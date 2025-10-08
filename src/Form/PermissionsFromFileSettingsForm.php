<?php

namespace Drupal\permissions_from_file\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Permissions From File settings for this site.
 */
class PermissionsFromFileSettingsForm extends ConfigFormBase {

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
    $path = $this->config('permissions_from_file.settings')->get('file_path');

    $form['file_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('File Path'),
      '#required' => TRUE,
      '#default_value' => !empty($path) ? $path : '',
      '#description' => $this->t("Path to the file on the server that contains usernames."),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $path = $form_state->getValue('file_path');
    if (is_string($path) && !file_exists($path)) {
      $form_state->setErrorByName('file_path', $this->t('File could not be read. Please confirm that it exists and is readable.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);

    $this->config('permissions_from_file.settings')
      ->set('file_path', $form_state->getValue('file_path'))
      ->save();
  }

}
