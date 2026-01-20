<?php
/**
 * Symfony Plugin Tests
 */

namespace PhpKnip\Tests\Unit\Plugin\Symfony;

use PhpKnip\Tests\TestCase;
use PhpKnip\Plugin\Symfony\SymfonyPlugin;
use PhpKnip\Resolver\SymbolTable;
use PhpKnip\Resolver\Symbol;

class SymfonyPluginTest extends TestCase
{
    /**
     * @var SymfonyPlugin
     */
    private $plugin;

    /**
     * @var string
     */
    private $tempDir;

    protected function setUp(): void
    {
        $this->plugin = new SymfonyPlugin();
        $this->tempDir = sys_get_temp_dir() . '/php-knip-symfony-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testGetName()
    {
        $this->assertEquals('symfony', $this->plugin->getName());
    }

    public function testGetDescription()
    {
        $this->assertNotEmpty($this->plugin->getDescription());
    }

    public function testIsApplicableWithBinConsole()
    {
        mkdir($this->tempDir . '/bin', 0777, true);
        file_put_contents($this->tempDir . '/bin/console', '#!/usr/bin/env php');

        $this->assertTrue($this->plugin->isApplicable($this->tempDir, array()));
    }

    public function testIsApplicableWithSymfonyLock()
    {
        file_put_contents($this->tempDir . '/symfony.lock', '{}');

        $this->assertTrue($this->plugin->isApplicable($this->tempDir, array()));
    }

    public function testIsApplicableWithBundlesConfig()
    {
        mkdir($this->tempDir . '/config', 0777, true);
        file_put_contents($this->tempDir . '/config/bundles.php', '<?php return [];');

        $this->assertTrue($this->plugin->isApplicable($this->tempDir, array()));
    }

    public function testIsApplicableWithFrameworkBundle()
    {
        $composerData = array(
            'require' => array(
                'symfony/framework-bundle' => '^6.0',
            ),
        );

        $this->assertTrue($this->plugin->isApplicable($this->tempDir, $composerData));
    }

    public function testIsApplicableWithHttpKernel()
    {
        $composerData = array(
            'require' => array(
                'symfony/http-kernel' => '^6.0',
            ),
        );

        $this->assertTrue($this->plugin->isApplicable($this->tempDir, $composerData));
    }

    public function testIsApplicableWithConsole()
    {
        $composerData = array(
            'require' => array(
                'symfony/console' => '^6.0',
            ),
        );

        $this->assertTrue($this->plugin->isApplicable($this->tempDir, $composerData));
    }

    public function testIsNotApplicableWithoutSymfony()
    {
        $composerData = array(
            'require' => array(
                'laravel/framework' => '^9.0',
            ),
        );

        $this->assertFalse($this->plugin->isApplicable($this->tempDir, $composerData));
    }

    public function testGetPriority()
    {
        $this->assertEquals(100, $this->plugin->getPriority());
    }

    public function testGetIgnorePatterns()
    {
        $patterns = $this->plugin->getIgnorePatterns();

        $this->assertContains('App\\Kernel', $patterns);
        $this->assertContains('*Controller', $patterns);
        $this->assertContains('*Command', $patterns);
        $this->assertContains('*Subscriber', $patterns);
        $this->assertContains('*Repository', $patterns);
    }

    public function testGetIgnoreFilePatterns()
    {
        $patterns = $this->plugin->getIgnoreFilePatterns();

        $this->assertContains('**/var/cache/**', $patterns);
        $this->assertContains('**/var/log/**', $patterns);
        $this->assertContains('**/vendor/**', $patterns);
    }

    public function testGetEntryPointsWithControllers()
    {
        // Create Symfony-like structure
        $controllerDir = $this->tempDir . '/src/Controller';
        mkdir($controllerDir, 0777, true);

        $controllerContent = <<<'PHP'
<?php
namespace App\Controller;

class HomeController
{
    public function index() {}
}
PHP;
        file_put_contents($controllerDir . '/HomeController.php', $controllerContent);

        $entryPoints = $this->plugin->getEntryPoints($this->tempDir);

        $this->assertContains('App\\Controller\\HomeController', $entryPoints);
    }

    public function testGetEntryPointsWithCommands()
    {
        // Create Command structure
        $commandDir = $this->tempDir . '/src/Command';
        mkdir($commandDir, 0777, true);

        $commandContent = <<<'PHP'
<?php
namespace App\Command;

class ImportDataCommand
{
    protected static $defaultName = 'app:import-data';
}
PHP;
        file_put_contents($commandDir . '/ImportDataCommand.php', $commandContent);

        $entryPoints = $this->plugin->getEntryPoints($this->tempDir);

        $this->assertContains('App\\Command\\ImportDataCommand', $entryPoints);
    }

    public function testGetEntryPointsWithEventSubscribers()
    {
        // Create EventSubscriber structure
        $subscriberDir = $this->tempDir . '/src/EventSubscriber';
        mkdir($subscriberDir, 0777, true);

        $subscriberContent = <<<'PHP'
<?php
namespace App\EventSubscriber;

class RequestSubscriber
{
    public static function getSubscribedEvents() {}
}
PHP;
        file_put_contents($subscriberDir . '/RequestSubscriber.php', $subscriberContent);

        $entryPoints = $this->plugin->getEntryPoints($this->tempDir);

        $this->assertContains('App\\EventSubscriber\\RequestSubscriber', $entryPoints);
    }

    public function testGetEntryPointsWithKernel()
    {
        // Create Kernel
        $srcDir = $this->tempDir . '/src';
        mkdir($srcDir, 0777, true);

        $kernelContent = <<<'PHP'
<?php
namespace App;

class Kernel
{
    public function boot() {}
}
PHP;
        file_put_contents($srcDir . '/Kernel.php', $kernelContent);

        $entryPoints = $this->plugin->getEntryPoints($this->tempDir);

        $this->assertContains('App\\Kernel', $entryPoints);
    }

    public function testGetAdditionalReferencesFromBundles()
    {
        // Create bundles.php
        $configDir = $this->tempDir . '/config';
        mkdir($configDir, 0777, true);

        $bundlesContent = <<<'PHP'
<?php
return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    App\MyBundle\MyBundle::class => ['all' => true],
    Acme\CustomBundle\AcmeBundle::class => ['all' => true],
];
PHP;
        file_put_contents($configDir . '/bundles.php', $bundlesContent);

        $references = $this->plugin->getAdditionalReferences($this->tempDir);

        $classNames = array_map(function ($ref) {
            return $ref->getSymbolName();
        }, $references);

        // Should include app bundles but not Symfony core bundles
        $this->assertContains('App\\MyBundle\\MyBundle', $classNames);
        $this->assertContains('Acme\\CustomBundle\\AcmeBundle', $classNames);
        $this->assertNotContains('Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle', $classNames);
    }

