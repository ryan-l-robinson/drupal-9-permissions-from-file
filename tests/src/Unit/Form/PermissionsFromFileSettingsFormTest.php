<?php

namespace Drupal\Tests\permissions_from_file\Unit\Form;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\permissions_from_file\Form\PermissionsFromFileSettingsForm;
use Drupal\permissions_from_file\Services\PermissionsFromFileConfig;
use Drupal\Tests\UnitTestCase;
use Drupal\user\RoleInterface;

/**
 * @coversDefaultClass \Drupal\permissions_from_file\Form\PermissionsFromFileSettingsForm
 * @group permissions_from_file
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
   * The entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The config service mock.
   *
   * @var \Drupal\permissions_from_file\Services\PermissionsFromFileConfig|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $permissionsFromFileConfig;

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

    // Mock entity type manager and role storage.
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $role_storage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager->method('getStorage')->with('user_role')->willReturn($role_storage);

    $roles = [
      'anonymous' => $this->createMockRole('anonymous', 'Anonymous'),
      'authenticated' => $this->createMockRole('authenticated', 'Authenticated'),
      'editor' => $this->createMockRole('editor', 'Editor'),
      'administrator' => $this->createMockRole('administrator', 'Administrator'),
    ];
    $role_storage->method('loadMultiple')->willReturn($roles);

    // Mock the config service.
    $this->permissionsFromFileConfig = $this->createMock(PermissionsFromFileConfig::class);

    // Mock translation service.
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturnArgument(0);

    // Mock typed config manager.
    $typed_config_manager = $this->createMock(TypedConfigManagerInterface::class);

    // Set up container.
    $container = new ContainerBuilder();
    $container->set('string_translation', $translation);
    $container->set('config.factory', $config_factory);
    $container->set('entity_type.manager', $this->entityTypeManager);
    $container->set('permissions_from_file.config', $this->permissionsFromFileConfig);
    $container->set('messenger', $this->createMock(MessengerInterface::class));
    $container->set('config.typed', $typed_config_manager);
    \Drupal::setContainer($container);

    $this->form = PermissionsFromFileSettingsForm::create($container);
  }

  /**
   * Helper to create a mock role.
   */
  protected function createMockRole(string $id, string $label): RoleInterface {
    $role = $this->createMock(RoleInterface::class);
    $role->method('id')->willReturn($id);
    $role->method('label')->willReturn($label);
    return $role;
  }

  /**
   * @covers ::getFormId
   */
  public function testGetFormId(): void {
    $this->assertEquals('permissions_from_file_settings', $this->form->getFormId());
  }

  /**
   * @covers ::buildForm
   */
  public function testBuildForm(): void {
    $this->config->method('get')
      ->with('mappings')
      ->willReturn([
        ['file_path' => '/some/path.txt', 'roles' => ['editor']],
      ]);

    $form_state = new FormState();
    $form = $this->form->buildForm([], $form_state);

    $this->assertArrayHasKey('mappings_wrapper', $form);
    $this->assertCount(2, $form['mappings_wrapper']['mappings']);
    $this->assertEquals('/some/path.txt', $form['mappings_wrapper']['mappings'][0]['file_path']['#default_value']);
    $this->assertEquals(['editor'], $form['mappings_wrapper']['mappings'][0]['roles']['#default_value']);
  }

  /**
   * @covers ::validateForm
   */
  public function testValidateFormWithInvalidPath(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValue('mappings', [
      ['file_path' => '/invalid/path', 'roles' => ['editor' => 'editor']],
    ]);

    $this->form->validateForm($form, $form_state);

    $this->assertCount(1, $form_state->getErrors());
  }

  /**
   * @covers ::validateForm
   */
  public function testValidateFormWithMissingRole(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValue('mappings', [
      ['file_path' => '/some/path.txt', 'roles' => []],
    ]);

    $this->form->validateForm($form, $form_state);

    $this->assertCount(1, $form_state->getErrors());
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitForm(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValue('mappings', [
      ['file_path' => '/valid/path.txt', 'roles' => ['editor' => 'editor']],
      ['file_path' => '', 'roles' => []],
    ]);

    $this->config->expects($this->once())
      ->method('set')
      ->with('mappings', [['file_path' => '/valid/path.txt', 'roles' => ['editor']]])
      ->willReturnSelf();

    $this->config->expects($this->once())->method('save');
    $this->permissionsFromFileConfig->expects($this->once())->method('updateUnmappedRoles');

    $this->form->submitForm($form, $form_state);
  }

  /**
   * @covers ::addOne
   */
  public function testAddOne(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->set('mappings_count', 1);
    $this->form->addOne($form, $form_state);
    $this->assertEquals(2, $form_state->get('mappings_count'));
    $this->assertTrue($form_state->isRebuilding());
  }

  /**
   * @covers ::removeOne
   */
  public function testRemoveOne(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->set('mappings_count', 2);
    $this->form->removeOne($form, $form_state);
    $this->assertEquals(1, $form_state->get('mappings_count'));
    $this->assertTrue($form_state->isRebuilding());
  }

}
