<?php
/**
 * Composer Lock Parser
 *
 * Parses composer.lock files to extract installed package information
 */

namespace PhpKnip\Composer;

/**
 * Parser for composer.lock files
 */
class ComposerLockParser
{
    /**
     * @var array Parsed data
     */
    private $data = array();

    /**
     * @var array<string, array> Indexed packages by name
     */
    private $packageIndex = array();

    /**
     * @var array<string, array> Indexed dev packages by name
     */
    private $devPackageIndex = array();

    /**
     * Parse a composer.lock file
     *
     * @param string $path Path to composer.lock
     *
     * @return $this
     *
     * @throws \RuntimeException If file cannot be read or parsed
     */
    public function parse($path)
    {
        if (!file_exists($path)) {
            throw new \RuntimeException(sprintf('File not found: %s', $path));
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException(sprintf('Cannot read file: %s', $path));
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(sprintf(
                'Invalid JSON in %s: %s',
                $path,
                json_last_error_msg()
            ));
        }

        $this->data = $data;
        $this->buildIndex();

        return $this;
    }

    /**
     * Parse from array data
     *
     * @param array $data Lock file data
     *
     * @return $this
     */
    public function parseArray(array $data)
    {
        $this->data = $data;
        $this->buildIndex();
        return $this;
    }

    /**
     * Build package index for fast lookup
     */
    private function buildIndex()
    {
        $this->packageIndex = array();
        $this->devPackageIndex = array();

        // Index regular packages
        if (isset($this->data['packages'])) {
            foreach ($this->data['packages'] as $package) {
                if (isset($package['name'])) {
                    $this->packageIndex[$package['name']] = $package;
                }
            }
        }

        // Index dev packages
        if (isset($this->data['packages-dev'])) {
            foreach ($this->data['packages-dev'] as $package) {
                if (isset($package['name'])) {
                    $this->devPackageIndex[$package['name']] = $package;
                }
            }
        }
    }

    /**
     * Get all installed packages (non-dev)
     *
     * @return array<array>
     */
    public function getPackages()
    {
        return isset($this->data['packages']) ? $this->data['packages'] : array();
    }

    /**
     * Get all installed dev packages
     *
     * @return array<array>
     */
    public function getDevPackages()
    {
        return isset($this->data['packages-dev']) ? $this->data['packages-dev'] : array();
    }

    /**
     * Get all installed packages (including dev)
     *
     * @param bool $includeDev Include dev packages
     *
     * @return array<array>
     */
    public function getAllPackages($includeDev = true)
    {
        $packages = $this->getPackages();

        if ($includeDev) {
            $packages = array_merge($packages, $this->getDevPackages());
        }

        return $packages;
    }

    /**
     * Get package by name
     *
     * @param string $name Package name
     *
     * @return array|null Package data or null if not found
     */
    public function getPackage($name)
    {
        if (isset($this->packageIndex[$name])) {
            return $this->packageIndex[$name];
        }

        if (isset($this->devPackageIndex[$name])) {
            return $this->devPackageIndex[$name];
        }

        return null;
    }

    /**
     * Check if package is installed
     *
     * @param string $name Package name
     *
     * @return bool
     */
    public function hasPackage($name)
    {
        return isset($this->packageIndex[$name]) || isset($this->devPackageIndex[$name]);
    }

    /**
     * Check if package is a dev dependency
     *
     * @param string $name Package name
     *
     * @return bool
     */
    public function isDevPackage($name)
    {
        return isset($this->devPackageIndex[$name]);
    }

    /**
     * Get package version
     *
     * @param string $name Package name
     *
     * @return string|null
     */
    public function getPackageVersion($name)
    {
        $package = $this->getPackage($name);
        return $package !== null && isset($package['version']) ? $package['version'] : null;
    }

    /**
     * Get package autoload configuration
     *
     * @param string $name Package name
     *
     * @return array
     */
    public function getPackageAutoload($name)
    {
        $package = $this->getPackage($name);
        return $package !== null && isset($package['autoload']) ? $package['autoload'] : array();
    }

    /**
     * Get package PSR-4 autoload
     *
     * @param string $name Package name
     *
     * @return array<string, string|array>
     */
    public function getPackagePsr4($name)
    {
        $autoload = $this->getPackageAutoload($name);
        return isset($autoload['psr-4']) ? $autoload['psr-4'] : array();
    }

    /**
     * Get package PSR-0 autoload
     *
     * @param string $name Package name
     *
     * @return array<string, string|array>
     */
    public function getPackagePsr0($name)
    {
        $autoload = $this->getPackageAutoload($name);
        return isset($autoload['psr-0']) ? $autoload['psr-0'] : array();
    }

    /**
     * Get all namespaces provided by a package
     *
     * @param string $name Package name
     *
     * @return array<string> Namespace prefixes
     */
    public function getPackageNamespaces($name)
    {
        $namespaces = array();

        $psr4 = $this->getPackagePsr4($name);
        foreach ($psr4 as $namespace => $paths) {
            $namespaces[] = rtrim($namespace, '\\');
        }

        $psr0 = $this->getPackagePsr0($name);
        foreach ($psr0 as $namespace => $paths) {
            $namespaces[] = rtrim($namespace, '\\');
        }

        return array_unique($namespaces);
    }

    /**
     * Get all package names
     *
     * @param bool $includeDev Include dev packages
     *
     * @return array<string>
     */
    public function getPackageNames($includeDev = true)
    {
        $names = array_keys($this->packageIndex);

        if ($includeDev) {
            $names = array_merge($names, array_keys($this->devPackageIndex));
        }

        return array_unique($names);
    }

    /**
     * Build namespace to package mapping
     *
     * @param bool $includeDev Include dev packages
     *
     * @return array<string, string> Namespace prefix => package name
     */
    public function buildNamespaceMap($includeDev = true)
    {
        $map = array();

        foreach ($this->getAllPackages($includeDev) as $package) {
            if (!isset($package['name'])) {
                continue;
            }

            $packageName = $package['name'];

            // PSR-4
            if (isset($package['autoload']['psr-4'])) {
                foreach ($package['autoload']['psr-4'] as $namespace => $paths) {
                    $namespace = rtrim($namespace, '\\');
                    if ($namespace !== '') {
                        $map[$namespace] = $packageName;
                    }
                }
            }

            // PSR-0
            if (isset($package['autoload']['psr-0'])) {
                foreach ($package['autoload']['psr-0'] as $namespace => $paths) {
                    $namespace = rtrim($namespace, '\\');
                    if ($namespace !== '') {
                        $map[$namespace] = $packageName;
                    }
                }
            }
        }

        // Sort by namespace length descending for longest match first
        uksort($map, function ($a, $b) {
            return strlen($b) - strlen($a);
        });

        return $map;
    }

    /**
     * Find package that provides a namespace
     *
     * @param string $namespace Namespace to lookup
     * @param bool $includeDev Include dev packages
     *
     * @return string|null Package name or null if not found
     */
    public function findPackageByNamespace($namespace, $includeDev = true)
    {
        $map = $this->buildNamespaceMap($includeDev);

        foreach ($map as $prefix => $packageName) {
            if ($namespace === $prefix || strpos($namespace, $prefix . '\\') === 0) {
                return $packageName;
            }
        }

        return null;
    }

    /**
     * Get content hash
     *
     * @return string|null
     */
    public function getContentHash()
    {
        return isset($this->data['content-hash']) ? $this->data['content-hash'] : null;
    }

    /**
     * Get raw data
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }
}
