<?php
/**
 * Parser Factory
 *
 * Creates PHP-Parser instances based on PHP version
 */

namespace PhpKnip\Parser;

use PhpParser\Parser;
use PhpParser\ParserFactory as PhpParserFactory;
use PhpParser\Lexer;

/**
 * Factory for creating PHP-Parser instances
 *
 * Supports PHP-Parser v3, v4, and v5
 */
class ParserFactory
{
    /**
     * PHP version constants
     */
    const PHP_4 = '4';
    const PHP_5 = '5';
    const PHP_7 = '7';
    const PHP_8 = '8';

    /**
     * Create parser for specified PHP version
     *
     * @param string $phpVersion PHP version (e.g., '5.6', '7.4', '8.0', 'auto')
     *
     * @return Parser Parser instance
     */
    public function create($phpVersion = 'auto')
    {
        $factory = new PhpParserFactory();

        // PHP-Parser v5 API (createForNewestSupportedVersion exists)
        if (method_exists($factory, 'createForNewestSupportedVersion')) {
            return $this->createV5Parser($factory, $phpVersion);
        }

        // PHP-Parser v4 API (create with PREFER_PHP7 constant)
        if (method_exists($factory, 'create') && defined('PhpParser\\ParserFactory::PREFER_PHP7')) {
            return $this->createV4Parser($factory, $phpVersion);
        }

        // PHP-Parser v3 fallback
        return $this->createLegacyParser($phpVersion);
    }

    /**
     * Create parser using PHP-Parser v5 API
     *
     * @param PhpParserFactory $factory Parser factory
     * @param string $phpVersion PHP version
     *
     * @return Parser Parser instance
     */
    private function createV5Parser(PhpParserFactory $factory, $phpVersion)
    {
        // v5 uses createForNewestSupportedVersion() or createForVersion()
        if ($phpVersion === 'auto') {
            return $factory->createForNewestSupportedVersion();
        }

        // For specific versions, use createForVersion if available
        if (method_exists($factory, 'createForVersion') && class_exists('PhpParser\\PhpVersion')) {
            // PHP 4 is not directly supported by php-parser; use PHP 5 parser
            $versionString = $this->getMajorVersion($phpVersion) === self::PHP_4 ? '5.6' : $phpVersion;
            $phpVersionObj = \PhpParser\PhpVersion::fromString($versionString);
            return $factory->createForVersion($phpVersionObj);
        }

        // Fallback to newest supported
        return $factory->createForNewestSupportedVersion();
    }

    /**
     * Create parser using PHP-Parser v4 API
     *
     * @param PhpParserFactory $factory Parser factory
     * @param string $phpVersion PHP version
     *
     * @return Parser Parser instance
     */
    private function createV4Parser(PhpParserFactory $factory, $phpVersion)
    {
        $kind = $this->getParserKindV4($phpVersion);
        $lexer = $this->createLexer($phpVersion);
        return $factory->create($kind, $lexer);
    }

    /**
     * Get parser kind constant for PHP-Parser v4
     *
     * @param string $phpVersion PHP version
     *
     * @return int Parser kind constant
     */
    private function getParserKindV4($phpVersion)
    {
        if ($phpVersion === 'auto') {
            return PhpParserFactory::PREFER_PHP7;
        }

        $majorVersion = $this->getMajorVersion($phpVersion);

        switch ($majorVersion) {
            case self::PHP_4:
            case self::PHP_5:
                return defined('PhpParser\\ParserFactory::PREFER_PHP5')
                    ? PhpParserFactory::PREFER_PHP5
                    : PhpParserFactory::PREFER_PHP7;
            case self::PHP_7:
            case self::PHP_8:
            default:
                return PhpParserFactory::PREFER_PHP7;
        }
    }

    /**
     * Create lexer with appropriate options
     *
     * @param string $phpVersion PHP version
     *
     * @return Lexer|null Lexer instance or null for v5
     */
    private function createLexer($phpVersion)
    {
        $options = array(
            'usedAttributes' => array(
                'comments',
                'startLine',
                'endLine',
                'startFilePos',
                'endFilePos',
                'startTokenPos',
                'endTokenPos',
            ),
        );

        // Check if Lexer\Emulative exists (PHP-Parser v4+)
        if (class_exists('PhpParser\\Lexer\\Emulative')) {
            return new Lexer\Emulative($options);
        }

        // Fallback to standard Lexer
        return new Lexer($options);
    }

    /**
     * Create parser using PHP-Parser v3 API
     *
     * @param string $phpVersion PHP version
     *
     * @return Parser Parser instance
     */
    private function createLegacyParser($phpVersion)
    {
        $lexer = $this->createLexer($phpVersion);
        $majorVersion = $this->getMajorVersion($phpVersion);

        // PHP-Parser v3 uses different class names
        // PHP 4 code is parsed using the PHP 5 parser (syntax is a subset)
        if (($majorVersion === self::PHP_4 || $majorVersion === self::PHP_5) && class_exists('PhpParser\\Parser\\Php5')) {
            return new \PhpParser\Parser\Php5($lexer);
        }

        // Default to PHP7 parser
        if (class_exists('PhpParser\\Parser\\Php7')) {
            return new \PhpParser\Parser\Php7($lexer);
        }

        throw new \RuntimeException('Unable to create PHP parser. Check PHP-Parser installation.');
    }

    /**
     * Extract major version from version string
     *
     * @param string $version Version string (e.g., '7.4.0', '8.0', '5.6')
     *
     * @return string Major version (e.g., '7', '8', '5')
     */
    private function getMajorVersion($version)
    {
        $parts = explode('.', $version);
        return isset($parts[0]) ? $parts[0] : '7';
    }

