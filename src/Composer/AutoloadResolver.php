<?php
/**
 * Autoload Resolver
 *
 * Resolves class/function names to their source packages using Composer autoload rules
 */

namespace PhpKnip\Composer;

/**
 * Resolves symbols to packages using autoload configuration
 */
class AutoloadResolver
{
    /**
     * @var array<string, string> Namespace prefix => package name (sorted by length desc)
     */
    private $namespaceMap = array();

    /**
     * @var array<string, string> Class name => package name
     */
    private $classmapIndex = array();

    /**
     * @var array<string, string> Function name => package name
     */
    private $functionMap = array();

    /**
     * @var string Project's own namespace prefix (to exclude from dependency tracking)
     */
    private $projectNamespace;

    /**
     * @var string|null Project name
     */
    private $projectName;

    /**
     * Build resolver from composer data
     *
     * @param ComposerJsonParser $composerJson composer.json parser
     * @param ComposerLockParser $composerLock composer.lock parser
     *
     * @return $this
     */
    public function build(ComposerJsonParser $composerJson, ComposerLockParser $composerLock)
    {
        $this->projectName = $composerJson->getName();

        // Build namespace map from installed packages
        $this->namespaceMap = $composerLock->buildNamespaceMap(true);

        // Add project's own namespaces
        $this->addProjectNamespaces($composerJson);

        // Build classmap index if needed
        $this->buildClassmapIndex($composerLock);

        return $this;
    }

    /**
     * Add project's own namespaces
     *
     * @param ComposerJsonParser $composerJson composer.json parser
     */
    private function addProjectNamespaces(ComposerJsonParser $composerJson)
    {
        $projectName = $composerJson->getName() ?: '(project)';

        // PSR-4
        foreach ($composerJson->getPsr4Autoload() as $namespace => $paths) {
            $namespace = rtrim($namespace, '\\');
            if ($namespace !== '') {
                $this->namespaceMap[$namespace] = $projectName;
                if ($this->projectNamespace === null) {
                    $this->projectNamespace = $namespace;
                }
            }
        }

        // PSR-0
        foreach ($composerJson->getPsr0Autoload() as $namespace => $paths) {
            $namespace = rtrim($namespace, '\\');
            if ($namespace !== '') {
                $this->namespaceMap[$namespace] = $projectName;
                if ($this->projectNamespace === null) {
                    $this->projectNamespace = $namespace;
                }
            }
        }

        // Re-sort by length
        uksort($this->namespaceMap, function ($a, $b) {
            return strlen($b) - strlen($a);
        });
    }

    /**
     * Build classmap index from packages
     *
     * @param ComposerLockParser $composerLock composer.lock parser
     */
    private function buildClassmapIndex(ComposerLockParser $composerLock)
    {
        // Note: Full classmap building requires reading vendor/composer/autoload_classmap.php
        // For now, we rely on namespace-based resolution
        $this->classmapIndex = array();
    }

    /**
     * Resolve a class/interface/trait name to its package
     *
     * @param string $className Fully qualified class name
     *
     * @return string|null Package name or null if not found/is project code
     */
    public function resolveClass($className)
    {
        // Remove leading backslash
        $className = ltrim($className, '\\');

        // Check classmap first
        if (isset($this->classmapIndex[$className])) {
            return $this->classmapIndex[$className];
        }

        // Check namespace map
        foreach ($this->namespaceMap as $prefix => $packageName) {
            if ($className === $prefix || strpos($className, $prefix . '\\') === 0) {
                return $packageName;
            }
        }

        return null;
    }

    /**
     * Resolve a function name to its package
     *
     * @param string $functionName Fully qualified function name
     *
     * @return string|null Package name or null if not found
     */
    public function resolveFunction($functionName)
    {
        // Remove leading backslash
        $functionName = ltrim($functionName, '\\');

        // Check function map
        if (isset($this->functionMap[$functionName])) {
            return $this->functionMap[$functionName];
        }

        // Try namespace-based resolution
        $pos = strrpos($functionName, '\\');
        if ($pos !== false) {
            $namespace = substr($functionName, 0, $pos);
            foreach ($this->namespaceMap as $prefix => $packageName) {
                if ($namespace === $prefix || strpos($namespace, $prefix . '\\') === 0) {
                    return $packageName;
                }
            }
        }

        return null;
    }