    public function testGetAdditionalReferencesFromServices()
    {
        // Create services.yaml
        $configDir = $this->tempDir . '/config';
        mkdir($configDir, 0777, true);

        $servicesContent = <<<'YAML'
services:
    App\Service\MyService:
        arguments:
            - '@doctrine.orm.entity_manager'

    App\Repository\UserRepository:
        class: App\Repository\UserRepository
YAML;
        file_put_contents($configDir . '/services.yaml', $servicesContent);

        $references = $this->plugin->getAdditionalReferences($this->tempDir);

        $classNames = array_map(function ($ref) {
            return $ref->getSymbolName();
        }, $references);

        $this->assertContains('App\\Service\\MyService', $classNames);
        $this->assertContains('App\\Repository\\UserRepository', $classNames);
    }

    public function testGetAdditionalReferencesFromRoutes()
    {
        // Create routes.yaml
        $configDir = $this->tempDir . '/config';
        mkdir($configDir, 0777, true);

        $routesContent = <<<'YAML'
home:
    path: /
    controller: App\Controller\HomeController::index

api_users:
    path: /api/users
    controller: App\Controller\Api\UserController::list
YAML;
        file_put_contents($configDir . '/routes.yaml', $routesContent);

        $references = $this->plugin->getAdditionalReferences($this->tempDir);

        $classNames = array_map(function ($ref) {
            return $ref->getSymbolName();
        }, $references);

        $this->assertContains('App\\Controller\\HomeController', $classNames);
        $this->assertContains('App\\Controller\\Api\\UserController', $classNames);
    }

    public function testProcessSymbolsMarksControllersAsUsed()
    {
        $symbolTable = new SymbolTable();
        $controller = Symbol::createClass('HomeController', 'App\\Controller');
        $symbolTable->add($controller);

        $this->plugin->processSymbols($symbolTable, $this->tempDir);

        $this->assertTrue($controller->getMetadataValue('framework_used', false));
    }

    public function testProcessSymbolsMarksCommandsAsUsed()
    {
        $symbolTable = new SymbolTable();
        $command = Symbol::createClass('ImportCommand', 'App\\Command');
        $symbolTable->add($command);

        $this->plugin->processSymbols($symbolTable, $this->tempDir);

        $this->assertTrue($command->getMetadataValue('framework_used', false));
    }

    public function testProcessSymbolsMarksEventSubscribersAsUsed()
    {
        $symbolTable = new SymbolTable();
        $subscriber = Symbol::createClass('RequestSubscriber', 'App\\EventSubscriber');
        $symbolTable->add($subscriber);

        $this->plugin->processSymbols($symbolTable, $this->tempDir);

        $this->assertTrue($subscriber->getMetadataValue('framework_used', false));
    }

