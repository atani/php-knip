<?php
/**
 * Cache Manager
 *
 * Manages caching of analysis results for improved performance
 */

namespace PhpKnip\Cache;

use PhpKnip\Resolver\Symbol;
use PhpKnip\Resolver\Reference;

/**
 * Manages file-based caching of symbols and references
 */
class CacheManager
{
    /**
     * Cache format version (increment when format changes)
     */
    const VERSION = 1;

    /**
     * @var string Cache directory path
     */
    private $cacheDir;

    /**
     * @var bool Whether caching is enabled
     */
    private $enabled;

    /**
     * @var array Cache metadata
     */
    private $metadata = array();

    /**
     * @var bool Whether metadata has been modified
     */
    private $metadataModified = false;

    /**
     * @var array Statistics
     */
    private $stats = array(
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
    );

    /**
     * Constructor
     *
     * @param string $cacheDir Cache directory path
     * @param bool $enabled Whether caching is enabled
     */
    public function __construct($cacheDir, $enabled = true)
    {
        $this->cacheDir = rtrim($cacheDir, '/');
        $this->enabled = $enabled;

        if ($this->enabled) {
            $this->ensureCacheDir();
            $this->loadMetadata();
        }
    }

    /**
     * Check if cache is valid for a file
     *
     * @param string $filePath File path to check
     *
     * @return bool
     */
    public function isValid($filePath)
    {
        if (!$this->enabled) {
            return false;
        }

        $cacheKey = $this->getCacheKey($filePath);

        // Check if we have metadata for this file
        if (!isset($this->metadata['files'][$cacheKey])) {
            return false;
        }

        $meta = $this->metadata['files'][$cacheKey];

        // Check if file exists
        if (!file_exists($filePath)) {
            return false;
        }

        // Check if cache file exists
        $cacheFile = $this->getCacheFilePath($cacheKey);
        if (!file_exists($cacheFile)) {
            return false;
        }

        // Fast path: check mtime
        $currentMtime = filemtime($filePath);
        if ($currentMtime === $meta['mtime']) {
            return true;
        }

        // Slow path: check hash (file was touched but maybe not changed)
        $currentHash = $this->getFileHash($filePath);
        if ($currentHash === $meta['hash']) {
            // Update mtime in metadata
            $this->metadata['files'][$cacheKey]['mtime'] = $currentMtime;
            $this->metadataModified = true;
            return true;
        }

        return false;
    }

    /**
     * Get cached data for a file
     *
     * @param string $filePath File path
     *
     * @return array|null Cached data or null if not found
     */
    public function get($filePath)
    {
        if (!$this->enabled) {
            return null;
        }

        $cacheKey = $this->getCacheKey($filePath);
        $cacheFile = $this->getCacheFilePath($cacheKey);

        if (!file_exists($cacheFile)) {
            $this->stats['misses']++;
            return null;
        }

        $content = file_get_contents($cacheFile);
        if ($content === false) {
            $this->stats['misses']++;
            return null;
        }

        $data = json_decode($content, true);
        if ($data === null) {
            $this->stats['misses']++;
            return null;
        }

        $this->stats['hits']++;
        return $data;
    }

    /**
     * Store data in cache
     *
     * @param string $filePath File path
     * @param array $data Data to cache (symbols, references, useStatements)
     *
     * @return bool Success
     */
    public function set($filePath, array $data)
    {
        if (!$this->enabled) {
            return false;
        }

        $cacheKey = $this->getCacheKey($filePath);
        $cacheFile = $this->getCacheFilePath($cacheKey);

        // Store cache data
        $json = json_encode($data, JSON_PRETTY_PRINT);
        if ($json === false) {
            return false;
        }

        if (file_put_contents($cacheFile, $json) === false) {
            return false;
        }

        // Update metadata
        $this->metadata['files'][$cacheKey] = array(
            'path' => $filePath,
            'mtime' => filemtime($filePath),
            'hash' => $this->getFileHash($filePath),
            'size' => filesize($filePath),
            'cached' => time(),
        );
        $this->metadataModified = true;
        $this->stats['writes']++;

        return true;
    }

    /**
     * Clear all cache
     *
     * @return bool Success
     */
    public function clear()
    {
        if (!is_dir($this->cacheDir)) {
            return true;
        }

        $files = glob($this->cacheDir . '/*.json');
        if ($files === false) {
            return false;
        }

        foreach ($files as $file) {
            unlink($file);
        }

        $this->metadata = array(
            'version' => self::VERSION,
            'created' => time(),
            'files' => array(),
        );
        $this->metadataModified = true;
        $this->saveMetadata();

        return true;
    }

    /**
     * Get cache statistics
     *
     * @return array
     */
    public function getStats()
    {
        $fileCount = isset($this->metadata['files']) ? count($this->metadata['files']) : 0;
        $cacheSize = 0;

        if (is_dir($this->cacheDir)) {
            $files = glob($this->cacheDir . '/*.json');
            if ($files !== false) {
                foreach ($files as $file) {
                    $cacheSize += filesize($file);
                }
            }
        }

        return array(
            'enabled' => $this->enabled,
            'directory' => $this->cacheDir,
            'fileCount' => $fileCount,
            'cacheSize' => $cacheSize,
            'hits' => $this->stats['hits'],
            'misses' => $this->stats['misses'],
            'writes' => $this->stats['writes'],
            'hitRate' => $this->stats['hits'] + $this->stats['misses'] > 0
                ? round($this->stats['hits'] / ($this->stats['hits'] + $this->stats['misses']) * 100, 1)
                : 0,
        );
    }

    /**
     * Save metadata to disk
     *
     * @return bool Success
     */
    public function saveMetadata()
    {
        if (!$this->enabled || !$this->metadataModified) {
            return true;
        }

        $metaFile = $this->cacheDir . '/meta.json';
        $json = json_encode($this->metadata, JSON_PRETTY_PRINT);

        if ($json === false) {
            return false;
        }

        $result = file_put_contents($metaFile, $json) !== false;
        if ($result) {
            $this->metadataModified = false;
        }

        return $result;
    }

    /**
     * Ensure cache directory exists
     *
     * @return void
     */
    private function ensureCacheDir()
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Load metadata from disk
     *
     * @return void
     */
    private function loadMetadata()
    {
        $metaFile = $this->cacheDir . '/meta.json';

        if (!file_exists($metaFile)) {
            $this->metadata = array(
                'version' => self::VERSION,
                'created' => time(),
                'files' => array(),
            );
            return;
        }

        $content = file_get_contents($metaFile);
        if ($content === false) {
            $this->metadata = array(
                'version' => self::VERSION,
                'created' => time(),
                'files' => array(),
            );
            return;
        }

        $data = json_decode($content, true);
        if ($data === null || !isset($data['version']) || $data['version'] !== self::VERSION) {
            // Version mismatch or invalid - clear cache
            $this->clear();
            return;
        }

        $this->metadata = $data;
    }

    /**
     * Get cache key for a file path
     *
     * @param string $filePath File path
     *
     * @return string Cache key
     */
    private function getCacheKey($filePath)
    {
        return md5($filePath);
    }

    /**
     * Get cache file path for a cache key
     *
     * @param string $cacheKey Cache key
     *
     * @return string Cache file path
     */
    private function getCacheFilePath($cacheKey)
    {
        return $this->cacheDir . '/' . $cacheKey . '.json';
    }

    /**
     * Get file content hash
     *
     * @param string $filePath File path
     *
     * @return string Hash
     */
    private function getFileHash($filePath)
    {
        return md5_file($filePath);
    }

    /**
     * Check if caching is enabled
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }
}
