<?php

namespace Drupal\Tests\permissions_from_file\Unit\Services;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\permissions_from_file\Services\PermissionsFromFileConfig;
use Drupal\Tests\UnitTestCase;
use Drupal\user\RoleInterface;

/**
 * @coversDefaultClass \Drupal\permissions_from_file\Services\PermissionsFromFileConfig
 * @group permissions_from_file
 */
class PermissionsFromFileConfigTest extends UnitTestCase {

  /**
   * The service under test.
   *
   * @var \Drupal\permissions_from_file\Services\PermissionsFromFileConfig
   */
  protected $service;

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

    // Mock entity type manager and role storage.
    $entity_type_manager = $this->createMock(EntityTypeManager::class);
    $role_storage = $this->createMock(EntityStorageInterface::class);
    $entity_type_manager->method('getStorage')->with('user_role')->willReturn($role_storage);

    $roles = [
      'anonymous' => $this->createMockRole('anonymous', 'Anonymous'),
      'authenticated' => $this->createMockRole('authenticated', 'Authenticated'),
      'editor' => $this->createMockRole('editor', 'Editor'),
      'administrator' => $this->createMockRole('administrator', 'Administrator'),
    ];
    $role_storage->method('loadMultiple')->willReturn($roles);

    $this->service = new PermissionsFromFileConfig($config_factory, $entity_type_manager);
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
   * @covers ::updateUnmappedRoles
   */
  public function testUpdateUnmappedRoles(): void {
    $mappings = [
      ['file_path' => '/path/one.txt', 'roles' => ['editor']],
    ];
    $this->config->method('get')->with('mappings')->willReturn($mappings);

    $this->config->expects($this->once())
      ->method('set')
      ->with('unmapped', ['anonymous', 'authenticated', 'administrator'])
      ->willReturnSelf();
    $this->config->expects($this->once())->method('save');

    $this->service->updateUnmappedRoles();
  }

  /**
   * @covers ::removeRole
   */
  public function testRemoveRole(): void {
    $mappings = [
      ['file_path' => '/path/one.txt', 'roles' => ['editor', 'administrator']],
      ['file_path' => '/path/two.txt', 'roles' => ['administrator']],
    ];
    $this->config->method('get')->with('mappings')->willReturn($mappings);

    $expected_mappings = [
      ['file_path' => '/path/one.txt', 'roles' => ['editor']],
      ['file_path' => '/path/two.txt', 'roles' => []],
    ];
    $this->config->expects($this->once())
      ->method('set')
      ->with('mappings', $expected_mappings)
      ->willReturnSelf();
    $this->config->expects($this->once())->method('save');

    $this->service->removeRole('administrator');
  }

}
