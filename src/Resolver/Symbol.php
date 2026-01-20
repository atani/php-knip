<?php
/**
 * Symbol
 *
 * Represents a PHP symbol (class, method, function, constant, etc.)
 */

namespace PhpKnip\Resolver;

/**
 * Represents a code symbol that can be analyzed for usage
 */
class Symbol
{
    /**
     * Symbol types
     */
    const TYPE_CLASS = 'class';
    const TYPE_INTERFACE = 'interface';
    const TYPE_TRAIT = 'trait';
    const TYPE_ENUM = 'enum';
    const TYPE_FUNCTION = 'function';
    const TYPE_METHOD = 'method';
    const TYPE_PROPERTY = 'property';
    const TYPE_CONSTANT = 'constant';
    const TYPE_CLASS_CONSTANT = 'class_constant';

    /**
     * Visibility modifiers
     */
    const VISIBILITY_PUBLIC = 'public';
    const VISIBILITY_PROTECTED = 'protected';
    const VISIBILITY_PRIVATE = 'private';

    /**
     * @var string Symbol type (one of TYPE_* constants)
     */
    private $type;

    /**
     * @var string Symbol name (short name without namespace)
     */
    private $name;

    /**
     * @var string|null Fully qualified name (with namespace)
     */
    private $fullyQualifiedName;

    /**
     * @var string|null Namespace
     */
    private $namespace;

    /**
     * @var string|null Parent class/interface name (for methods, properties, constants)
     */
    private $parent;

    /**
     * @var string|null Visibility (public, protected, private)
     */
    private $visibility;

    /**
     * @var bool Whether symbol is static
     */
    private $isStatic = false;

    /**
     * @var bool Whether symbol is abstract
     */
    private $isAbstract = false;

    /**
     * @var bool Whether symbol is final
     */
    private $isFinal = false;

    /**
     * @var string|null File path where symbol is defined
     */
    private $filePath;

    /**
     * @var int|null Start line number
     */
    private $startLine;

    /**
     * @var int|null End line number
     */
    private $endLine;

    /**
     * @var array Extended classes (for class symbols)
     */
    private $extends = array();

    /**
     * @var array Implemented interfaces (for class symbols)
     */
    private $implements = array();

    /**
     * @var array Used traits (for class symbols)
     */
    private $uses = array();

    /**
     * @var array Additional metadata
     */
    private $metadata = array();

    /**
     * Constructor
     *
     * @param string $type Symbol type
     * @param string $name Symbol name
     */
    public function __construct($type, $name)
    {
        $this->type = $type;
        $this->name = $name;
    }

    /**
     * Create a class symbol
     *
     * @param string $name Class name
     * @param string|null $namespace Namespace
     * @param string|null $filePath File path (optional)
     * @param int|null $line Start line (optional)
     *
     * @return Symbol
     */
    public static function createClass($name, $namespace = null, $filePath = null, $line = null)
    {
        $symbol = new self(self::TYPE_CLASS, $name);
        $symbol->setNamespace($namespace);
        if ($filePath !== null) {
            $symbol->setFilePath($filePath);
        }
        if ($line !== null) {
            $symbol->setStartLine($line);
        }
        return $symbol;
    }

    /**
     * Create an interface symbol
     *
     * @param string $name Interface name
     * @param string|null $namespace Namespace
     * @param string|null $filePath File path (optional)
     * @param int|null $line Start line (optional)
     *
     * @return Symbol
     */
    public static function createInterface($name, $namespace = null, $filePath = null, $line = null)
    {
        $symbol = new self(self::TYPE_INTERFACE, $name);
        $symbol->setNamespace($namespace);
        if ($filePath !== null) {
            $symbol->setFilePath($filePath);
        }
        if ($line !== null) {
            $symbol->setStartLine($line);
        }
        return $symbol;
    }

    /**
     * Create a trait symbol
     *
     * @param string $name Trait name
     * @param string|null $namespace Namespace
     * @param string|null $filePath File path (optional)
     * @param int|null $line Start line (optional)
     *
     * @return Symbol
     */
    public static function createTrait($name, $namespace = null, $filePath = null, $line = null)
    {
        $symbol = new self(self::TYPE_TRAIT, $name);
        $symbol->setNamespace($namespace);
        if ($filePath !== null) {
            $symbol->setFilePath($filePath);
        }
        if ($line !== null) {
            $symbol->setStartLine($line);
        }
        return $symbol;
    }

