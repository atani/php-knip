<?php
/**
 * Issue
 *
 * Represents an analysis issue (unused code)
 */

namespace PhpKnip\Analyzer;

/**
 * Represents a detected issue
 */
class Issue
{
    /**
     * Severity levels
     */
    const SEVERITY_ERROR = 'error';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_INFO = 'info';

    /**
     * Issue types (rules)
     */
    const TYPE_UNUSED_FILE = 'unused-files';
    const TYPE_UNUSED_CLASS = 'unused-classes';
    const TYPE_UNUSED_INTERFACE = 'unused-interfaces';
    const TYPE_UNUSED_TRAIT = 'unused-traits';
    const TYPE_UNUSED_METHOD = 'unused-methods';
    const TYPE_UNUSED_FUNCTION = 'unused-functions';
    const TYPE_UNUSED_CONSTANT = 'unused-constants';
    const TYPE_UNUSED_PROPERTY = 'unused-properties';
    const TYPE_UNUSED_PARAMETER = 'unused-parameters';
    const TYPE_UNUSED_VARIABLE = 'unused-variables';
    const TYPE_UNUSED_USE = 'unused-use-statements';
    const TYPE_UNUSED_DEPENDENCY = 'unused-dependencies';

    /**
     * @var string Issue type
     */
    private $type;

    /**
     * @var string Severity level
     */
    private $severity;

    /**
     * @var string Issue message
     */
    private $message;

    /**
     * @var string|null File path
     */
    private $filePath;

    /**
     * @var int|null Line number
     */
    private $line;

    /**
     * @var string|null Symbol name
     */
    private $symbolName;

    /**
     * @var string|null Symbol type
     */
    private $symbolType;

    /**
     * @var array Additional metadata
     */
    private $metadata = array();

    /**
     * Constructor
     *
     * @param string $type Issue type
     * @param string $message Issue message
     * @param string $severity Severity level
     */
    public function __construct($type, $message, $severity = self::SEVERITY_WARNING)
    {
        $this->type = $type;
        $this->message = $message;
        $this->severity = $severity;
    }

    /**
     * Create an unused class issue
     *
     * @param string $className Class name
     * @param string $filePath File path
     * @param int $line Line number
     *
     * @return Issue
     */
    public static function unusedClass($className, $filePath, $line)
    {
        $issue = new self(
            self::TYPE_UNUSED_CLASS,
            sprintf("Class '%s' is never used", $className),
            self::SEVERITY_ERROR
        );
        $issue->setFilePath($filePath);
        $issue->setLine($line);
        $issue->setSymbolName($className);
        $issue->setSymbolType('class');
        return $issue;
    }

    /**
     * Create an unused interface issue
     *
     * @param string $interfaceName Interface name
     * @param string $filePath File path
     * @param int $line Line number
     *
     * @return Issue
     */
    public static function unusedInterface($interfaceName, $filePath, $line)
    {
        $issue = new self(
            self::TYPE_UNUSED_INTERFACE,
            sprintf("Interface '%s' is never implemented", $interfaceName),
            self::SEVERITY_WARNING
        );
        $issue->setFilePath($filePath);
        $issue->setLine($line);
        $issue->setSymbolName($interfaceName);
        $issue->setSymbolType('interface');
        return $issue;
    }

    /**
     * Create an unused trait issue
     *
     * @param string $traitName Trait name
     * @param string $filePath File path
     * @param int $line Line number
     *
     * @return Issue
     */
    public static function unusedTrait($traitName, $filePath, $line)
    {
        $issue = new self(
            self::TYPE_UNUSED_TRAIT,
            sprintf("Trait '%s' is never used", $traitName),
            self::SEVERITY_ERROR
        );
        $issue->setFilePath($filePath);
        $issue->setLine($line);
        $issue->setSymbolName($traitName);
        $issue->setSymbolType('trait');
        return $issue;
    }

    /**
     * Create an unused function issue
     *
     * @param string $functionName Function name
     * @param string $filePath File path
     * @param int $line Line number
     *
     * @return Issue
     */
    public static function unusedFunction($functionName, $filePath, $line)
    {
        $issue = new self(
            self::TYPE_UNUSED_FUNCTION,
            sprintf("Function '%s' is never called", $functionName),
            self::SEVERITY_ERROR
        );
        $issue->setFilePath($filePath);
        $issue->setLine($line);
        $issue->setSymbolName($functionName);
        $issue->setSymbolType('function');
        return $issue;
    }

