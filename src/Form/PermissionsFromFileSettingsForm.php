<?php

namespace Drupal\permissions_from_file\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\permissions_from_file\Services\PermissionsFromFileConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure permissions settings for this site.
 */
class PermissionsFromFileSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The config updating service.
   *
   * @var \Drupal\permissions_from_file\Services\PermissionsFromFileConfig
   */
  protected PermissionsFromFileConfig $permissionsFromFileConfig;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->permissionsFromFileConfig = $container->get('permissions_from_file.config');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'permissions_from_file_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['permissions_from_file.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('permissions_from_file.settings');

    // Existing values from config; each is:
    // ['file_path' => '...', 'roles' => ['role_id', ...]].
    $defaults = $config->get('mappings') ?: [];

    // Build roles options: role_id => label, excluding anonymous/authenticated.
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    $role_options = [];
    foreach ($roles as $role) {
      $role_options[$role->id()] = $role->label();
    }
    unset($role_options['anonymous'], $role_options['authenticated']);

    // Track how many rows to display in the repeatable set.
    $count = $form_state->get('mappings_count');
    if ($count === NULL) {
      $count = max(1, is_array($defaults) ? count($defaults) : 1);
      $form_state->set('mappings_count', $count);
    }

    $form['description'] = [
      '#markup' => $this->t('Add as many mappings as you need. Each mapping has a <em>File Path</em> and one or more <em>Roles</em>. Usernames found in the file will be assigned the selected roles.'),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];

    // Wrapper for AJAX updates.
    $form['mappings_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'permissions-file-mappings-wrapper'],
    ];

    // Container that preserves nested values; no outer fieldset.
    $form['mappings_wrapper']['mappings'] = [
      '#tree' => TRUE,
    ];

    // Build rows.
    for ($i = 0; $i < $count; $i++) {
      $form['mappings_wrapper']['mappings'][$i] = [
        '#type' => 'details',
        '#title' => $this->t('Mapping @num', ['@num' => $i + 1]),
        '#open' => TRUE,
      ];

      if (!is_array($defaults) || !isset($defaults[$i]) || !is_array($defaults[$i])) {
        $default_file_path = '';
        $default_roles = [];
      }
      else {
        $default_file_path = $defaults[$i]['file_path'] ?? '';
        $default_roles = $defaults[$i]['roles'] ?? [];
      }
      $form['mappings_wrapper']['mappings'][$i]['file_path'] = [
        '#type' => 'textfield',
        '#title' => $this->t('File Path'),
        '#required' => FALSE,
        '#default_value' => $default_file_path,
        '#description' => $this->t('Absolute path or a stream wrapper (e.g., <code>public://users.txt</code>) to a file with one username per line.'),
      ];

      // Multi-select roles via checkboxes.
      if (!is_array($default_roles)) {
        $default_roles = $default_roles ? [$default_roles] : [];
      }

      $form['mappings_wrapper']['mappings'][$i]['roles'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Roles'),
        '#options' => $role_options,
        // Array of selected role IDs.
        '#default_value' => $default_roles,
        '#description' => $this->t('Select one or more roles to assign to users listed in the file.'),
      ];
    }

    // Add and remove buttons.
    $form['mappings_wrapper']['actions'] = [
      '#type' => 'actions',
    ];

    $form['mappings_wrapper']['actions']['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add mapping'),
      '#submit' => ['::addOne'],
      '#ajax' => [
        'callback' => '::ajaxRebuildMappings',
        'wrapper' => 'permissions-file-mappings-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];

    $form['mappings_wrapper']['actions']['remove'] = [
      '#type' => 'submit',
      '#value' => $this->t('Remove last'),
      '#submit' => ['::removeOne'],
      '#ajax' => [
        'callback' => '::ajaxRebuildMappings',
        'wrapper' => 'permissions-file-mappings-wrapper',
      ],
      '#limit_validation_errors' => [],
      '#attributes' => ['class' => ['button--danger']],
      '#access' => $count > 1,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * AJAX callback to re-render the mappings wrapper.
   */
  public function ajaxRebuildMappings(array &$form, FormStateInterface $form_state): mixed {
    return $form['mappings_wrapper'];
  }

  /**
   * Submit handler: Add one row.
   */
  public function addOne(array &$form, FormStateInterface $form_state): void {
    $count = $form_state->get('mappings_count');
    if (is_numeric($count)) {
      $form_state->set('mappings_count', $count + 1);
      $form_state->setRebuild(TRUE);
    }
  }

  /**
   * Submit handler: Remove one row.
   */
  public function removeOne(array &$form, FormStateInterface $form_state): void {
    $count = $form_state->get('mappings_count');
    if (is_numeric($count) && $count > 1) {
      $form_state->set('mappings_count', $count - 1);
      $form_state->setRebuild(TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $rows = $form_state->getValue(['mappings']) ?? [];
    if (!is_array($rows)) {
      return;
    }

    foreach ($rows as $delta => $row) {
      $path = trim((string) ($row['file_path'] ?? ''));
      $has_path = $path !== '';

      $roles_raw = $row['roles'] ?? [];
      if (!is_array($roles_raw)) {
        $roles_raw = $roles_raw ? [$roles_raw] : [];
      }
      // Keep only selected role IDs (remove '0' placeholders).
      $selected_roles = array_keys(array_filter($roles_raw));
      $has_roles = !empty($selected_roles);

      // Either both must be present or both empty.
      if (($has_path && !$has_roles) || (!$has_path && $has_roles)) {
        $form_state->setErrorByName("mappings][$delta][file_path",
          $this->t('Both File Path and at least one Role must be provided (or leave both empty).')
        );
      }

      // Per-row file existence check if a path provided.
      if ($has_path) {
        if (!file_exists($path)) {
          $form_state->setErrorByName("mappings][$delta][file_path",
            $this->t('The file "@path" could not be found or is not readable.', ['@path' => $path])
          );
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValue('mappings') ?? [];

    // Normalize & filter out completely empty rows.
    $clean = [];
    if (is_array($values)) {
      foreach ($values as $row) {
        $path = trim((string) ($row['file_path'] ?? ''));
        $roles_raw = $row['roles'] ?? [];
        if (!is_array($roles_raw)) {
          $roles_raw = $roles_raw ? [$roles_raw] : [];
        }

        // Keep only selected role IDs.
        $selected_roles = array_keys(array_filter($roles_raw));

        // Skip fully empty row.
        if ($path === '' && empty($selected_roles)) {
          continue;
        }

        $clean[] = [
          'file_path' => $path,
          'roles' => $selected_roles,
        ];
      }

      $this->configFactory->getEditable('permissions_from_file.settings')
        ->set('mappings', array_values($clean))
        ->save();

      $this->permissionsFromFileConfig->updateUnmappedRoles();
    }

    parent::submitForm($form, $form_state);
  }

}