    /**
     * Detect PHP version of source code
     *
     * @param string $code PHP source code
     *
     * @return string Detected PHP version
     */
    public function detectVersion($code)
    {
        // Check for PHP 8+ features
        if ($this->hasPHP8Features($code)) {
            return '8.0';
        }

        // Check for PHP 7+ features
        if ($this->hasPHP7Features($code)) {
            return '7.0';
        }

        // Check for PHP 5+ features (namespaces, traits, etc.)
        if ($this->hasPHP5Features($code)) {
            return '5.6';
        }

        // Check for PHP 4 patterns (no PHP 5+ features detected)
        if ($this->hasPHP4Patterns($code)) {
            return '4.4';
        }

        // Default to PHP 5.6 for legacy code
        return '5.6';
    }

    /**
     * Check for PHP 8 specific features
     *
     * @param string $code PHP source code
     *
     * @return bool True if PHP 8 features detected
     */
    private function hasPHP8Features($code)
    {
        // Attributes
        if (preg_match('/#\[[\w\\\\]+/', $code)) {
            return true;
        }

        // Match expression
        if (preg_match('/\bmatch\s*\(/', $code)) {
            return true;
        }

        // Constructor property promotion
        if (preg_match('/function\s+__construct\s*\([^)]*\b(public|private|protected)\s+/', $code)) {
            return true;
        }

        // Union types with null (PHP 8 style)
        if (preg_match('/:\s*\w+\s*\|\s*null\b/', $code)) {
            return true;
        }

        return false;
    }

    /**
     * Check for PHP 7 specific features
     *
     * @param string $code PHP source code
     *
     * @return bool True if PHP 7 features detected
     */
    private function hasPHP7Features($code)
    {
        // Scalar type declarations
        if (preg_match('/function\s+\w+\s*\([^)]*:\s*(int|string|bool|float)\s*[,)]/', $code)) {
            return true;
        }

        // Return type declarations
        if (preg_match('/\)\s*:\s*(int|string|bool|float|array|void|self|parent|\??\w+)/', $code)) {
            return true;
        }

        // Null coalescing operator
        if (strpos($code, '??') !== false) {
            return true;
        }

        // Spaceship operator
        if (strpos($code, '<=>') !== false) {
            return true;
        }

        // Anonymous classes
        if (preg_match('/new\s+class\s*[({]/', $code)) {
            return true;
        }

        return false;
    }

    /**
     * Check for PHP 5 specific features (not present in PHP 4)
     *
     * Known limitation: regex patterns match against raw source code including
     * comments and string literals, which may cause false positives.
     * This is acceptable for file-level version detection heuristics.
     *
     * @param string $code PHP source code
     *
     * @return bool True if PHP 5 features detected
     */
    private function hasPHP5Features($code)
    {
        // Namespaces
        if (preg_match('/\bnamespace\s+[\w\\\\]+/', $code)) {
            return true;
        }

        // Traits
        if (preg_match('/\btrait\s+\w+/', $code)) {
            return true;
        }

        // Visibility modifiers on methods/properties (public/protected/private)
        if (preg_match('/\b(public|protected|private)\s+(static\s+)?(\$|function\s)/', $code)) {
            return true;
        }

        // Abstract/final classes
        if (preg_match('/\b(abstract|final)\s+class\b/', $code)) {
            return true;
        }

        // Type hints (PHP 5 style)
        if (preg_match('/function\s+\w+\s*\([^)]*\b(array|callable)\s+\$/', $code)) {
            return true;
        }

        // try-catch (PHP 5+)
        if (preg_match('/\btry\s*\{/', $code)) {
            return true;
        }

        // __construct method (PHP 5+ style constructor)
        if (preg_match('/function\s+__construct\s*\(/', $code)) {
            return true;
        }

        // Interface declarations or implements clause (PHP 5+)
        if (preg_match('/\b(interface\s+\w+|implements\s+\w+)/', $code)) {
            return true;
        }

        return false;
    }

    /**
     * Check for PHP 4 patterns
     *
     * @param string $code PHP source code
     *
     * @return bool True if PHP 4 patterns detected
     */
    private function hasPHP4Patterns($code)
    {
        // var keyword for property declarations
        if (preg_match('/\bvar\s+\$\w+/', $code)) {
            return true;
        }

        // Old-style constructor (method name matches class name, case-insensitive)
        // Two-step approach to avoid O(n^2) regex performance on large files:
        // 1. Extract class names, 2. Search for matching function declarations
        // Known limitation: matches function names against class names globally (not per-class scope),
        // so `function Foo()` in `class Bar` may match `class Foo` in the same file.
        // This is acceptable for file-level version detection heuristics.
        // Note: implements is not checked here because hasPHP5Features catches it first
        if (preg_match_all('/\bclass\s+(\w+)\b/', $code, $matches)) {
            foreach ($matches[1] as $className) {
                if (preg_match('/\bfunction\s+' . preg_quote($className, '/') . '\s*\(/i', $code)) {
                    return true;
                }
            }
        }

        // Classes without visibility modifiers
        // Known limitation: checks visibility keywords anywhere in the file, not scoped to class body.
        // Visibility keywords inside strings or comments may cause false negatives.
        if (preg_match('/\bclass\s+\w+/', $code) && !preg_match('/\b(public|protected|private)\b/', $code)) {
            return true;
        }

        return false;
    }

    /**
     * Get supported PHP versions
     *
     * @return array List of supported PHP versions
     */
    public function getSupportedVersions()
    {
        return array(
            '4.4',
            '5.6',
            '7.0',
            '7.1',
            '7.2',
            '7.3',
            '7.4',
            '8.0',
            '8.1',
            '8.2',
            '8.3',
        );
    }
}
