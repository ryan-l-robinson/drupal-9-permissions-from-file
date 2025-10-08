<?php

namespace Drupal\Tests\permissions_from_file\Unit\Form;

use Drupal\permissions_from_file\Form\PermissionsFromFileSettingsForm;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\permissions_from_file\Form\PermissionsFromFileSettingsForm
 * @group laurier_custom
 */
class PermissionsFromFileSettingsFormTest extends UnitTestCase {

  /**
   * The form under test.
   *
   * @var \Drupal\permissions_from_file\Form\PermissionsFromFileSettingsForm
   */
  protected $form;

  /**
   * The config mock.
   *
   * @var \Drupal\Core\Config\Config|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $config;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock config.
    $this->config = $this->createMock(Config::class);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('getEditable')
      ->with('permissions_from_file.settings')
      ->willReturn($this->config);
    $config_factory->method('get')
      ->with('permissions_from_file.settings')
      ->willReturn($this->config);

    // Mock translation service.
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')
    // Return the original string.
      ->willReturnArgument(0);

    // Mock messenger service.
    $messenger = $this->createMock(MessengerInterface::class);

    // Mock typed config manager.
    $typed_config_manager = $this->createMock(TypedConfigManagerInterface::class);

    // Set up container.
    $container = new ContainerBuilder();
    $container->set('string_translation', $translation);
    $container->set('messenger', $messenger);
    \Drupal::setContainer($container);

    $this->form = new PermissionsFromFileSettingsForm($config_factory, $typed_config_manager);
  }

  /**
   * @covers ::getFormId
   */
  public function testGetFormId(): void {
    $this->assertEquals('permissions_from_file_settings', $this->form->getFormId());
  }

  /**
   * @covers ::getEditableConfigNames
   */
  public function testGetEditableConfigNames(): void {
    $reflection = new \ReflectionClass($this->form);
    $method = $reflection->getMethod('getEditableConfigNames');
    $method->setAccessible(TRUE);

    $result = $method->invoke($this->form);
    $this->assertEquals(['permissions_from_file.settings'], $result);
  }

  /**
   * @covers ::buildForm
   */
  public function testBuildForm(): void {
    $this->config->method('get')
      ->with('file_path')
      ->willReturn('/some/path');

    $form_state = new FormState();
    $form = $this->form->buildForm([], $form_state);

    $this->assertArrayHasKey('file_path', $form);
    $this->assertEquals('/some/path', $form['file_path']['#default_value']);
  }

  /**
   * @covers ::validateForm
   */
  public function testValidateFormWithInvalidPath(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValue('file_path', '/invalid/path');

    $this->form->validateForm($form, $form_state);

    $this->assertTrue($form_state->hasAnyErrors());
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitForm(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValue('file_path', '/valid/path');

    $this->config->expects($this->once())
      ->method('set')
      ->with('file_path', '/valid/path')
      ->willReturnSelf();

    $this->config->expects($this->once())
      ->method('save');

    $this->form->submitForm($form, $form_state);
  }

}
