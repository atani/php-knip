<?php
/**
 * Laravel Plugin
 *
 * Framework support for Laravel applications
 */

namespace PhpKnip\Plugin\Laravel;

use PhpKnip\Plugin\AbstractPlugin;
use PhpKnip\Resolver\SymbolTable;
use PhpKnip\Resolver\Reference;

/**
 * Laravel framework plugin
 *
 * Provides support for:
 * - Service Provider detection
 * - Facade recognition
 * - Controller/Model/Job/Event recognition
 * - Route file analysis
 * - Config file analysis
 */
class LaravelPlugin extends AbstractPlugin
{
    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'laravel';
    }

    /**
     * @inheritDoc
     */
    public function getDescription()
    {
        return 'Laravel framework support - recognizes Service Providers, Facades, Controllers, etc.';
    }

    /**
     * @inheritDoc
     */
    public function isApplicable($projectRoot, array $composerData)
    {
        // Check for artisan file (Laravel signature)
        if ($this->fileExists($projectRoot, 'artisan')) {
            return true;
        }

        // Check for laravel/framework dependency
        if ($this->hasComposerDependency('laravel/framework', $composerData)) {
            return true;
        }

        // Check for illuminate packages (Lumen or custom setups)
        $require = isset($composerData['require']) ? $composerData['require'] : array();
        foreach (array_keys($require) as $package) {
            if (strpos($package, 'illuminate/') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function getPriority()
    {
        return 100; // High priority for Laravel
    }

    /**
     * @inheritDoc
     */
    public function getIgnorePatterns()
    {
        return array(
            // Base Laravel classes that should never be flagged
            'App\\Http\\Kernel',
            'App\\Console\\Kernel',
            'App\\Exceptions\\Handler',
            'App\\Providers\\*',

            // Common Laravel conventions
            '*ServiceProvider',
            '*Facade',

            // Eloquent model methods (magic methods)
            '*\\boot',
            '*\\booted',
            '*\\creating',
            '*\\created',
            '*\\updating',
            '*\\updated',
            '*\\deleting',
            '*\\deleted',
            '*\\saving',
            '*\\saved',
            '*\\restoring',
            '*\\restored',
            '*\\replicating',
        );
    }

    /**
     * @inheritDoc
     */
    public function getIgnoreFilePatterns()
    {
        return array(
            // Laravel generated/cache files
            '**/storage/framework/**',
            '**/bootstrap/cache/**',
            '**/vendor/**',

            // Laravel config files (often contain class references as strings)
            '**/config/*.php',

            // Database migrations and seeds
            '**/database/migrations/**',
            '**/database/seeders/**',
            '**/database/seeds/**',
            '**/database/factories/**',
        );
    }

    /**
     * @inheritDoc
     */
    public function getEntryPoints($projectRoot)
    {
        $entryPoints = array();

        // Service Providers from config/app.php
        $entryPoints = array_merge($entryPoints, $this->getServiceProviders($projectRoot));

        // Controllers from routes
        $entryPoints = array_merge($entryPoints, $this->getControllerEntryPoints($projectRoot));

        // Console commands
        $entryPoints = array_merge($entryPoints, $this->getCommandEntryPoints($projectRoot));

        // Event listeners and subscribers
        $entryPoints = array_merge($entryPoints, $this->getEventEntryPoints($projectRoot));

        // Middleware
        $entryPoints = array_merge($entryPoints, $this->getMiddlewareEntryPoints($projectRoot));

        return array_unique($entryPoints);
    }

    /**
     * @inheritDoc
     */
    public function getAdditionalReferences($projectRoot)
    {
        $references = array();

        // Parse route files for controller references
        $references = array_merge($references, $this->parseRouteFiles($projectRoot));

        // Parse config files for class references
        $references = array_merge($references, $this->parseConfigFiles($projectRoot));

        return $references;
    }

    /**
     * @inheritDoc
     */
    public function processSymbols(SymbolTable $symbolTable, $projectRoot)
    {
        // Mark framework-specific symbols as used
        foreach ($symbolTable->getClasses() as $class) {
            $fqn = $class->getFullyQualifiedName();

            // Service Providers are always used
            if ($this->isServiceProvider($fqn)) {
                $class->setMetadata('framework_used', true);
                $class->setMetadata('framework_reason', 'Laravel Service Provider');
            }

            // Controllers are used via routes
            if ($this->isController($fqn)) {
                $class->setMetadata('framework_type', 'controller');
            }

            // Models with Eloquent relationships
            if ($this->isModel($fqn)) {
                $class->setMetadata('framework_type', 'model');
            }

            // Jobs, Events, Listeners
            if ($this->isJob($fqn) || $this->isEvent($fqn) || $this->isListener($fqn)) {
                $class->setMetadata('framework_type', 'async');
            }
        }
    }

    /**
     * Get service providers from config/app.php
     *
     * @param string $projectRoot Project root
     *
     * @return array<string>
     */
    private function getServiceProviders($projectRoot)
    {
        $providers = array();
        $configPath = $projectRoot . '/config/app.php';

        if (!file_exists($configPath)) {
            return $providers;
        }

        $content = file_get_contents($configPath);
        if ($content === false) {
            return $providers;
        }

        // Extract providers array
        // Look for patterns like: App\Providers\AppServiceProvider::class
        if (preg_match_all('/([A-Za-z0-9_\\\\]+)::class/', $content, $matches)) {
            foreach ($matches[1] as $className) {
                if (strpos($className, 'Provider') !== false) {
                    $providers[] = $className;
                }
            }
        }

        return $providers;
    }

    /**
     * Get controller entry points from route files
     *
     * @param string $projectRoot Project root
     *
     * @return array<string>
     */
    private function getControllerEntryPoints($projectRoot)
    {
        $controllers = array();

        // Find controllers in app/Http/Controllers
        $controllerDir = $projectRoot . '/app/Http/Controllers';
        if (is_dir($controllerDir)) {
            $files = $this->findPhpFiles($projectRoot, 'app/Http/Controllers');
            foreach ($files as $file) {
                $className = $this->extractClassName($file);
                if ($className !== null) {
                    $controllers[] = $className;
                }
            }
        }

        return $controllers;
    }

    /**
     * Get command entry points
     *
     * @param string $projectRoot Project root
     *
     * @return array<string>
     */
    private function getCommandEntryPoints($projectRoot)
    {
        $commands = array();

        $commandDir = $projectRoot . '/app/Console/Commands';
        if (is_dir($commandDir)) {
            $files = $this->findPhpFiles($projectRoot, 'app/Console/Commands');
            foreach ($files as $file) {
                $className = $this->extractClassName($file);
                if ($className !== null) {
                    $commands[] = $className;
                }
            }
        }

        return $commands;
    }

    /**
     * Get event-related entry points
     *
     * @param string $projectRoot Project root
     *
     * @return array<string>
     */
    private function getEventEntryPoints($projectRoot)
    {
        $events = array();

        // Events
        $eventDir = $projectRoot . '/app/Events';
        if (is_dir($eventDir)) {
            $files = $this->findPhpFiles($projectRoot, 'app/Events');
            foreach ($files as $file) {
                $className = $this->extractClassName($file);
                if ($className !== null) {
                    $events[] = $className;
                }
            }
        }

        // Listeners
        $listenerDir = $projectRoot . '/app/Listeners';
        if (is_dir($listenerDir)) {
            $files = $this->findPhpFiles($projectRoot, 'app/Listeners');
            foreach ($files as $file) {
                $className = $this->extractClassName($file);
                if ($className !== null) {
                    $events[] = $className;
                }
            }
        }

        return $events;
    }

    /**
     * Get middleware entry points
     *
     * @param string $projectRoot Project root
     *
     * @return array<string>
     */
    private function getMiddlewareEntryPoints($projectRoot)
    {
        $middleware = array();

        $middlewareDir = $projectRoot . '/app/Http/Middleware';
        if (is_dir($middlewareDir)) {
            $files = $this->findPhpFiles($projectRoot, 'app/Http/Middleware');
            foreach ($files as $file) {
                $className = $this->extractClassName($file);
                if ($className !== null) {
                    $middleware[] = $className;
                }
            }
        }

        return $middleware;
    }

    /**
     * Parse route files for references
     *
     * @param string $projectRoot Project root
     *
     * @return array<Reference>
     */
    private function parseRouteFiles($projectRoot)
    {
        $references = array();
        $routeDir = $projectRoot . '/routes';

        if (!is_dir($routeDir)) {
            return $references;
        }

        $routeFiles = array('web.php', 'api.php', 'console.php', 'channels.php');

        foreach ($routeFiles as $routeFile) {
            $path = $routeDir . '/' . $routeFile;
            if (!file_exists($path)) {
                continue;
            }

            $content = file_get_contents($path);
            if ($content === false) {
                continue;
            }

            // Extract controller references
            // Pattern: [Controller::class, 'method'] or 'Controller@method'
            if (preg_match_all('/([A-Za-z0-9_\\\\]+Controller)::class/', $content, $matches)) {
                foreach ($matches[1] as $controller) {
                    $ref = Reference::createClassString($controller, $path, 0);
                    $ref->setMetadata('source', 'route');
                    $references[] = $ref;
                }
            }

            // Old-style route definitions: 'Controller@method'
            if (preg_match_all('/[\'"]([A-Za-z0-9_\\\\]+Controller)@(\w+)[\'"]/', $content, $matches)) {
                foreach ($matches[1] as $controller) {
                    $ref = Reference::createClassString($controller, $path, 0);
                    $ref->setMetadata('source', 'route');
                    $references[] = $ref;
                }
            }
        }

        return $references;
    }

    /**
     * Parse config files for class references
     *
     * @param string $projectRoot Project root
     *
     * @return array<Reference>
     */
    private function parseConfigFiles($projectRoot)
    {
        $references = array();
        $configDir = $projectRoot . '/config';

        if (!is_dir($configDir)) {
            return $references;
        }

        $configFiles = glob($configDir . '/*.php');
        if ($configFiles === false) {
            return $references;
        }

        foreach ($configFiles as $configFile) {
            $content = file_get_contents($configFile);
            if ($content === false) {
                continue;
            }

            // Extract class references
            if (preg_match_all('/([A-Za-z0-9_\\\\]+)::class/', $content, $matches)) {
                foreach ($matches[1] as $className) {
                    // Skip Laravel core classes
                    if (strpos($className, 'Illuminate\\') === 0) {
                        continue;
                    }
                    $ref = Reference::createClassString($className, $configFile, 0);
                    $ref->setMetadata('source', 'config');
                    $references[] = $ref;
                }
            }
        }

        return $references;
    }

    /**
     * Check if class is a Service Provider
     *
     * @param string $fqn Class FQN
     *
     * @return bool
     */
    private function isServiceProvider($fqn)
    {
        return strpos($fqn, 'ServiceProvider') !== false
            || strpos($fqn, '\\Providers\\') !== false;
    }

    /**
     * Check if class is a Controller
     *
     * @param string $fqn Class FQN
     *
     * @return bool
     */
    private function isController($fqn)
    {
        return strpos($fqn, 'Controller') !== false
            || strpos($fqn, '\\Controllers\\') !== false;
    }

    /**
     * Check if class is an Eloquent Model
     *
     * @param string $fqn Class FQN
     *
     * @return bool
     */
    private function isModel($fqn)
    {
        return strpos($fqn, '\\Models\\') !== false;
    }

    /**
     * Check if class is a Job
     *
     * @param string $fqn Class FQN
     *
     * @return bool
     */
    private function isJob($fqn)
    {
        return strpos($fqn, '\\Jobs\\') !== false;
    }

    /**
     * Check if class is an Event
     *
     * @param string $fqn Class FQN
     *
     * @return bool
     */
    private function isEvent($fqn)
    {
        return strpos($fqn, '\\Events\\') !== false;
    }

    /**
     * Check if class is a Listener
     *
     * @param string $fqn Class FQN
     *
     * @return bool
     */
    private function isListener($fqn)
    {
        return strpos($fqn, '\\Listeners\\') !== false;
    }
}
