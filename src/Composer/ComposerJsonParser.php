<?php
/**
 * Composer JSON Parser
 *
 * Parses composer.json files to extract dependency and autoload information
 */

namespace PhpKnip\Composer;

/**
 * Parser for composer.json files
 */
class ComposerJsonParser
{
    /**
     * @var array Parsed data
     */
    private $data = array();

    /**
     * @var string|null Base directory
     */
    private $baseDir;

    /**
     * Parse a composer.json file
     *
     * @param string $path Path to composer.json
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
        $this->baseDir = dirname($path);

        return $this;
    }

    /**
     * Parse from array data
     *
     * @param array $data Composer data
     * @param string|null $baseDir Base directory
     *
     * @return $this
     */
    public function parseArray(array $data, $baseDir = null)
    {
        $this->data = $data;
        $this->baseDir = $baseDir;
        return $this;
    }

    /**
     * Get package name
     *
     * @return string|null
     */
    public function getName()
    {
        return isset($this->data['name']) ? $this->data['name'] : null;
    }

    /**
     * Get all require dependencies
     *
     * @return array<string, string> Package name => version constraint
     */
    public function getRequire()
    {
        return isset($this->data['require']) ? $this->data['require'] : array();
    }

    /**
     * Get all require-dev dependencies
     *
     * @return array<string, string> Package name => version constraint
     */
    public function getRequireDev()
    {
        return isset($this->data['require-dev']) ? $this->data['require-dev'] : array();
    }

    /**
     * Get all dependencies (require + require-dev)
     *
     * @param bool $includeDev Include dev dependencies
     *
     * @return array<string, string>
     */
    public function getAllDependencies($includeDev = true)
    {
        $deps = $this->getRequire();

        if ($includeDev) {
            $deps = array_merge($deps, $this->getRequireDev());
        }

        return $deps;
    }

    /**
     * Get PSR-4 autoload mappings
     *
     * @return array<string, string|array> Namespace prefix => path(s)
     */
    public function getPsr4Autoload()
    {
        if (!isset($this->data['autoload']['psr-4'])) {
            return array();
        }

        return $this->data['autoload']['psr-4'];
    }

    /**
     * Get PSR-0 autoload mappings
     *
     * @return array<string, string|array> Namespace prefix => path(s)
     */
    public function getPsr0Autoload()
    {
        if (!isset($this->data['autoload']['psr-0'])) {
            return array();
        }

        return $this->data['autoload']['psr-0'];
    }

    /**
     * Get classmap autoload paths
     *
     * @return array<string>
     */
    public function getClassmapAutoload()
    {
        if (!isset($this->data['autoload']['classmap'])) {
            return array();
        }

        return $this->data['autoload']['classmap'];
    }

    /**
     * Get files autoload paths
     *
     * @return array<string>
     */
    public function getFilesAutoload()
    {
        if (!isset($this->data['autoload']['files'])) {
            return array();
        }

        return $this->data['autoload']['files'];
    }

    /**
     * Get all autoload configuration
     *
     * @return array
     */
    public function getAutoload()
    {
        return isset($this->data['autoload']) ? $this->data['autoload'] : array();
    }

    /**
     * Get dev autoload configuration
     *
     * @return array
     */
    public function getAutoloadDev()
    {
        return isset($this->data['autoload-dev']) ? $this->data['autoload-dev'] : array();
    }

    /**
     * Check if a package is required
     *
     * @param string $packageName Package name
     *
     * @return bool
     */
    public function hasRequire($packageName)
    {
        $deps = $this->getRequire();
        return isset($deps[$packageName]);
    }

    /**
     * Check if a package is required as dev dependency
     *
     * @param string $packageName Package name
     *
     * @return bool
     */
    public function hasRequireDev($packageName)
    {
        $deps = $this->getRequireDev();
        return isset($deps[$packageName]);
    }

    /**
     * Check if a package is required (either regular or dev)
     *
     * @param string $packageName Package name
     *
     * @return bool
     */
    public function hasDependency($packageName)
    {
        return $this->hasRequire($packageName) || $this->hasRequireDev($packageName);
    }

    /**
     * Get version constraint for a package
     *
     * @param string $packageName Package name
     *
     * @return string|null Version constraint or null if not found
     */
    public function getVersionConstraint($packageName)
    {
        $require = $this->getRequire();
        if (isset($require[$packageName])) {
            return $require[$packageName];
        }

        $requireDev = $this->getRequireDev();
        if (isset($requireDev[$packageName])) {
            return $requireDev[$packageName];
        }

        return null;
    }

    /**
     * Get PHP extension requirements
     *
     * @return array<string, string>
     */
    public function getExtensionRequirements()
    {
        $extensions = array();
        $require = $this->getRequire();

        foreach ($require as $package => $version) {
            if (strpos($package, 'ext-') === 0) {
                $extensions[$package] = $version;
            }
        }

        return $extensions;
    }

    /**
     * Get PHP version requirement
     *
     * @return string|null
     */
    public function getPhpVersionRequirement()
    {
        $require = $this->getRequire();
        return isset($require['php']) ? $require['php'] : null;
    }

    /**
     * Get package dependencies only (excluding PHP and extensions)
     *
     * @param bool $includeDev Include dev dependencies
     *
     * @return array<string, string>
     */
    public function getPackageDependencies($includeDev = true)
    {
        $all = $this->getAllDependencies($includeDev);
        $packages = array();

        foreach ($all as $name => $version) {
            // Skip PHP requirement
            if ($name === 'php') {
                continue;
            }

            // Skip extensions
            if (strpos($name, 'ext-') === 0) {
                continue;
            }

            // Skip platform packages
            if (strpos($name, 'lib-') === 0) {
                continue;
            }

            $packages[$name] = $version;
        }

        return $packages;
    }

    /**
     * Get base directory
     *
     * @return string|null
     */
    public function getBaseDir()
    {
        return $this->baseDir;
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

    /**
     * Get scripts
     *
     * @return array
     */
    public function getScripts()
    {
        return isset($this->data['scripts']) ? $this->data['scripts'] : array();
    }

    /**
     * Get extra configuration
     *
     * @return array
     */
    public function getExtra()
    {
        return isset($this->data['extra']) ? $this->data['extra'] : array();
    }
}