    /**
     * Create a function symbol
     *
     * @param string $name Function name
     * @param string|null $namespace Namespace
     * @param string|null $filePath File path (optional)
     * @param int|null $line Start line (optional)
     *
     * @return Symbol
     */
    public static function createFunction($name, $namespace = null, $filePath = null, $line = null)
    {
        $symbol = new self(self::TYPE_FUNCTION, $name);
        $symbol->setNamespace($namespace);
        if ($filePath !== null) {
            $symbol->setFilePath($filePath);
        }
        if ($line !== null) {
            $symbol->setStartLine($line);
        }
        return $symbol;
    }

    /**
     * Create a method symbol
     *
     * @param string $name Method name
     * @param string $className Parent class name
     * @param string $visibility Visibility
     *
     * @return Symbol
     */
    public static function createMethod($name, $className, $visibility = self::VISIBILITY_PUBLIC)
    {
        $symbol = new self(self::TYPE_METHOD, $name);
        $symbol->setParent($className);
        $symbol->setVisibility($visibility);
        return $symbol;
    }

    /**
     * Create a property symbol
     *
     * @param string $name Property name
     * @param string $className Parent class name
     * @param string $visibility Visibility
     *
     * @return Symbol
     */
    public static function createProperty($name, $className, $visibility = self::VISIBILITY_PUBLIC)
    {
        $symbol = new self(self::TYPE_PROPERTY, $name);
        $symbol->setParent($className);
        $symbol->setVisibility($visibility);
        return $symbol;
    }

    /**
     * Create a constant symbol
     *
     * @param string $name Constant name
     * @param string|null $namespace Namespace
     * @param string|null $filePath File path (optional)
     * @param int|null $line Start line (optional)
     *
     * @return Symbol
     */
    public static function createConstant($name, $namespace = null, $filePath = null, $line = null)
    {
        $symbol = new self(self::TYPE_CONSTANT, $name);
        $symbol->setNamespace($namespace);
        if ($filePath !== null) {
            $symbol->setFilePath($filePath);
        }
        if ($line !== null) {
            $symbol->setStartLine($line);
        }
        return $symbol;
    }

    /**
     * Create a class constant symbol
     *
     * @param string $name Constant name
     * @param string $className Parent class name
     *
     * @return Symbol
     */
    public static function createClassConstant($name, $className)
    {
        $symbol = new self(self::TYPE_CLASS_CONSTANT, $name);
        $symbol->setParent($className);
        return $symbol;
    }