    /**
     * Create an unused method issue
     *
     * @param string $methodName Method name
     * @param string $className Class name
     * @param string $filePath File path
     * @param int $line Line number
     *
     * @return Issue
     */
    public static function unusedMethod($methodName, $className, $filePath, $line)
    {
        $issue = new self(
            self::TYPE_UNUSED_METHOD,
            sprintf("Method '%s::%s' is never called", $className, $methodName),
            self::SEVERITY_WARNING
        );
        $issue->setFilePath($filePath);
        $issue->setLine($line);
        $issue->setSymbolName($className . '::' . $methodName);
        $issue->setSymbolType('method');
        $issue->setMetadata('className', $className);
        $issue->setMetadata('methodName', $methodName);
        return $issue;
    }

    /**
     * Create an unused use statement issue
     *
     * @param string $importName Imported name
     * @param string $filePath File path
     * @param int $line Line number
     *
     * @return Issue
     */
    public static function unusedUseStatement($importName, $filePath, $line)
    {
        $issue = new self(
            self::TYPE_UNUSED_USE,
            sprintf("Use statement '%s' is never used", $importName),
            self::SEVERITY_WARNING
        );
        $issue->setFilePath($filePath);
        $issue->setLine($line);
        $issue->setSymbolName($importName);
        $issue->setSymbolType('use');
        return $issue;
    }

    /**
     * Create an unused constant issue
     *
     * @param string $constantName Constant name
     * @param string $filePath File path
     * @param int $line Line number
     *
     * @return Issue
     */
    public static function unusedConstant($constantName, $filePath, $line)
    {
        $issue = new self(
            self::TYPE_UNUSED_CONSTANT,
            sprintf("Constant '%s' is never used", $constantName),
            self::SEVERITY_WARNING
        );
        $issue->setFilePath($filePath);
        $issue->setLine($line);
        $issue->setSymbolName($constantName);
        $issue->setSymbolType('constant');
        return $issue;
    }

    /**
     * Create an unused property issue
     *
     * @param string $propertyName Property name
     * @param string $className Class name
     * @param string $filePath File path
     * @param int $line Line number
     *
     * @return Issue
     */
    public static function unusedProperty($propertyName, $className, $filePath, $line)
    {
        $issue = new self(
            self::TYPE_UNUSED_PROPERTY,
            sprintf("Property '%s::\$%s' is never used", $className, $propertyName),
            self::SEVERITY_WARNING
        );
        $issue->setFilePath($filePath);
        $issue->setLine($line);
        $issue->setSymbolName($className . '::$' . $propertyName);
        $issue->setSymbolType('property');
        $issue->setMetadata('className', $className);
        $issue->setMetadata('propertyName', $propertyName);
        return $issue;
    }

    /**
     * Create an unused file issue
     *
     * @param string $filePath File path
     *
     * @return Issue
     */
    public static function unusedFile($filePath)
    {
        $issue = new self(
            self::TYPE_UNUSED_FILE,
            sprintf("File '%s' contains no used code", basename($filePath)),
            self::SEVERITY_WARNING
        );
        $issue->setFilePath($filePath);
        $issue->setSymbolName(basename($filePath));
        $issue->setSymbolType('file');
        return $issue;
    }

    // Getters and Setters

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getSeverity()
    {
        return $this->severity;
    }

    /**
     * @param string $severity
     * @return $this
     */
    public function setSeverity($severity)
    {
        $this->severity = $severity;
        return $this;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @return string|null
     */
    public function getFilePath()
    {
        return $this->filePath;
    }

    /**
     * @param string|null $filePath
     * @return $this
     */
    public function setFilePath($filePath)
    {
        $this->filePath = $filePath;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getLine()
    {
        return $this->line;
    }

    /**
     * @param int|null $line
     * @return $this
     */
    public function setLine($line)
    {
        $this->line = $line;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getSymbolName()
    {
        return $this->symbolName;
    }

    /**
     * @param string|null $symbolName
     * @return $this
     */
    public function setSymbolName($symbolName)
    {
        $this->symbolName = $symbolName;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getSymbolType()
    {
        return $this->symbolType;
    }

    /**
     * @param string|null $symbolType
     * @return $this
     */
    public function setSymbolType($symbolType)
    {
        $this->symbolType = $symbolType;
        return $this;
    }

    /**
     * @return array
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function setMetadata($key, $value)
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getMetadataValue($key, $default = null)
    {
        return isset($this->metadata[$key]) ? $this->metadata[$key] : $default;
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            'type' => $this->type,
            'severity' => $this->severity,
            'message' => $this->message,
            'file' => $this->filePath,
            'line' => $this->line,
            'symbol' => $this->symbolName,
            'symbolType' => $this->symbolType,
            'metadata' => $this->metadata,
        );
    }

    /**
     * Get formatted location string
     *
     * @return string
     */
    public function getLocation()
    {
        if ($this->filePath === null) {
            return '';
        }

        if ($this->line !== null) {
            return $this->filePath . ':' . $this->line;
        }

        return $this->filePath;
    }
}