    public function testProcessSymbolsMarksFormTypesWithFrameworkType()
    {
        $symbolTable = new SymbolTable();
        $formType = Symbol::createClass('UserType', 'App\\Form');
        $symbolTable->add($formType);

        $this->plugin->processSymbols($symbolTable, $this->tempDir);

        $this->assertEquals('form_type', $formType->getMetadataValue('framework_type'));
    }

    public function testProcessSymbolsMarksEntitiesWithFrameworkType()
    {
        $symbolTable = new SymbolTable();
        $entity = Symbol::createClass('User', 'App\\Entity');
        $symbolTable->add($entity);

        $this->plugin->processSymbols($symbolTable, $this->tempDir);

        $this->assertEquals('doctrine_entity', $entity->getMetadataValue('framework_type'));
    }

    public function testProcessSymbolsMarksRepositoriesWithFrameworkType()
    {
        $symbolTable = new SymbolTable();
        $repository = Symbol::createClass('UserRepository', 'App\\Repository');
        $symbolTable->add($repository);

        $this->plugin->processSymbols($symbolTable, $this->tempDir);

        $this->assertEquals('doctrine_repository', $repository->getMetadataValue('framework_type'));
    }

    public function testProcessSymbolsMarksTwigExtensionsWithFrameworkType()
    {
        $symbolTable = new SymbolTable();
        $extension = Symbol::createClass('AppExtension', 'App\\Twig');
        $symbolTable->add($extension);

        $this->plugin->processSymbols($symbolTable, $this->tempDir);

        $this->assertEquals('twig_extension', $extension->getMetadataValue('framework_type'));
    }

    public function testProcessSymbolsMarksMessageHandlersWithFrameworkType()
    {
        $symbolTable = new SymbolTable();
        $handler = Symbol::createClass('SendEmailHandler', 'App\\MessageHandler');
        $symbolTable->add($handler);

        $this->plugin->processSymbols($symbolTable, $this->tempDir);

        $this->assertEquals('message_handler', $handler->getMetadataValue('framework_type'));
    }

    public function testProcessSymbolsMarksVotersWithFrameworkType()
    {
        $symbolTable = new SymbolTable();
        $voter = Symbol::createClass('PostVoter', 'App\\Security');
        $symbolTable->add($voter);

        $this->plugin->processSymbols($symbolTable, $this->tempDir);

        $this->assertEquals('security_voter', $voter->getMetadataValue('framework_type'));
    }

    public function testReferencesHaveMetadata()
    {
        // Create routes.yaml
        $configDir = $this->tempDir . '/config';
        mkdir($configDir, 0777, true);

        $routesContent = <<<'YAML'
home:
    path: /
    controller: App\Controller\HomeController::index
YAML;
        file_put_contents($configDir . '/routes.yaml', $routesContent);

        $references = $this->plugin->getAdditionalReferences($this->tempDir);

        $this->assertNotEmpty($references);
        $ref = $references[0];
        $metadata = $ref->getMetadata();

        $this->assertEquals('symfony_routes', $metadata['source']);
    }

    public function testGetEntryPointsFromBundles()
    {
        // Create bundles.php with app bundle
        $configDir = $this->tempDir . '/config';
        mkdir($configDir, 0777, true);

        $bundlesContent = <<<'PHP'
<?php
return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    App\MyBundle\AppMyBundle::class => ['all' => true],
];
PHP;
        file_put_contents($configDir . '/bundles.php', $bundlesContent);

        $entryPoints = $this->plugin->getEntryPoints($this->tempDir);

        // Should include app bundles but not Symfony core bundles
        $this->assertContains('App\\MyBundle\\AppMyBundle', $entryPoints);
        $this->assertNotContains('Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle', $entryPoints);
    }

    public function testServicesDirectoryIsParsed()
    {
        // Create services directory with multiple files
        $servicesDir = $this->tempDir . '/config/services';
        mkdir($servicesDir, 0777, true);

        $servicesContent = <<<'YAML'
services:
    App\Service\EmailService:
        class: App\Service\EmailService
YAML;
        file_put_contents($servicesDir . '/email.yaml', $servicesContent);

        $references = $this->plugin->getAdditionalReferences($this->tempDir);

        $classNames = array_map(function ($ref) {
            return $ref->getSymbolName();
        }, $references);

        $this->assertContains('App\\Service\\EmailService', $classNames);
    }

    public function testRoutesDirectoryIsParsed()
    {
        // Create routes directory
        $routesDir = $this->tempDir . '/config/routes';
        mkdir($routesDir, 0777, true);

        $routesContent = <<<'YAML'
api:
    path: /api
    controller: App\Controller\ApiController::index
YAML;
        file_put_contents($routesDir . '/api.yaml', $routesContent);

        $references = $this->plugin->getAdditionalReferences($this->tempDir);

        $classNames = array_map(function ($ref) {
            return $ref->getSymbolName();
        }, $references);

        $this->assertContains('App\\Controller\\ApiController', $classNames);
    }

    /**
     * Remove directory recursively
     *
     * @param string $dir Directory path
     */
    private function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
