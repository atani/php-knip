<?php
/**
 * Laravel Plugin Tests
 */

namespace PhpKnip\Tests\Unit\Plugin\Laravel;

use PHPUnit\Framework\TestCase;
use PhpKnip\Plugin\Laravel\LaravelPlugin;
use PhpKnip\Resolver\SymbolTable;
use PhpKnip\Resolver\Symbol;

class LaravelPluginTest extends TestCase
{
    /**
     * @var LaravelPlugin
     */
    private $plugin;

    /**
     * @var string
     */
    private $tempDir;

    protected function setUp()
    {
        $this->plugin = new LaravelPlugin();
        $this->tempDir = sys_get_temp_dir() . '/php-knip-laravel-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown()
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testGetName()
    {
        $this->assertEquals('laravel', $this->plugin->getName());
    }

    public function testGetDescription()
    {
        $this->assertNotEmpty($this->plugin->getDescription());
    }

    public function testIsApplicableWithArtisanFile()
    {
        file_put_contents($this->tempDir . '/artisan', '<?php // artisan');

        $this->assertTrue($this->plugin->isApplicable($this->tempDir, array()));
    }

    public function testIsApplicableWithLaravelDependency()
    {
        $composerData = array(
            'require' => array(
                'laravel/framework' => '^9.0',
            ),
        );

        $this->assertTrue($this->plugin->isApplicable($this->tempDir, $composerData));
    }

    public function testIsApplicableWithIlluminateDependency()
    {
        $composerData = array(
            'require' => array(
                'illuminate/database' => '^9.0',
            ),
        );

        $this->assertTrue($this->plugin->isApplicable($this->tempDir, $composerData));
    }

    public function testIsNotApplicableWithoutLaravel()
    {
        $composerData = array(
            'require' => array(
                'symfony/console' => '^5.0',
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

        $this->assertContains('App\\Http\\Kernel', $patterns);
        $this->assertContains('App\\Providers\\*', $patterns);
        $this->assertContains('*ServiceProvider', $patterns);
    }

    public function testGetIgnoreFilePatterns()
    {
        $patterns = $this->plugin->getIgnoreFilePatterns();

        $this->assertContains('**/storage/framework/**', $patterns);
        $this->assertContains('**/bootstrap/cache/**', $patterns);
        $this->assertContains('**/vendor/**', $patterns);
    }

    public function testGetEntryPointsWithControllers()
    {
        // Create Laravel-like structure
        $controllerDir = $this->tempDir . '/app/Http/Controllers';
        mkdir($controllerDir, 0777, true);

        $controllerContent = <<<'PHP'
<?php
namespace App\Http\Controllers;

class UserController
{
    public function index() {}
}
PHP;
        file_put_contents($controllerDir . '/UserController.php', $controllerContent);

        $entryPoints = $this->plugin->getEntryPoints($this->tempDir);

        $this->assertContains('App\\Http\\Controllers\\UserController', $entryPoints);
    }

    public function testGetEntryPointsWithCommands()
    {
        // Create Console Commands structure
        $commandDir = $this->tempDir . '/app/Console/Commands';
        mkdir($commandDir, 0777, true);

        $commandContent = <<<'PHP'
<?php
namespace App\Console\Commands;

class SendEmails
{
    protected $signature = 'emails:send';
}
PHP;
        file_put_contents($commandDir . '/SendEmails.php', $commandContent);

        $entryPoints = $this->plugin->getEntryPoints($this->tempDir);

        $this->assertContains('App\\Console\\Commands\\SendEmails', $entryPoints);
    }

    public function testGetEntryPointsWithEvents()
    {
        // Create Events structure
        $eventDir = $this->tempDir . '/app/Events';
        mkdir($eventDir, 0777, true);

        $eventContent = <<<'PHP'
<?php
namespace App\Events;

class OrderPlaced
{
}
PHP;
        file_put_contents($eventDir . '/OrderPlaced.php', $eventContent);

        $entryPoints = $this->plugin->getEntryPoints($this->tempDir);

        $this->assertContains('App\\Events\\OrderPlaced', $entryPoints);
    }

    public function testGetEntryPointsWithMiddleware()
    {
        // Create Middleware structure
        $middlewareDir = $this->tempDir . '/app/Http/Middleware';
        mkdir($middlewareDir, 0777, true);

        $middlewareContent = <<<'PHP'
<?php
namespace App\Http\Middleware;

class Authenticate
{
    public function handle($request, $next)
    {
        return $next($request);
    }
}
PHP;
        file_put_contents($middlewareDir . '/Authenticate.php', $middlewareContent);

        $entryPoints = $this->plugin->getEntryPoints($this->tempDir);

        $this->assertContains('App\\Http\\Middleware\\Authenticate', $entryPoints);
    }

    public function testGetAdditionalReferencesFromRoutes()
    {
        // Create routes directory
        $routeDir = $this->tempDir . '/routes';
        mkdir($routeDir, 0777, true);

        $routeContent = <<<'PHP'
<?php
use App\Http\Controllers\UserController;

Route::get('/users', [UserController::class, 'index']);
Route::post('/users', 'PostController@store');
PHP;
        file_put_contents($routeDir . '/web.php', $routeContent);

        $references = $this->plugin->getAdditionalReferences($this->tempDir);

        $classNames = array_map(function ($ref) {
            return $ref->getSymbolName();
        }, $references);

        // Note: The regex extracts the class name as it appears in the file
        // After use statement, it's just 'UserController', not the FQN
        $this->assertContains('UserController', $classNames);
        $this->assertContains('PostController', $classNames);
    }

    public function testGetAdditionalReferencesFromConfig()
    {
        // Create config directory
        $configDir = $this->tempDir . '/config';
        mkdir($configDir, 0777, true);

        $configContent = <<<'PHP'
<?php
return [
    'providers' => [
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
    ],
];
PHP;
        file_put_contents($configDir . '/app.php', $configContent);

        $references = $this->plugin->getAdditionalReferences($this->tempDir);

        $classNames = array_map(function ($ref) {
            return $ref->getSymbolName();
        }, $references);

        $this->assertContains('App\\Providers\\AppServiceProvider', $classNames);
        $this->assertContains('App\\Providers\\AuthServiceProvider', $classNames);
    }

    public function testProcessSymbolsMarksServiceProvidersAsUsed()
    {
        $symbolTable = new SymbolTable();
        $provider = Symbol::createClass('AppServiceProvider', 'App\\Providers');
        $symbolTable->add($provider);

        $this->plugin->processSymbols($symbolTable, $this->tempDir);

        $this->assertTrue($provider->getMetadataValue('framework_used', false));
    }

    public function testProcessSymbolsMarksControllersWithFrameworkType()
    {
        $symbolTable = new SymbolTable();
        $controller = Symbol::createClass('UserController', 'App\\Http\\Controllers');
        $symbolTable->add($controller);

        $this->plugin->processSymbols($symbolTable, $this->tempDir);

        $this->assertEquals('controller', $controller->getMetadataValue('framework_type'));
    }

    public function testProcessSymbolsMarksModelsWithFrameworkType()
    {
        $symbolTable = new SymbolTable();
        $model = Symbol::createClass('User', 'App\\Models');
        $symbolTable->add($model);

        $this->plugin->processSymbols($symbolTable, $this->tempDir);

        $this->assertEquals('model', $model->getMetadataValue('framework_type'));
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
