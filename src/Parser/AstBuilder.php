<?php
/**
 * AST Builder
 *
 * Builds Abstract Syntax Trees from PHP source files
 */

namespace PhpKnip\Parser;

use PhpParser\Error;
use PhpParser\ErrorHandler;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpKnip\Parser\Encoding\EncodingDetector;
use PhpKnip\Parser\Encoding\EncodingConverter;

/**
 * Builds AST from PHP source files with encoding support
 */
class AstBuilder
{
    /**
     * @var ParserFactory
     */
    private $parserFactory;

    /**
     * @var EncodingDetector
     */
    private $encodingDetector;

    /**
     * @var EncodingConverter
     */
    private $encodingConverter;

    /**
     * @var string
     */
    private $phpVersion;

    /**
     * @var string|null
     */
    private $defaultEncoding;

    /**
     * @var array
     */
    private $errors = array();

    /**
     * Constructor
     *
     * @param string $phpVersion PHP version for parsing
     * @param string|null $defaultEncoding Default encoding
     */
    public function __construct($phpVersion = 'auto', $defaultEncoding = null)
    {
        $this->phpVersion = $phpVersion;
        $this->defaultEncoding = $defaultEncoding;
        $this->parserFactory = new ParserFactory();
        $this->encodingDetector = new EncodingDetector($defaultEncoding);
        $this->encodingConverter = new EncodingConverter($this->encodingDetector);
    }

    /**
     * Build AST from file
     *
     * @param string $filePath Path to PHP file
     *
     * @return array|null AST nodes or null on failure
     */
    public function buildFromFile($filePath)
    {
        if (!file_exists($filePath)) {
            $this->addError($filePath, 0, "File not found: $filePath");
            return null;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            $this->addError($filePath, 0, "Unable to read file: $filePath");
            return null;
        }

        return $this->buildFromString($content, $filePath);
    }

    /**
     * Build AST from string
     *
     * @param string $code PHP source code
     * @param string $filePath File path for error reporting
     *
     * @return array|null AST nodes or null on failure
     */
    public function buildFromString($code, $filePath = 'unknown')
    {
        // Convert encoding to UTF-8 if necessary
        try {
            $conversionResult = $this->encodingConverter->toUtf8($code);
            $code = $conversionResult['content'];
        } catch (\RuntimeException $e) {
            $this->addError($filePath, 0, "Encoding conversion failed: " . $e->getMessage());
            return null;
        }

        // Detect PHP version if auto
        $phpVersion = $this->phpVersion;
        if ($phpVersion === 'auto') {
            $phpVersion = $this->parserFactory->detectVersion($code);
        }

        // Create parser
        $parser = $this->parserFactory->create($phpVersion);

        // Parse with error handling
        try {
            $ast = $parser->parse($code);

            if ($ast === null) {
                $this->addError($filePath, 0, "Parser returned null");
                return null;
            }

            // Resolve names (fully qualified class names)
            $ast = $this->resolveNames($ast);

            return $ast;
        } catch (Error $e) {
            $this->addError($filePath, $e->getStartLine(), $e->getMessage());
            return null;
        }
    }

    /**
     * Resolve names in AST (fully qualify class names)
     *
     * @param array $ast AST nodes
     *
     * @return array AST with resolved names
     */
    private function resolveNames(array $ast)
    {
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());

        return $traverser->traverse($ast);
    }

    /**
     * Build ASTs from multiple files
     *
     * @param array $filePaths Array of file paths
     *
     * @return array Map of file path to AST (or null for failed files)
     */
    public function buildFromFiles(array $filePaths)
    {
        $results = array();

        foreach ($filePaths as $filePath) {
            $results[$filePath] = $this->buildFromFile($filePath);
        }

        return $results;
    }

    /**
     * Add an error
     *
     * @param string $file File path
     * @param int $line Line number
     * @param string $message Error message
     */
    private function addError($file, $line, $message)
    {
        $this->errors[] = array(
            'file' => $file,
            'line' => $line,
            'message' => $message,
        );
    }

    /**
     * Get all errors
     *
     * @return array Array of error arrays
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Check if there were errors
     *
     * @return bool True if errors occurred
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }

    /**
     * Clear errors
     */
    public function clearErrors()
    {
        $this->errors = array();
    }

    /**
     * Get encoding detection result for content
     *
     * @param string $content File content
     *
     * @return array Detection result
     */
    public function detectEncoding($content)
    {
        return $this->encodingDetector->detect($content);
    }

    /**
     * Set default encoding
     *
     * @param string $encoding Default encoding
     */
    public function setDefaultEncoding($encoding)
    {
        $this->defaultEncoding = $encoding;
        $this->encodingDetector = new EncodingDetector($encoding);
        $this->encodingConverter = new EncodingConverter($this->encodingDetector);
    }

    /**
     * Set PHP version
     *
     * @param string $version PHP version
     */
    public function setPhpVersion($version)
    {
        $this->phpVersion = $version;
    }
}
