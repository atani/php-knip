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
 * Supports PHP 5.6 through PHP 8.x syntax parsing
 */
class ParserFactory
{
    /**
     * PHP version constants
     */
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

        // Determine parser kind based on version
        $kind = $this->getParserKind($phpVersion);

        // Create lexer with appropriate options
        $lexer = $this->createLexer($phpVersion);

        // PHP-Parser v4/v5 API
        if (method_exists($factory, 'create')) {
            return $factory->create($kind, $lexer);
        }

        // PHP-Parser v3 API fallback
        return $this->createLegacyParser($kind, $lexer);
    }

    /**
     * Get parser kind constant for PHP-Parser
     *
     * @param string $phpVersion PHP version
     *
     * @return int Parser kind constant
     */
    private function getParserKind($phpVersion)
    {
        if ($phpVersion === 'auto') {
            // Use the most compatible parser
            return PhpParserFactory::PREFER_PHP7;
        }

        $majorVersion = $this->getMajorVersion($phpVersion);

        switch ($majorVersion) {
            case self::PHP_5:
                return PhpParserFactory::PREFER_PHP5;
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
     * @return Lexer Lexer instance
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
     * @param int $kind Parser kind
     * @param Lexer $lexer Lexer instance
     *
     * @return Parser Parser instance
     */
    private function createLegacyParser($kind, Lexer $lexer)
    {
        // PHP-Parser v3 uses different class names
        switch ($kind) {
            case PhpParserFactory::PREFER_PHP5:
            case PhpParserFactory::ONLY_PHP5:
                if (class_exists('PhpParser\\Parser\\Php5')) {
                    return new \PhpParser\Parser\Php5($lexer);
                }
                break;
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

        // Named arguments
        if (preg_match('/\w+\s*:\s*\$?\w+/', $code)) {
            // This is a simplified check; named arguments are context-dependent
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
     * Get supported PHP versions
     *
     * @return array List of supported PHP versions
     */
    public function getSupportedVersions()
    {
        return array(
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
