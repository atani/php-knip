<?php
/**
 * Encoding Detector
 *
 * Detects file encoding using multiple strategies
 */

namespace PhpKnip\Parser\Encoding;

/**
 * Detects encoding of PHP source files
 *
 * Detection order:
 * 1. BOM (Byte Order Mark)
 * 2. declare(encoding='...') statement
 * 3. Explicit configuration
 * 4. mbstring auto-detection
 * 5. Default (UTF-8)
 */
class EncodingDetector
{
    /**
     * Known BOM signatures
     *
     * @var array
     */
    private static $bomSignatures = array(
        'UTF-8' => "\xEF\xBB\xBF",
        'UTF-16BE' => "\xFE\xFF",
        'UTF-16LE' => "\xFF\xFE",
        'UTF-32BE' => "\x00\x00\xFE\xFF",
        'UTF-32LE' => "\xFF\xFE\x00\x00",
    );

    /**
     * Encoding aliases mapping
     *
     * @var array
     */
    private static $encodingAliases = array(
        'eucjp' => 'EUC-JP',
        'euc-jp' => 'EUC-JP',
        'euc_jp' => 'EUC-JP',
        'ujis' => 'EUC-JP',
        'shiftjis' => 'SJIS',
        'shift-jis' => 'SJIS',
        'shift_jis' => 'SJIS',
        'sjis' => 'SJIS',
        'cp932' => 'SJIS-win',
        'ms932' => 'SJIS-win',
        'windows-31j' => 'SJIS-win',
        'utf8' => 'UTF-8',
        'utf-8' => 'UTF-8',
        'iso-2022-jp' => 'ISO-2022-JP',
        'jis' => 'ISO-2022-JP',
    );

    /**
     * Supported encodings for detection
     *
     * @var array
     */
    private static $detectOrder = array(
        'UTF-8',
        'EUC-JP',
        'SJIS',
        'SJIS-win',
        'ISO-2022-JP',
        'ASCII',
    );

    /**
     * Configured encoding (from config file)
     *
     * @var string|null
     */
    private $configuredEncoding;

    /**
     * Constructor
     *
     * @param string|null $configuredEncoding Encoding from configuration
     */
    public function __construct($configuredEncoding = null)
    {
        $this->configuredEncoding = $configuredEncoding;
    }

    /**
     * Detect encoding of file content
     *
     * @param string $content File content
     * @param string $filePath File path (for logging)
     *
     * @return array Detection result with 'encoding' and 'method' keys
     */
    public function detect($content, $filePath = '')
    {
        // 1. Check for BOM
        $bomResult = $this->detectBom($content);
        if ($bomResult !== null) {
            return array(
                'encoding' => $bomResult,
                'method' => 'bom',
                'confidence' => 'high',
            );
        }

        // 2. Check for declare(encoding=...) statement
        $declareResult = $this->detectDeclareEncoding($content);
        if ($declareResult !== null) {
            return array(
                'encoding' => $declareResult,
                'method' => 'declare',
                'confidence' => 'high',
            );
        }

        // 3. Use configured encoding if specified
        if ($this->configuredEncoding !== null && $this->configuredEncoding !== 'auto') {
            $normalized = $this->normalizeEncoding($this->configuredEncoding);
            return array(
                'encoding' => $normalized,
                'method' => 'config',
                'confidence' => 'medium',
            );
        }

        // 4. Use mbstring auto-detection
        $mbResult = $this->detectWithMbstring($content);
        if ($mbResult !== null) {
            return array(
                'encoding' => $mbResult,
                'method' => 'mbstring',
                'confidence' => 'medium',
            );
        }

        // 5. Default to UTF-8
        return array(
            'encoding' => 'UTF-8',
            'method' => 'default',
            'confidence' => 'low',
        );
    }

    /**
     * Detect encoding from BOM
     *
     * @param string $content File content
     *
     * @return string|null Detected encoding or null
     */
    private function detectBom($content)
    {
        // Check longer BOMs first (UTF-32 before UTF-16)
        $bomChecks = array(
            'UTF-32BE' => 4,
            'UTF-32LE' => 4,
            'UTF-8' => 3,
            'UTF-16BE' => 2,
            'UTF-16LE' => 2,
        );

        foreach ($bomChecks as $encoding => $length) {
            $bom = self::$bomSignatures[$encoding];
            if (substr($content, 0, $length) === $bom) {
                return $encoding;
            }
        }

        return null;
    }

    /**
     * Detect encoding from declare(encoding=...) statement
     *
     * @param string $content File content
     *
     * @return string|null Detected encoding or null
     */
    private function detectDeclareEncoding($content)
    {
        // Pattern to match declare(encoding='...') or declare(encoding="...")
        // Must be in the first portion of the file (within first 1024 bytes)
        $searchContent = substr($content, 0, 1024);

        $pattern = '/declare\s*\(\s*encoding\s*=\s*[\'"]([^\'"]+)[\'"]\s*\)/i';

        if (preg_match($pattern, $searchContent, $matches)) {
            return $this->normalizeEncoding($matches[1]);
        }

        return null;
    }

    /**
     * Detect encoding using mbstring
     *
     * @param string $content File content
     *
     * @return string|null Detected encoding or null
     */
    private function detectWithMbstring($content)
    {
        if (!function_exists('mb_detect_encoding')) {
            return null;
        }

        // Use strict mode for more accurate detection
        $detected = mb_detect_encoding($content, self::$detectOrder, true);

        if ($detected !== false) {
            return $detected;
        }

        // Try non-strict mode as fallback
        $detected = mb_detect_encoding($content, self::$detectOrder, false);

        return $detected !== false ? $detected : null;
    }

    /**
     * Normalize encoding name to standard form
     *
     * @param string $encoding Encoding name
     *
     * @return string Normalized encoding name
     */
    public function normalizeEncoding($encoding)
    {
        $lower = strtolower(trim($encoding));

        if (isset(self::$encodingAliases[$lower])) {
            return self::$encodingAliases[$lower];
        }

        // Return uppercase version if not in aliases
        return strtoupper($encoding);
    }

    /**
     * Check if encoding is supported
     *
     * @param string $encoding Encoding name
     *
     * @return bool True if supported
     */
    public function isSupported($encoding)
    {
        $normalized = $this->normalizeEncoding($encoding);

        $supported = array(
            'UTF-8',
            'EUC-JP',
            'SJIS',
            'SJIS-win',
            'ISO-2022-JP',
            'ASCII',
            'UTF-16BE',
            'UTF-16LE',
        );

        return in_array($normalized, $supported, true);
    }

    /**
     * Get BOM length for encoding
     *
     * @param string $encoding Encoding name
     *
     * @return int BOM length in bytes (0 if no BOM)
     */
    public function getBomLength($encoding)
    {
        $bomLengths = array(
            'UTF-8' => 3,
            'UTF-16BE' => 2,
            'UTF-16LE' => 2,
            'UTF-32BE' => 4,
            'UTF-32LE' => 4,
        );

        return isset($bomLengths[$encoding]) ? $bomLengths[$encoding] : 0;
    }

    /**
     * Remove BOM from content if present
     *
     * @param string $content File content
     * @param string $encoding Detected encoding
     *
     * @return string Content without BOM
     */
    public function removeBom($content, $encoding)
    {
        $bomLength = $this->getBomLength($encoding);

        if ($bomLength > 0 && isset(self::$bomSignatures[$encoding])) {
            $bom = self::$bomSignatures[$encoding];
            if (substr($content, 0, $bomLength) === $bom) {
                return substr($content, $bomLength);
            }
        }

        return $content;
    }
}
