<?php
/**
 * Abstract Plugin
 *
 * Base class for framework plugins with default implementations
 */

namespace PhpKnip\Plugin;

use PhpKnip\Resolver\SymbolTable;

/**
 * Abstract base class for framework plugins
 *
 * Provides sensible defaults for most plugin methods.
 * Plugins should extend this class and override methods as needed.
 */
abstract class AbstractPlugin implements PluginInterface
{
    /**
     * @var string Project root directory (set when plugin is activated)
     */
    protected $projectRoot;

    /**
     * @var array Composer data (set when plugin is activated)
     */
    protected $composerData = array();

    /**
     * @inheritDoc
     */
    public function getPriority()
    {
        return 0;
    }

    /**
     * @inheritDoc
     */
    public function getIgnorePatterns()
    {
        return array();
    }

    /**
     * @inheritDoc
     */
    public function getIgnoreFilePatterns()
    {
        return array();
    }

    /**
     * @inheritDoc
     */
    public function processSymbols(SymbolTable $symbolTable, $projectRoot)
    {
        // Default: no additional processing
    }

    /**
     * @inheritDoc
     */
    public function getAdditionalReferences($projectRoot)
    {
        return array();
    }

    /**
     * @inheritDoc
     */
    public function getEntryPoints($projectRoot)
    {
        return array();
    }

    /**
     * Check if a composer dependency exists
     *
     * @param string $package Package name (e.g., 'laravel/framework')
     * @param array $composerData Composer data
     *
     * @return bool
     */
    protected function hasComposerDependency($package, array $composerData)
    {
        $require = isset($composerData['require']) ? $composerData['require'] : array();
        $requireDev = isset($composerData['require-dev']) ? $composerData['require-dev'] : array();

        return isset($require[$package]) || isset($requireDev[$package]);
    }

    /**
     * Check if a file exists in the project
     *
     * @param string $projectRoot Project root directory
     * @param string $relativePath Relative path to check
     *
     * @return bool
     */
    protected function fileExists($projectRoot, $relativePath)
    {
        return file_exists($projectRoot . '/' . $relativePath);
    }

    /**
     * Check if a directory exists in the project
     *
     * @param string $projectRoot Project root directory
     * @param string $relativePath Relative path to check
     *
     * @return bool
     */
    protected function directoryExists($projectRoot, $relativePath)
    {
        return is_dir($projectRoot . '/' . $relativePath);
    }

    /**
     * Read JSON file from project
     *
     * @param string $projectRoot Project root directory
     * @param string $relativePath Relative path to JSON file
     *
     * @return array|null Parsed JSON or null if not found/invalid
     */
    protected function readJsonFile($projectRoot, $relativePath)
    {
        $path = $projectRoot . '/' . $relativePath;
        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Find PHP files matching a pattern
     *
     * @param string $projectRoot Project root directory
     * @param string $directory Directory to search
     * @param string $pattern File pattern (default: *.php)
     *
     * @return array<string> Array of file paths
     */
    protected function findPhpFiles($projectRoot, $directory, $pattern = '*.php')
    {
        $dir = $projectRoot . '/' . $directory;
        if (!is_dir($dir)) {
            return array();
        }

        $files = array();
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && fnmatch($pattern, $file->getFilename())) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Extract class name from PHP file
     *
     * Simple extraction based on namespace and class declaration.
     *
     * @param string $filePath Path to PHP file
     *
     * @return string|null Fully qualified class name or null
     */
    protected function extractClassName($filePath)
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        $namespace = '';
        $class = '';

        // Extract namespace
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = trim($matches[1]);
        }

        // Extract class name
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            $class = $matches[1];
        }

        if ($class === '') {
            return null;
        }

        return $namespace !== '' ? $namespace . '\\' . $class : $class;
    }
}
