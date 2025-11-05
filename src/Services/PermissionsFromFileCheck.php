<?php

namespace Drupal\permissions_from_file\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\user\Entity\User;

/**
 * Service for testing permissions against file content.
 */
class PermissionsFromFileCheck {

  /**
   * Configuration management service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected EntityTypeManager $entityTypeManager;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  public function __construct(
    ConfigFactoryInterface $config,
    EntityTypeManager $entity_type_manager,
    LoggerChannelFactoryInterface $logger,
  ) {
    $this->config = $config;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger->get('permissions_from_file');
  }

  /**
   * Reads file on server, determines if specified user is in the list.
   *
   * @param \Drupal\user\Entity\User $account
   *   The user to test.
   * @param string $path
   *   The path of the file to check.
   *
   * @return ?bool
   *   if the username is in the list or not, or NULL if file couldn't be read
   */
  public function isInFile(User $account, string $path): bool|null {
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
  public function updatePermissions(User $account): void {
    // This tracks roles to apply for the current user.
    $roles_to_apply = [];

    // Get each mapping from the config, then loop through them.
    // Check all files, compile the list of permissions,
    // then apply all the changes at once.
    $mappings = $this->config->getEditable('permissions_from_file.settings')->get('mappings');

    if (is_iterable($mappings)) {
      foreach ($mappings as $mapping) {
        if (is_array($mapping) && isset($mapping['file_path']) && isset($mapping['roles'])) {
          $path = $mapping['file_path'];
          $roles = $mapping['roles'];

          foreach ($roles as $role) {
            // Add to the roles list if in the file and not already there.
            if ($this->isInFile($account, $path) && !in_array($role, $roles_to_apply)) {
              $roles_to_apply[] = $role;
            }
          }
        }
      }
    }

    // Add any roles as needed to the user.
    foreach ($roles_to_apply as $role_to_apply) {
      if (!$account->hasRole($role_to_apply)) {
        $account->addRole($role_to_apply);
      }
    }

    // Remove any mapped roles which are currently applied
    // and we now see from the files they shouldn't be.
    $current_roles = $account->getRoles();
    $unmapped_roles = $this->config->get('permissions_from_file.settings')->get('unmapped');
    if (is_array($unmapped_roles)) {
      foreach ($current_roles as $current_role) {
        if (!in_array($current_role, $roles_to_apply) && !in_array($current_role, $unmapped_roles)) {
          $account->removeRole($current_role);
        }
      }
    }
    else {
      // Log an alert if configuration structure changed.
      $this->logger->alert("Roles could not be removed. Has the configuration structure changed for the unmapped roles?");
    }

    $account->save();
  }

}