    /**
     * Get unique identifier for this symbol
     *
     * @return string
     */
    public function getId()
    {
        if ($this->parent !== null) {
            return $this->type . ':' . $this->parent . '::' . $this->name;
        }

        if ($this->fullyQualifiedName !== null) {
            return $this->type . ':' . $this->fullyQualifiedName;
        }

        return $this->type . ':' . $this->name;
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
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string|null
     */
    public function getFullyQualifiedName()
    {
        return $this->fullyQualifiedName;
    }

    /**
     * @param string|null $fqn
     * @return $this
     */
    public function setFullyQualifiedName($fqn)
    {
        $this->fullyQualifiedName = $fqn;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @param string|null $namespace
     * @return $this
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
        if ($namespace !== null && $namespace !== '') {
            $this->fullyQualifiedName = $namespace . '\\' . $this->name;
        } else {
            $this->fullyQualifiedName = $this->name;
        }
        return $this;
    }

    /**
     * @return string|null
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param string|null $parent
     * @return $this
     */
    public function setParent($parent)
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getVisibility()
    {
        return $this->visibility;
    }

    /**
     * @param string|null $visibility
     * @return $this
     */
    public function setVisibility($visibility)
    {
        $this->visibility = $visibility;
        return $this;
    }

    /**
     * @return bool
     */
    public function isStatic()
    {
        return $this->isStatic;
    }

    /**
     * @param bool $isStatic
     * @return $this
     */
    public function setStatic($isStatic)
    {
        $this->isStatic = $isStatic;
        return $this;
    }

    /**
     * @return bool
     */
    public function isAbstract()
    {
        return $this->isAbstract;
    }

    /**
     * @param bool $isAbstract
     * @return $this
     */
    public function setAbstract($isAbstract)
    {
        $this->isAbstract = $isAbstract;
        return $this;
    }

    /**
     * @return bool
     */
    public function isFinal()
    {
        return $this->isFinal;
    }

    /**
     * @param bool $isFinal
     * @return $this
     */
    public function setFinal($isFinal)
    {
        $this->isFinal = $isFinal;
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
    public function getStartLine()
    {
        return $this->startLine;
    }

    /**
     * @param int|null $startLine
     * @return $this
     */
    public function setStartLine($startLine)
    {
        $this->startLine = $startLine;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getEndLine()
    {
        return $this->endLine;
    }

    /**
     * @param int|null $endLine
     * @return $this
     */
    public function setEndLine($endLine)
    {
        $this->endLine = $endLine;
        return $this;
    }

    /**
     * @return array
     */
    public function getExtends()
    {
        return $this->extends;
    }

    /**
     * @param array $extends
     * @return $this
     */
    public function setExtends(array $extends)
    {
        $this->extends = $extends;
        return $this;
    }

    /**
     * @return array
     */
    public function getImplements()
    {
        return $this->implements;
    }

    /**
     * @param array $implements
     * @return $this
     */
    public function setImplements(array $implements)
    {
        $this->implements = $implements;
        return $this;
    }

    /**
     * @return array
     */
    public function getUses()
    {
        return $this->uses;
    }

    /**
     * @param array $uses
     * @return $this
     */
    public function setUses(array $uses)
    {
        $this->uses = $uses;
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
     * Check if this is a class-like symbol (class, interface, trait, enum)
     *
     * @return bool
     */
    public function isClassLike()
    {
        return in_array($this->type, array(
            self::TYPE_CLASS,
            self::TYPE_INTERFACE,
            self::TYPE_TRAIT,
            self::TYPE_ENUM,
        ), true);
    }

    /**
     * Check if this is a member symbol (method, property, class constant)
     *
     * @return bool
     */
    public function isMember()
    {
        return in_array($this->type, array(
            self::TYPE_METHOD,
            self::TYPE_PROPERTY,
            self::TYPE_CLASS_CONSTANT,
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
            'name' => $this->name,
            'fqn' => $this->fullyQualifiedName,
            'namespace' => $this->namespace,
            'parent' => $this->parent,
            'visibility' => $this->visibility,
            'isStatic' => $this->isStatic,
            'isAbstract' => $this->isAbstract,
            'isFinal' => $this->isFinal,
            'file' => $this->filePath,
            'startLine' => $this->startLine,
            'endLine' => $this->endLine,
            'extends' => $this->extends,
            'implements' => $this->implements,
            'uses' => $this->uses,
            'metadata' => $this->metadata,
        );
    }

    /**
     * Create Symbol from array representation
     *
     * @param array $data Array data from toArray()
     *
     * @return Symbol
     */
    public static function fromArray(array $data)
    {
        $symbol = new self($data['type'], $data['name']);

        if (isset($data['fqn'])) {
            $symbol->fullyQualifiedName = $data['fqn'];
        }
        if (isset($data['namespace'])) {
            $symbol->namespace = $data['namespace'];
        }
        if (isset($data['parent'])) {
            $symbol->parent = $data['parent'];
        }
        if (isset($data['visibility'])) {
            $symbol->visibility = $data['visibility'];
        }
        if (isset($data['isStatic'])) {
            $symbol->isStatic = $data['isStatic'];
        }
        if (isset($data['isAbstract'])) {
            $symbol->isAbstract = $data['isAbstract'];
        }
        if (isset($data['isFinal'])) {
            $symbol->isFinal = $data['isFinal'];
        }
        if (isset($data['file'])) {
            $symbol->filePath = $data['file'];
        }
        if (isset($data['startLine'])) {
            $symbol->startLine = $data['startLine'];
        }
        if (isset($data['endLine'])) {
            $symbol->endLine = $data['endLine'];
        }
        if (isset($data['extends'])) {
            $symbol->extends = $data['extends'];
        }
        if (isset($data['implements'])) {
            $symbol->implements = $data['implements'];
        }
        if (isset($data['uses'])) {
            $symbol->uses = $data['uses'];
        }
        if (isset($data['metadata'])) {
            $symbol->metadata = $data['metadata'];
        }

        return $symbol;
    }
}
