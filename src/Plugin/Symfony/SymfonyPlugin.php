<?php
/**
 * Symfony Plugin
 *
 * Framework support for Symfony applications
 */

namespace PhpKnip\Plugin\Symfony;

use PhpKnip\Plugin\AbstractPlugin;
use PhpKnip\Resolver\SymbolTable;
use PhpKnip\Resolver\Reference;

/**
 * Symfony framework plugin
 *
 * Provides support for:
 * - Controllers with route attributes/annotations
 * - Console Commands
 * - Event Subscribers and Listeners
 * - Form Types
 * - Twig Extensions
 * - Validators
 * - Security Voters and Authenticators
 * - Doctrine Entities and Repositories
 * - Service definitions from YAML/XML config
 * - Message Handlers
 */
class SymfonyPlugin extends AbstractPlugin
{
    /**
     * Symfony service tags that indicate framework usage
     *
     * @var array
     */
    private static $serviceTags = array(
        'controller.service_arguments',
        'console.command',
        'kernel.event_subscriber',
        'kernel.event_listener',
        'form.type',
        'twig.extension',
        'validator.constraint_validator',
        'security.voter',
        'doctrine.orm.entity_listener',
        'doctrine.event_listener',
        'doctrine.event_subscriber',
        'messenger.message_handler',
        'serializer.normalizer',
        'monolog.logger',
    );

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'symfony';
    }

    /**
     * @inheritDoc
     */
    public function getDescription()
    {
        return 'Symfony framework support - recognizes controllers, commands, services, and Doctrine entities';
    }

    /**
     * @inheritDoc
     */
    public function isApplicable($projectRoot, array $composerData)
    {
        // Check for bin/console (Symfony signature)
        if ($this->fileExists($projectRoot, 'bin/console')) {
            return true;
        }

        // Check for symfony.lock file
        if ($this->fileExists($projectRoot, 'symfony.lock')) {
            return true;
        }

        // Check for config/bundles.php (Symfony Flex)
        if ($this->fileExists($projectRoot, 'config/bundles.php')) {
            return true;
        }

        // Check for Symfony framework dependencies
        $symfonyPackages = array(
            'symfony/framework-bundle',
            'symfony/http-kernel',
            'symfony/console',
        );

        foreach ($symfonyPackages as $package) {
            if ($this->hasComposerDependency($package, $composerData)) {
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
        return 100; // High priority for Symfony
    }

    /**
     * @inheritDoc
     */
    public function getIgnorePatterns()
    {
        return array(
            // Kernel class
            'App\\Kernel',
            '*\\Kernel',

            // Controllers
            '*Controller',
            '*\\Controller\\*',

            // Commands
            '*Command',
            '*\\Command\\*',

            // Event handling
            '*Subscriber',
            '*Listener',
            '*\\EventSubscriber\\*',
            '*\\EventListener\\*',

            // Forms
            '*Type',
            '*\\Form\\*',

            // Twig
            '*Extension',
            '*\\Twig\\*',

            // Validators
            '*Validator',
            '*\\Validator\\*',

            // Security
            '*Voter',
            '*Authenticator',
            '*\\Security\\*',

            // Doctrine
            '*Repository',
            '*\\Repository\\*',
            '*\\Entity\\*',

            // Message handlers
            '*Handler',
            '*\\MessageHandler\\*',
            '*\\Handler\\*',

            // Normalizers/Serializers
            '*Normalizer',
            '*\\Serializer\\*',

            // Data fixtures
            '*Fixture',
            '*\\DataFixtures\\*',

            // Migrations
            '*\\Migrations\\*',

            // Common Symfony method patterns
            '*\\configure',
            '*\\execute',
            '*\\getSubscribedEvents',
            '*\\buildForm',
            '*\\configureOptions',
            '*\\getFunctions',
            '*\\getFilters',
            '*\\validate',
            '*\\supports',
            '*\\voteOnAttribute',
        );
    }

    /**
     * @inheritDoc
     */
    public function getIgnoreFilePatterns()
    {
        return array(
            // Symfony cache and logs
            '**/var/cache/**',
            '**/var/log/**',

            // Vendor
            '**/vendor/**',

            // Config cache
            '**/config/secrets/**',

            // Generated files
            '**/src/Kernel.php',

            // Migrations (auto-generated)
            '**/migrations/**',
            '**/src/Migrations/**',

            // Test fixtures
            '**/tests/Fixtures/**',
        );
    }

    /**
     * @inheritDoc
     */
    public function getEntryPoints($projectRoot)
    {
        $entryPoints = array();

        // Kernel class
        $entryPoints = array_merge($entryPoints, $this->getKernelEntryPoints($projectRoot));

        // Controllers
        $entryPoints = array_merge($entryPoints, $this->getControllerEntryPoints($projectRoot));

        // Commands
        $entryPoints = array_merge($entryPoints, $this->getCommandEntryPoints($projectRoot));

        // Event Subscribers
        $entryPoints = array_merge($entryPoints, $this->getEventSubscriberEntryPoints($projectRoot));

        // Bundles
        $entryPoints = array_merge($entryPoints, $this->getBundleEntryPoints($projectRoot));

        return array_unique($entryPoints);
    }

    /**
     * @inheritDoc
     */
    public function getAdditionalReferences($projectRoot)
    {
        $references = array();

        // Parse bundles.php for bundle references
        $references = array_merge($references, $this->parseBundlesConfig($projectRoot));

        // Parse services.yaml for class references
        $references = array_merge($references, $this->parseServicesConfig($projectRoot));

        // Parse routes for controller references
        $references = array_merge($references, $this->parseRoutesConfig($projectRoot));

        // Parse doctrine.yaml for entity/repository references
        $references = array_merge($references, $this->parseDoctrineConfig($projectRoot));

        return $references;
    }

    /**
     * @inheritDoc
     */
    public function processSymbols(SymbolTable $symbolTable, $projectRoot)
    {
        foreach ($symbolTable->getClasses() as $class) {
            $fqn = $class->getFullyQualifiedName();
            $name = $class->getName();

            // Controllers are always used
            if ($this->isController($name, $fqn)) {
                $class->setMetadata('framework_used', true);
                $class->setMetadata('framework_reason', 'Symfony Controller');
            }

            // Commands are always used
            if ($this->isCommand($name, $fqn)) {
                $class->setMetadata('framework_used', true);
                $class->setMetadata('framework_reason', 'Symfony Command');
            }

            // Event Subscribers are always used
            if ($this->isEventSubscriber($name, $fqn)) {
                $class->setMetadata('framework_used', true);
                $class->setMetadata('framework_reason', 'Symfony Event Subscriber');
            }

            // Form Types
            if ($this->isFormType($name, $fqn)) {
                $class->setMetadata('framework_type', 'form_type');
            }

            // Twig Extensions
            if ($this->isTwigExtension($name, $fqn)) {
                $class->setMetadata('framework_type', 'twig_extension');
            }

            // Doctrine Entities
            if ($this->isEntity($name, $fqn)) {
                $class->setMetadata('framework_type', 'doctrine_entity');
            }

            // Doctrine Repositories
            if ($this->isRepository($name, $fqn)) {
                $class->setMetadata('framework_type', 'doctrine_repository');
            }

            // Message Handlers
            if ($this->isMessageHandler($name, $fqn)) {
                $class->setMetadata('framework_type', 'message_handler');
            }

            // Security Voters
            if ($this->isVoter($name, $fqn)) {
                $class->setMetadata('framework_type', 'security_voter');
            }
        }
    }

    /**
     * Get Kernel entry points
     *
     * @param string $projectRoot Project root
     *
     * @return array
     */
    private function getKernelEntryPoints($projectRoot)
    {
        $entryPoints = array();

        // Standard Kernel location
        $kernelFile = $projectRoot . '/src/Kernel.php';
        if (file_exists($kernelFile)) {
            $className = $this->extractClassName($kernelFile);
            if ($className !== null) {
                $entryPoints[] = $className;
            }
        }

        return $entryPoints;
    }

    /**
     * Get Controller entry points
     *
     * @param string $projectRoot Project root
     *
     * @return array
     */
    private function getControllerEntryPoints($projectRoot)
    {
        $entryPoints = array();
        $controllerDir = $projectRoot . '/src/Controller';

        if (!is_dir($controllerDir)) {
            return $entryPoints;
        }

        $files = $this->findPhpFiles($projectRoot, 'src/Controller');
        foreach ($files as $file) {
            $className = $this->extractClassName($file);
            if ($className !== null) {
                $entryPoints[] = $className;
            }
        }

        return $entryPoints;
    }

    /**
     * Get Command entry points
     *
     * @param string $projectRoot Project root
     *
     * @return array
     */
    private function getCommandEntryPoints($projectRoot)
    {
        $entryPoints = array();
        $commandDir = $projectRoot . '/src/Command';

        if (!is_dir($commandDir)) {
            return $entryPoints;
        }

        $files = $this->findPhpFiles($projectRoot, 'src/Command');
        foreach ($files as $file) {
            $className = $this->extractClassName($file);
            if ($className !== null) {
                $entryPoints[] = $className;
            }
        }

        return $entryPoints;
    }

    /**
     * Get Event Subscriber entry points
     *
     * @param string $projectRoot Project root
     *
     * @return array
     */
    private function getEventSubscriberEntryPoints($projectRoot)
    {
        $entryPoints = array();

        $dirs = array(
            'src/EventSubscriber',
            'src/EventListener',
        );

        foreach ($dirs as $dir) {
            $fullDir = $projectRoot . '/' . $dir;
            if (!is_dir($fullDir)) {
                continue;
            }

            $files = $this->findPhpFiles($projectRoot, $dir);
            foreach ($files as $file) {
                $className = $this->extractClassName($file);
                if ($className !== null) {
                    $entryPoints[] = $className;
                }
            }
        }

        return $entryPoints;
    }

    /**
     * Get Bundle entry points from config/bundles.php
     *
     * @param string $projectRoot Project root
     *
     * @return array
     */
    private function getBundleEntryPoints($projectRoot)
    {
        $entryPoints = array();
        $bundlesFile = $projectRoot . '/config/bundles.php';

        if (!file_exists($bundlesFile)) {
            return $entryPoints;
        }

        $content = file_get_contents($bundlesFile);
        if ($content === false) {
            return $entryPoints;
        }

        // Extract bundle class references
        if (preg_match_all('/([A-Za-z0-9_\\\\]+Bundle)::class/', $content, $matches)) {
            foreach ($matches[1] as $bundleClass) {
                // Only include app bundles, not vendor bundles
                if (strpos($bundleClass, 'Symfony\\') === 0 ||
                    strpos($bundleClass, 'Doctrine\\') === 0 ||
                    strpos($bundleClass, 'Twig\\') === 0 ||
                    strpos($bundleClass, 'Sensio\\') === 0) {
                    continue;
                }
                $entryPoints[] = $bundleClass;
            }
        }

        return $entryPoints;
    }

    /**
     * Parse bundles.php for references
     *
     * @param string $projectRoot Project root
     *
     * @return array<Reference>
     */
    private function parseBundlesConfig($projectRoot)
    {
        $references = array();
        $bundlesFile = $projectRoot . '/config/bundles.php';

        if (!file_exists($bundlesFile)) {
            return $references;
        }

        $content = file_get_contents($bundlesFile);
        if ($content === false) {
            return $references;
        }

        // Extract all class references
        if (preg_match_all('/([A-Za-z0-9_\\\\]+)::class/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $match) {
                $className = $match[0];
                $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;

                // Skip Symfony core bundles
                if (strpos($className, 'Symfony\\') === 0) {
                    continue;
                }

                $ref = Reference::createClassString($className, $bundlesFile, $line);
                $ref->setMetadata('source', 'symfony_bundles');
                $references[] = $ref;
            }
        }

        return $references;
    }

    /**
     * Parse services.yaml for class references
     *
     * @param string $projectRoot Project root
     *
     * @return array<Reference>
     */
    private function parseServicesConfig($projectRoot)
    {
        $references = array();

        $configFiles = array(
            $projectRoot . '/config/services.yaml',
            $projectRoot . '/config/services.yml',
            $projectRoot . '/config/services.xml',
        );

        foreach ($configFiles as $configFile) {
            if (!file_exists($configFile)) {
                continue;
            }

            $content = file_get_contents($configFile);
            if ($content === false) {
                continue;
            }

            $refs = $this->parseYamlForClassReferences($content, $configFile);
            $references = array_merge($references, $refs);
        }

        // Also check config/services/ directory
        $servicesDir = $projectRoot . '/config/services';
        if (is_dir($servicesDir)) {
            $files = $this->globYamlFiles($servicesDir);
            foreach ($files as $file) {
                $content = file_get_contents($file);
                if ($content !== false) {
                    $refs = $this->parseYamlForClassReferences($content, $file);
                    $references = array_merge($references, $refs);
                }
            }
        }

        return $references;
    }

    /**
     * Parse YAML content for class references
     *
     * @param string $content YAML content
     * @param string $filePath File path
     *
     * @return array<Reference>
     */
    private function parseYamlForClassReferences($content, $filePath)
    {
        $references = array();

        // Pattern for class definitions in YAML
        // Matches: class: App\Service\MyService or App\Service\MyService:
        $patterns = array(
            '/class:\s*[\'"]?([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)[\'"]?/',
            '/^[\s]*([A-Za-z_\\\\][A-Za-z0-9_\\\\]+):\s*$/m',
            '/resource:\s*[\'"]?\.\.\/src\/([A-Za-z0-9_\/]+)\/[\'"]?/',
            '/arguments:\s*\[?\s*[\'"]?@([A-Za-z_\\\\][A-Za-z0-9_\\\\\.]*)[\'"]?/',
            '/factory:\s*\[[\'"]?([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)[\'"]?,/',
            '/parent:\s*[\'"]?([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)[\'"]?/',
        );

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[1] as $match) {
                    $className = $match[0];
                    $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;

                    // Skip service references (start with @) and non-class patterns
                    if (strpos($className, '@') === 0 ||
                        strpos($className, '..') === 0 ||
                        strpos($className, '%') !== false ||
                        strpos($className, '\\') === false) {
                        continue;
                    }

                    // Skip Symfony core classes
                    if (strpos($className, 'Symfony\\') === 0) {
                        continue;
                    }

                    $ref = Reference::createClassString($className, $filePath, $line);
                    $ref->setMetadata('source', 'symfony_services');
                    $references[] = $ref;
                }
            }
        }

        return $references;
    }

    /**
     * Parse routes configuration for controller references
     *
     * @param string $projectRoot Project root
     *
     * @return array<Reference>
     */
    private function parseRoutesConfig($projectRoot)
    {
        $references = array();

        $routeFiles = array(
            $projectRoot . '/config/routes.yaml',
            $projectRoot . '/config/routes.yml',
        );

        foreach ($routeFiles as $routeFile) {
            if (!file_exists($routeFile)) {
                continue;
            }

            $content = file_get_contents($routeFile);
            if ($content === false) {
                continue;
            }

            // Pattern for controller in routes
            // controller: App\Controller\HomeController::index
            if (preg_match_all('/controller:\s*[\'"]?([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)(?:::(\w+))?[\'"]?/', $content, $matches, PREG_OFFSET_CAPTURE)) {
                for ($i = 0; $i < count($matches[0]); $i++) {
                    $className = $matches[1][$i][0];
                    $line = substr_count(substr($content, 0, $matches[0][$i][1]), "\n") + 1;

                    $ref = Reference::createClassString($className, $routeFile, $line);
                    $ref->setMetadata('source', 'symfony_routes');
                    $references[] = $ref;

                    // If method is specified, also add method reference
                    if (!empty($matches[2][$i][0])) {
                        $methodName = $matches[2][$i][0];
                        $methodRef = Reference::createStaticCall($className, $methodName, $routeFile, $line);
                        $methodRef->setMetadata('source', 'symfony_routes');
                        $references[] = $methodRef;
                    }
                }
            }
        }

        // Check routes directory
        $routesDir = $projectRoot . '/config/routes';
        if (is_dir($routesDir)) {
            $files = $this->globYamlFiles($routesDir);
            foreach ($files as $file) {
                $content = file_get_contents($file);
                if ($content === false) {
                    continue;
                }

                if (preg_match_all('/controller:\s*[\'"]?([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)(?:::(\w+))?[\'"]?/', $content, $matches, PREG_OFFSET_CAPTURE)) {
                    for ($i = 0; $i < count($matches[0]); $i++) {
                        $className = $matches[1][$i][0];
                        $line = substr_count(substr($content, 0, $matches[0][$i][1]), "\n") + 1;

                        $ref = Reference::createClassString($className, $file, $line);
                        $ref->setMetadata('source', 'symfony_routes');
                        $references[] = $ref;
                    }
                }
            }
        }

        return $references;
    }

    /**
     * Parse Doctrine configuration for entity/repository references
     *
     * @param string $projectRoot Project root
     *
     * @return array<Reference>
     */
    private function parseDoctrineConfig($projectRoot)
    {
        $references = array();

        $doctrineFile = $projectRoot . '/config/packages/doctrine.yaml';
        if (!file_exists($doctrineFile)) {
            return $references;
        }

        $content = file_get_contents($doctrineFile);
        if ($content === false) {
            return $references;
        }

        // Extract entity and repository class references
        if (preg_match_all('/(?:entity|repository)_class:\s*[\'"]?([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)[\'"]?/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $match) {
                $className = $match[0];
                $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;

                $ref = Reference::createClassString($className, $doctrineFile, $line);
                $ref->setMetadata('source', 'symfony_doctrine');
                $references[] = $ref;
            }
        }

        return $references;
    }

    /**
     * Check if class is a Controller
     *
     * @param string $name Class short name
     * @param string $fqn Class FQN
     *
     * @return bool
     */
    private function isController($name, $fqn)
    {
        return strpos($name, 'Controller') !== false ||
            strpos($fqn, '\\Controller\\') !== false;
    }

    /**
     * Check if class is a Command
     *
     * @param string $name Class short name
     * @param string $fqn Class FQN
     *
     * @return bool
     */
    private function isCommand($name, $fqn)
    {
        return strpos($name, 'Command') !== false ||
            strpos($fqn, '\\Command\\') !== false;
    }

    /**
     * Check if class is an Event Subscriber
     *
     * @param string $name Class short name
     * @param string $fqn Class FQN
     *
     * @return bool
     */
    private function isEventSubscriber($name, $fqn)
    {
        return strpos($name, 'Subscriber') !== false ||
            strpos($name, 'Listener') !== false ||
            strpos($fqn, '\\EventSubscriber\\') !== false ||
            strpos($fqn, '\\EventListener\\') !== false;
    }

    /**
     * Check if class is a Form Type
     *
     * @param string $name Class short name
     * @param string $fqn Class FQN
     *
     * @return bool
     */
    private function isFormType($name, $fqn)
    {
        return strpos($name, 'Type') !== false &&
            strpos($fqn, '\\Form\\') !== false;
    }

    /**
     * Check if class is a Twig Extension
     *
     * @param string $name Class short name
     * @param string $fqn Class FQN
     *
     * @return bool
     */
    private function isTwigExtension($name, $fqn)
    {
        return strpos($name, 'Extension') !== false &&
            strpos($fqn, '\\Twig\\') !== false;
    }

    /**
     * Check if class is a Doctrine Entity
     *
     * @param string $name Class short name
     * @param string $fqn Class FQN
     *
     * @return bool
     */
    private function isEntity($name, $fqn)
    {
        return strpos($fqn, '\\Entity\\') !== false;
    }

    /**
     * Check if class is a Doctrine Repository
     *
     * @param string $name Class short name
     * @param string $fqn Class FQN
     *
     * @return bool
     */
    private function isRepository($name, $fqn)
    {
        return strpos($name, 'Repository') !== false ||
            strpos($fqn, '\\Repository\\') !== false;
    }

    /**
     * Check if class is a Message Handler
     *
     * @param string $name Class short name
     * @param string $fqn Class FQN
     *
     * @return bool
     */
    private function isMessageHandler($name, $fqn)
    {
        return strpos($name, 'Handler') !== false &&
            (strpos($fqn, '\\MessageHandler\\') !== false ||
             strpos($fqn, '\\Handler\\') !== false);
    }

    /**
     * Check if class is a Security Voter
     *
     * @param string $name Class short name
     * @param string $fqn Class FQN
     *
     * @return bool
     */
    private function isVoter($name, $fqn)
    {
        return strpos($name, 'Voter') !== false ||
            strpos($fqn, '\\Security\\') !== false;
    }

    /**
     * Find YAML files in a directory (cross-platform, no GLOB_BRACE)
     *
     * @param string $directory Directory path
     *
     * @return array<string>
     */
    private function globYamlFiles($directory)
    {
        $files = array();

        // Get .yaml files
        $yamlFiles = glob($directory . '/*.yaml');
        if ($yamlFiles !== false) {
            $files = array_merge($files, $yamlFiles);
        }

        // Get .yml files
        $ymlFiles = glob($directory . '/*.yml');
        if ($ymlFiles !== false) {
            $files = array_merge($files, $ymlFiles);
        }

        return $files;
    }
}
