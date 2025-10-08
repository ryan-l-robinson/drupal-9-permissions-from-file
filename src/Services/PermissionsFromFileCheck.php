<?php

namespace Drupal\permissions_from_file\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\user\Entity\User;

/**
 * Service for testing permissions against file content.
 */
class PermissionsFromFileCheck {

  /**
   * Config.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  public function __construct(
    ConfigFactoryInterface $config,
    LoggerChannelFactoryInterface $logger,
  ) {
    $this->config = $config;
    $this->logger = $logger->get('permissions_from_file');
  }

  /**
   * Reads file on server, determines if specified user is in the list.
   *
   * @param \Drupal\user\Entity\User $account
   *   The user to test.
   *
   * @return ?bool
   *   if the username is in the list or not, or NULL if file couldn't be read
   */
  public function isInList(User $account): bool|null {
    $path = $this->config->getEditable('permissions_from_file.settings')->get('file_path');
    if (is_string($path) && !empty($path)) {
      if (file_exists($path)) {
        $contents = file_get_contents($path);
        if ($contents) {
          return boolval(preg_match("/" . $account->getAccountName() . "/", $contents));
        }
        else {
          // Log an alert if file exists but contents couldn't be read.
          $this->logger->alert("Usernames file has empty content.");
        }
      }
      else {
        // Log an alert if file couldn't be found.
        $this->logger->alert("Usernames file could not be found.");
      }
    }
    return NULL;
  }

  /**
   * Adds or removes the role from the account.
   *
   * @param \Drupal\user\Entity\User $account
   *   The user to add or remove the role from.
   */
  public function updateAlcPermissions(User $account): void {
    $is_in_list = $this->isInList($account);
    // @todo Move this out to be configurable, mapping files to roles.
    $role = 'role_to_add';
    if ($is_in_list && !$account->hasRole($role)) {
      $account->addRole($role);
      $account->save();
    }
    elseif ($is_in_list == FALSE && $account->hasRole($role)) {
      $account->removeRole($role);
      $account->save();
    }
  }

}
