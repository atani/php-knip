<?php
/**
 * Configuration Loader
 *
 * Loads and merges configuration from various sources
 */

namespace PhpKnip\Config;

/**
 * Loads configuration from files with fallback to defaults
 */
class ConfigLoader
{
    /**
     * Default configuration values
     *
     * @var array
     */
    private static $defaults = array(
        'php_version' => 'auto',
        'encoding' => 'auto',
        'entry_points' => array(),
        'include' => array(
            'src/**/*.php',
            'app/**/*.php',
            'lib/**/*.php',
        ),
        'ignore' => array(
            'paths' => array(
                'vendor/**',
                'tests/**',
            ),
            'patterns' => array(),
            'symbols' => array(),
        ),
        'framework' => 'auto',
        'plugins' => array(),
        'rules' => array(
            'unused-files' => 'error',
            'unused-classes' => 'error',
            'unused-methods' => 'warning',
            'unused-functions' => 'error',
            'unused-constants' => 'warning',
            'unused-traits' => 'error',
            'unused-interfaces' => 'warning',
            'unused-dependencies' => 'error',
            'unused-use-statements' => 'warning',
            'unused-variables' => 'info',
            'unused-parameters' => 'off',
        ),
        'output' => array(
            'format' => 'text',
            'colors' => true,
            'show_progress' => true,
        ),
        'cache' => array(
            'enabled' => true,
            'directory' => '.php-knip-cache',
        ),
        'strict' => false,
        'parallel' => 1,
    );

    /**
     * Load configuration from file
     *
     * @param string $configFile Path to configuration file
     * @param string $basePath   Base path for the project
     *
     * @return array Configuration array
     */
    public function load($configFile, $basePath = '.')
    {
        $config = self::$defaults;

        // Try to load project config file
        $projectConfig = $this->loadConfigFile($configFile, $basePath);
        if ($projectConfig !== null) {
            $config = $this->mergeConfig($config, $projectConfig);
        }

        // Try to load home directory config
        $homeConfig = $this->loadHomeConfig();
        if ($homeConfig !== null) {
            // Home config has lower priority than project config
            $config = $this->mergeConfig($homeConfig, $config);
        }

        return $config;
    }

    /**
     * Load configuration file
     *
     * @param string $configFile Path to configuration file
     * @param string $basePath   Base path for the project
     *
     * @return array|null Configuration array or null if not found
     */
    private function loadConfigFile($configFile, $basePath)
    {
        // Try absolute path first
        if (file_exists($configFile)) {
            return $this->parseJsonFile($configFile);
        }

        // Try relative to base path
        $relativePath = rtrim($basePath, '/') . '/' . $configFile;
        if (file_exists($relativePath)) {
            return $this->parseJsonFile($relativePath);
        }

        return null;
    }

    /**
     * Load home directory configuration
     *
     * @return array|null Configuration array or null if not found
     */
    private function loadHomeConfig()
    {
        $homeDir = $this->getHomeDirectory();
        if ($homeDir === null) {
            return null;
        }

        $configPath = $homeDir . '/.php-knip/config.json';
        if (file_exists($configPath)) {
            return $this->parseJsonFile($configPath);
        }

        return null;
    }

    /**
     * Get user's home directory
     *
     * @return string|null Home directory path or null
     */
    private function getHomeDirectory()
    {
        // Try environment variables
        $home = getenv('HOME');
        if ($home !== false && is_dir($home)) {
            return $home;
        }

        // Windows
        $homeDrive = getenv('HOMEDRIVE');
        $homePath = getenv('HOMEPATH');
        if ($homeDrive !== false && $homePath !== false) {
            $home = $homeDrive . $homePath;
            if (is_dir($home)) {
                return $home;
            }
        }

        return null;
    }

    /**
     * Parse JSON configuration file
     *
     * @param string $filePath Path to JSON file
     *
     * @return array|null Parsed configuration or null on error
     */
    private function parseJsonFile($filePath)
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }

    /**
     * Deep merge two configuration arrays
     *
     * @param array $base     Base configuration
     * @param array $override Override configuration
     *
     * @return array Merged configuration
     */
    private function mergeConfig(array $base, array $override)
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                // Check if it's an associative array (config object) vs indexed array (list)
                if ($this->isAssociativeArray($value) && $this->isAssociativeArray($base[$key])) {
                    $base[$key] = $this->mergeConfig($base[$key], $value);
                } else {
                    // For indexed arrays (lists), replace entirely
                    $base[$key] = $value;
                }
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * Check if array is associative
     *
     * @param array $array Array to check
     *
     * @return bool True if associative
     */
    private function isAssociativeArray(array $array)
    {
        if (empty($array)) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Get default configuration
     *
     * @return array Default configuration
     */
    public static function getDefaults()
    {
        return self::$defaults;
    }
}