    /**
     * Check if a class belongs to the project (not a dependency)
     *
     * @param string $className Class name
     *
     * @return bool
     */
    public function isProjectClass($className)
    {
        $package = $this->resolveClass($className);

        if ($package === null) {
            return false;
        }

        return $package === $this->projectName || $package === '(project)';
    }

    /**
     * Check if a class belongs to an external dependency
     *
     * @param string $className Class name
     *
     * @return bool
     */
    public function isDependencyClass($className)
    {
        $package = $this->resolveClass($className);

        if ($package === null) {
            return false;
        }

        return $package !== $this->projectName && $package !== '(project)';
    }

    /**
     * Get all packages that are referenced by a set of class names
     *
     * @param array<string> $classNames Class names
     *
     * @return array<string> Package names
     */
    public function resolveUsedPackages(array $classNames)
    {
        $packages = array();

        foreach ($classNames as $className) {
            $package = $this->resolveClass($className);
            if ($package !== null && $package !== $this->projectName && $package !== '(project)') {
                $packages[$package] = true;
            }
        }

        return array_keys($packages);
    }

    /**
     * Get namespace map
     *
     * @return array<string, string>
     */
    public function getNamespaceMap()
    {
        return $this->namespaceMap;
    }

    /**
     * Set namespace map directly
     *
     * @param array<string, string> $map Namespace map
     *
     * @return $this
     */
    public function setNamespaceMap(array $map)
    {
        $this->namespaceMap = $map;
        return $this;
    }

    /**
     * Get project namespace
     *
     * @return string|null
     */
    public function getProjectNamespace()
    {
        return $this->projectNamespace;
    }

    /**
     * Set project namespace
     *
     * @param string $namespace Project namespace
     *
     * @return $this
     */
    public function setProjectNamespace($namespace)
    {
        $this->projectNamespace = $namespace;
        return $this;
    }

    /**
     * Get project name
     *
     * @return string|null
     */
    public function getProjectName()
    {
        return $this->projectName;
    }

    /**
     * Set project name
     *
     * @param string $name Project name
     *
     * @return $this
     */
    public function setProjectName($name)
    {
        $this->projectName = $name;
        return $this;
    }

    /**
     * Add a classmap entry
     *
     * @param string $className Class name
     * @param string $packageName Package name
     *
     * @return $this
     */
    public function addClassmapEntry($className, $packageName)
    {
        $this->classmapIndex[$className] = $packageName;
        return $this;
    }

    /**
     * Load classmap from Composer's generated autoload file
     *
     * @param string $vendorDir Vendor directory path
     *
     * @return $this
     */
    public function loadClassmap($vendorDir)
    {
        $classmapFile = $vendorDir . '/composer/autoload_classmap.php';

        if (file_exists($classmapFile)) {
            $classmap = include $classmapFile;

            if (is_array($classmap)) {
                foreach ($classmap as $className => $filePath) {
                    // Determine package from file path
                    $package = $this->packageFromPath($filePath, $vendorDir);
                    if ($package !== null) {
                        $this->classmapIndex[$className] = $package;
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Determine package name from file path
     *
     * @param string $filePath File path
     * @param string $vendorDir Vendor directory
     *
     * @return string|null Package name
     */
    private function packageFromPath($filePath, $vendorDir)
    {
        $vendorDir = rtrim($vendorDir, '/\\');
        $relativePath = str_replace($vendorDir . DIRECTORY_SEPARATOR, '', $filePath);

        // Package path format: vendor/package/...
        $parts = explode(DIRECTORY_SEPARATOR, $relativePath);
        if (count($parts) >= 2) {
            return $parts[0] . '/' . $parts[1];
        }

        return null;
    }
}
