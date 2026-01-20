<?php
/**
 * Reference
 *
 * Represents a reference to a symbol
 */

namespace PhpKnip\Resolver;

/**
 * Represents a reference to a code symbol
 */
class Reference
{
    /**
     * Reference types
     */
    const TYPE_NEW = 'new';                     // new ClassName()
    const TYPE_EXTENDS = 'extends';             // extends ClassName
    const TYPE_IMPLEMENTS = 'implements';       // implements InterfaceName
    const TYPE_USE_TRAIT = 'use_trait';         // use TraitName
    const TYPE_USE_IMPORT = 'use_import';       // use Namespace\ClassName
    const TYPE_STATIC_CALL = 'static_call';     // ClassName::method()
    const TYPE_STATIC_PROPERTY = 'static_prop'; // ClassName::$property
    const TYPE_CONSTANT = 'constant';           // ClassName::CONST or CONST
    const TYPE_FUNCTION_CALL = 'function_call'; // functionName()
    const TYPE_METHOD_CALL = 'method_call';     // $obj->method()
    const TYPE_PROPERTY_ACCESS = 'property';    // $obj->property
    const TYPE_INSTANCEOF = 'instanceof';       // instanceof ClassName
    const TYPE_TYPE_HINT = 'type_hint';         // function(ClassName $param)
    const TYPE_RETURN_TYPE = 'return_type';     // function(): ClassName
    const TYPE_CATCH = 'catch';                 // catch (ExceptionClass $e)
    const TYPE_CLASS_STRING = 'class_string';   // ClassName::class or 'ClassName'

    /**
     * @var string Reference type
     */
    private $type;

    /**
     * @var string Referenced symbol name
     */
    private $symbolName;

    /**
     * @var string|null Referenced symbol's parent (for methods/properties)
     */
    private $symbolParent;

    /**
     * @var string|null File where reference occurs
     */
    private $filePath;

    /**
     * @var int|null Line number
     */
    private $line;

    /**
     * @var string|null Context (class/function where reference occurs)
     */
    private $context;

    /**
     * @var bool Whether this is a dynamic reference (e.g., $className::method())
     */
    private $isDynamic = false;

    /**
     * @var array Additional metadata
     */
    private $metadata = array();

    /**
     * Constructor
     *
     * @param string $type Reference type
     * @param string $symbolName Symbol name
     */
    public function __construct($type, $symbolName)
    {
        $this->type = $type;
        $this->symbolName = $symbolName;
    }

    /**
     * Create a new instance reference
     *
     * @param string $className Class name
     * @param string|null $filePath File path (optional)
     * @param int|null $line Line number (optional)
     *
     * @return Reference
     */
    public static function createNew($className, $filePath = null, $line = null)
    {
        $ref = new self(self::TYPE_NEW, $className);
        if ($filePath !== null) {
            $ref->setFilePath($filePath);
        }
        if ($line !== null) {
            $ref->setLine($line);
        }
        return $ref;
    }

    /**
     * Create an extends reference
     *
     * @param string $className Parent class name
     * @param string|null $filePath File path (optional)
     * @param int|null $line Line number (optional)
     *
     * @return Reference
     */
    public static function createExtends($className, $filePath = null, $line = null)
    {
        $ref = new self(self::TYPE_EXTENDS, $className);
        if ($filePath !== null) {
            $ref->setFilePath($filePath);
        }
        if ($line !== null) {
            $ref->setLine($line);
        }
        return $ref;
    }

    /**
     * Create an implements reference
     *
     * @param string $interfaceName Interface name
     * @param string|null $filePath File path (optional)
     * @param int|null $line Line number (optional)
     *
     * @return Reference
     */
    public static function createImplements($interfaceName, $filePath = null, $line = null)
    {
        $ref = new self(self::TYPE_IMPLEMENTS, $interfaceName);
        if ($filePath !== null) {
            $ref->setFilePath($filePath);
        }
        if ($line !== null) {
            $ref->setLine($line);
        }
        return $ref;
    }

