<?php

namespace Drupal\Tests\permissions_from_file\Unit\Services;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\permissions_from_file\Services\PermissionsFromFileCheck;
use Drupal\Tests\UnitTestCase;
use Drupal\user\Entity\User;
use org\bovigo\vfs\vfsStream;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Drupal\permissions_from_file\Services\PermissionsFromFileCheck
 * @group permissions_from_file
 */
class PermissionsFromFileCheckTest extends UnitTestCase {

  /**
   * The service under test.
   *
   * @var \Drupal\permissions_from_file\Services\PermissionsFromFileCheck
   */
  protected $service;

  /**
   * The config mock.
   *
   * @var \Drupal\Core\Config\Config|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $config;

  /**
   * The user mock.
   *
   * @var \Drupal\user\Entity\User|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $user;

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

    // Mock entity type manager.
    $entity_type_manager = $this->createMock(EntityTypeManager::class);

    // Mock logger.
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->with('permissions_from_file')->willReturn($logger);

    // Mock user.
    $this->user = $this->createMock(User::class);
    $this->user->method('getAccountName')->willReturn('testuser');

    $this->service = new PermissionsFromFileCheck($config_factory, $entity_type_manager, $logger_factory);
  }

  /**
   * @covers ::isInFile
   */
  public function testIsInFile(): void {
    $root = vfsStream::setup('root');
    $file = vfsStream::newFile('users.txt')->withContent("testuser\nanotheruser")->at($root);

    $this->assertTrue($this->service->isInFile($this->user, $file->url()));
    $this->assertNull($this->service->isInFile($this->user, 'vfs://root/nonexistent.txt'));
  }

  /**
   * @covers ::updatePermissions
   */
  public function testUpdatePermissions(): void {
    $root = vfsStream::setup('root');
    $file1 = vfsStream::newFile('editors.txt')->withContent("testuser")->at($root);
    $file2 = vfsStream::newFile('admins.txt')->withContent("anotheruser")->at($root);

    $mappings = [
      ['file_path' => $file1->url(), 'roles' => ['editor']],
      ['file_path' => $file2->url(), 'roles' => ['administrator']],
    ];
    $this->config->method('get')
      ->will($this->returnValueMap([
        ['mappings', $mappings],
        ['unmapped', ['anonymous', 'authenticated']],
      ]));

    $this->user->method('getRoles')->willReturn(['authenticated', 'administrator']);
    $this->user->expects($this->once())->method('addRole')->with('editor');
    $this->user->expects($this->once())->method('removeRole')->with('administrator');
    $this->user->expects($this->once())->method('save');

    $this->service->updatePermissions($this->user);
  }

}
