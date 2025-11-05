<?php

namespace Drupal\permissions_from_file\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;

/**
 * Service for updating configuration.
 */
class PermissionsFromFileConfig {

  /**
   * Config.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $config;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected EntityTypeManager $entityTypeManager;

  public function __construct(
    ConfigFactoryInterface $config,
    EntityTypeManager $entity_type_manager,
  ) {
    $this->config = $config;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Updates configuration to list all unmapped roles.
   *
   * This is included as a matter of efficiency, to
   * store the unmapped values instead of determing them
   * with every login.
   */
  public function updateUnmappedRoles(): void {
    // Track all unmapped roles which should never be removed.
    // Start with all roles, then remove as we find a mapping.
    // An array with key as string of role name, value as the Role object.
    $unmapped_roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();

    // Get each mapping from the config, then loop through them.
    // If a mapping is found, remove it from the unmapped_roles.
    $mappings = $this->config->getEditable('permissions_from_file.settings')->get('mappings');
    if (is_iterable($mappings)) {
      foreach ($mappings as $mapping) {
        if (is_array($mapping) && isset($mapping['file_path']) && isset($mapping['roles'])) {
          $roles = $mapping['roles'];

          foreach ($roles as $role) {
            // Unset from the unmapped roles array.
            if (array_key_exists($role, $unmapped_roles)) {
              unset($unmapped_roles[$role]);
            }
          }
        }
      }
    }
    // Update the configuration for unmapped roles to be what's left.
    $this->config->getEditable('permissions_from_file.settings')
      ->set('unmapped', array_keys($unmapped_roles))
      ->save();
  }

  /**
   * Removes a role from any config.
   *
   * This is included to get it out of config
   * when a role is deleted, before the code
   * would otherwise try to assign it to users.
   *
   * @param string $role_id
   *   The role to remove.
   */
  public function removeRole(string $role_id): void {
    $mappings = $this->config->getEditable('permissions_from_file.settings')->get('mappings');
    if (is_array($mappings)) {
      for ($k = 0; $k < count($mappings); $k++) {
        if (is_array($mappings[$k]) && isset($mappings[$k]['roles'])) {
          // If in this array, remove it.
          if (($key = array_search($role_id, $mappings[$k]['roles'])) !== FALSE) {
            unset($mappings[$k]['roles'][$key]);
          }
        }
      }
      $this->config->getEditable('permissions_from_file.settings')->set('mappings', $mappings)->save();
    }
  }

}