    /**
     * Create a trait use reference
     *
     * @param string $traitName Trait name
     * @param string|null $filePath File path (optional)
     * @param int|null $line Line number (optional)
     *
     * @return Reference
     */
    public static function createUseTrait($traitName, $filePath = null, $line = null)
    {
        $ref = new self(self::TYPE_USE_TRAIT, $traitName);
        if ($filePath !== null) {
            $ref->setFilePath($filePath);
        }
        if ($line !== null) {
            $ref->setLine($line);
        }
        return $ref;
    }

    /**
     * Create a use import reference
     *
     * @param string $name Imported name (FQN)
     * @param string|null $alias Alias name
     *
     * @return Reference
     */
    public static function createUseImport($name, $alias = null)
    {
        $ref = new self(self::TYPE_USE_IMPORT, $name);
        if ($alias !== null) {
            $ref->setMetadata('alias', $alias);
        }
        return $ref;
    }

    /**
     * Create a static method call reference
     *
     * @param string $className Class name
     * @param string $methodName Method name
     * @param string|null $filePath File path (optional)
     * @param int|null $line Line number (optional)
     *
     * @return Reference
     */
    public static function createStaticCall($className, $methodName, $filePath = null, $line = null)
    {
        $ref = new self(self::TYPE_STATIC_CALL, $methodName);
        $ref->setSymbolParent($className);
        if ($filePath !== null) {
            $ref->setFilePath($filePath);
        }
        if ($line !== null) {
            $ref->setLine($line);
        }
        return $ref;
    }

    /**
     * Create a function call reference
     *
     * @param string $functionName Function name
     * @param string|null $filePath File path (optional)
     * @param int|null $line Line number (optional)
     *
     * @return Reference
     */
    public static function createFunctionCall($functionName, $filePath = null, $line = null)
    {
        $ref = new self(self::TYPE_FUNCTION_CALL, $functionName);
        if ($filePath !== null) {
            $ref->setFilePath($filePath);
        }
        if ($line !== null) {
            $ref->setLine($line);
        }
        return $ref;
    }

    /**
     * Create a method call reference
     *
     * @param string $methodName Method name
     * @param string|null $className Class name if known
     *
     * @return Reference
     */
    public static function createMethodCall($methodName, $className = null)
    {
        $ref = new self(self::TYPE_METHOD_CALL, $methodName);
        if ($className !== null) {
            $ref->setSymbolParent($className);
        }
        return $ref;
    }

    /**
     * Create a constant reference
     *
     * @param string $constantName Constant name
     * @param string|null $className Class name for class constants
     *
     * @return Reference
     */
    public static function createConstant($constantName, $className = null)
    {
        $ref = new self(self::TYPE_CONSTANT, $constantName);
        if ($className !== null) {
            $ref->setSymbolParent($className);
        }
        return $ref;
    }

    /**
     * Create an instanceof reference
     *
     * @param string $className Class name
     * @param string|null $filePath File path (optional)
     * @param int|null $line Line number (optional)
     *
     * @return Reference
     */
    public static function createInstanceof($className, $filePath = null, $line = null)
    {
        $ref = new self(self::TYPE_INSTANCEOF, $className);
        if ($filePath !== null) {
            $ref->setFilePath($filePath);
        }
        if ($line !== null) {
            $ref->setLine($line);
        }
        return $ref;
    }

    /**
     * Create a type hint reference
     *
     * @param string $typeName Type name
     * @param string|null $filePath File path (optional)
     * @param int|null $line Line number (optional)
     *
     * @return Reference
     */
    public static function createTypeHint($typeName, $filePath = null, $line = null)
    {
        $ref = new self(self::TYPE_TYPE_HINT, $typeName);
        if ($filePath !== null) {
            $ref->setFilePath($filePath);
        }
        if ($line !== null) {
            $ref->setLine($line);
        }
        return $ref;
    }

    /**
     * Create a return type reference
     *
     * @param string $typeName Type name
     * @param string|null $filePath File path (optional)
     * @param int|null $line Line number (optional)
     *
     * @return Reference
     */
    public static function createReturnType($typeName, $filePath = null, $line = null)
    {
        $ref = new self(self::TYPE_RETURN_TYPE, $typeName);
        if ($filePath !== null) {
            $ref->setFilePath($filePath);
        }
        if ($line !== null) {
            $ref->setLine($line);
        }
        return $ref;
    }

    /**
     * Create a catch reference
     *
     * @param string $className Exception class name
     * @param string|null $filePath File path (optional)
     * @param int|null $line Line number (optional)
     *
     * @return Reference
     */
    public static function createCatch($className, $filePath = null, $line = null)
    {
        $ref = new self(self::TYPE_CATCH, $className);
        if ($filePath !== null) {
            $ref->setFilePath($filePath);
        }
        if ($line !== null) {
            $ref->setLine($line);
        }
        return $ref;
    }

    /**
     * Create a ::class string reference
     *
     * @param string $className Class name
     * @param string|null $filePath File path (optional)
     * @param int|null $line Line number (optional)
     *
     * @return Reference
     */
    public static function createClassString($className, $filePath = null, $line = null)
    {
        $ref = new self(self::TYPE_CLASS_STRING, $className);
        if ($filePath !== null) {
            $ref->setFilePath($filePath);
        }
        if ($line !== null) {
            $ref->setLine($line);
        }
        return $ref;
    }

    /**
     * Get unique identifier for this reference
     *
     * @return string
     */
    public function getId()
    {
        $parts = array($this->type, $this->symbolName);
        if ($this->symbolParent !== null) {
            $parts[] = $this->symbolParent;
        }
        if ($this->filePath !== null) {
            $parts[] = $this->filePath;
        }
        if ($this->line !== null) {
            $parts[] = $this->line;
        }
        return implode(':', $parts);
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
    public function getSymbolName()
    {
        return $this->symbolName;
    }

    /**
     * @return string|null
     */
    public function getSymbolParent()
    {
        return $this->symbolParent;
    }

    /**
     * @param string|null $parent
     * @return $this
     */
    public function setSymbolParent($parent)
    {
        $this->symbolParent = $parent;
        return $this;
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
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @param string|null $context
     * @return $this
     */
    public function setContext($context)
    {
        $this->context = $context;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDynamic()
    {
        return $this->isDynamic;
    }

    /**
     * @param bool $isDynamic
     * @return $this
     */
    public function setDynamic($isDynamic)
    {
        $this->isDynamic = $isDynamic;
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
     * Check if this is a class reference
     *
     * @return bool
     */
    public function isClassReference()
    {
        return in_array($this->type, array(
            self::TYPE_NEW,
            self::TYPE_EXTENDS,
            self::TYPE_IMPLEMENTS,
            self::TYPE_USE_TRAIT,
            self::TYPE_INSTANCEOF,
            self::TYPE_TYPE_HINT,
            self::TYPE_RETURN_TYPE,
            self::TYPE_CATCH,
            self::TYPE_CLASS_STRING,
        ), true);
    }

    /**
     * Check if this is a function/method reference
     *
     * @return bool
     */
    public function isCallReference()
    {
        return in_array($this->type, array(
            self::TYPE_FUNCTION_CALL,
            self::TYPE_METHOD_CALL,
            self::TYPE_STATIC_CALL,
        ), true);
    }

    /**
     * Convert to array representation
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            'type' => $this->type,
            'symbolName' => $this->symbolName,
            'symbolParent' => $this->symbolParent,
            'file' => $this->filePath,
            'line' => $this->line,
            'context' => $this->context,
            'isDynamic' => $this->isDynamic,
            'metadata' => $this->metadata,
        );
    }

    /**
     * Create Reference from array representation
     *
     * @param array $data Array data from toArray()
     *
     * @return Reference
     */
    public static function fromArray(array $data)
    {
        $ref = new self($data['type'], $data['symbolName']);

        if (isset($data['symbolParent'])) {
            $ref->symbolParent = $data['symbolParent'];
        }
        if (isset($data['file'])) {
            $ref->filePath = $data['file'];
        }
        if (isset($data['line'])) {
            $ref->line = $data['line'];
        }
        if (isset($data['context'])) {
            $ref->context = $data['context'];
        }
        if (isset($data['isDynamic'])) {
            $ref->isDynamic = $data['isDynamic'];
        }
        if (isset($data['metadata'])) {
            $ref->metadata = $data['metadata'];
        }

        return $ref;
    }
}
