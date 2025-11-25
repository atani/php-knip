<?php
/**
 * Symbol Table
 *
 * Manages a collection of symbols from the codebase
 */

namespace PhpKnip\Resolver;

/**
 * Stores and indexes all symbols found in the codebase
 */
class SymbolTable
{
    /**
     * @var array<string, Symbol> Symbols indexed by ID
     */
    private $symbols = array();

    /**
     * @var array<string, array<string>> Symbols indexed by type
     */
    private $byType = array();

    /**
     * @var array<string, array<string>> Symbols indexed by file
     */
    private $byFile = array();

    /**
     * @var array<string, array<string>> Symbols indexed by namespace
     */
    private $byNamespace = array();

    /**
     * @var array<string, array<string>> Class members indexed by parent class
     */
    private $byParent = array();

    /**
     * Add a symbol to the table
     *
     * @param Symbol $symbol Symbol to add
     *
     * @return $this
     */
    public function add(Symbol $symbol)
    {
        $id = $symbol->getId();
        $this->symbols[$id] = $symbol;

        // Index by type
        $type = $symbol->getType();
        if (!isset($this->byType[$type])) {
            $this->byType[$type] = array();
        }
        $this->byType[$type][] = $id;

        // Index by file
        $file = $symbol->getFilePath();
        if ($file !== null) {
            if (!isset($this->byFile[$file])) {
                $this->byFile[$file] = array();
            }
            $this->byFile[$file][] = $id;
        }

        // Index by namespace
        $namespace = $symbol->getNamespace();
        $nsKey = $namespace !== null ? $namespace : '';
        if (!isset($this->byNamespace[$nsKey])) {
            $this->byNamespace[$nsKey] = array();
        }
        $this->byNamespace[$nsKey][] = $id;

        // Index by parent (for class members)
        $parent = $symbol->getParent();
        if ($parent !== null) {
            if (!isset($this->byParent[$parent])) {
                $this->byParent[$parent] = array();
            }
            $this->byParent[$parent][] = $id;
        }

        return $this;
    }

    /**
     * Add multiple symbols
     *
     * @param array<Symbol> $symbols Symbols to add
     *
     * @return $this
     */
    public function addAll(array $symbols)
    {
        foreach ($symbols as $symbol) {
            $this->add($symbol);
        }
        return $this;
    }

    /**
     * Get symbol by ID
     *
     * @param string $id Symbol ID
     *
     * @return Symbol|null
     */
    public function get($id)
    {
        return isset($this->symbols[$id]) ? $this->symbols[$id] : null;
    }

    /**
     * Check if symbol exists
     *
     * @param string $id Symbol ID
     *
     * @return bool
     */
    public function has($id)
    {
        return isset($this->symbols[$id]);
    }

    /**
     * Remove symbol by ID
     *
     * @param string $id Symbol ID
     *
     * @return $this
     */
    public function remove($id)
    {
        if (isset($this->symbols[$id])) {
            $symbol = $this->symbols[$id];
            unset($this->symbols[$id]);

            // Remove from type index
            $type = $symbol->getType();
            if (isset($this->byType[$type])) {
                $this->byType[$type] = array_values(array_diff($this->byType[$type], array($id)));
            }

            // Remove from file index
            $file = $symbol->getFilePath();
            if ($file !== null && isset($this->byFile[$file])) {
                $this->byFile[$file] = array_values(array_diff($this->byFile[$file], array($id)));
            }

            // Remove from namespace index
            $namespace = $symbol->getNamespace();
            $nsKey = $namespace !== null ? $namespace : '';
            if (isset($this->byNamespace[$nsKey])) {
                $this->byNamespace[$nsKey] = array_values(array_diff($this->byNamespace[$nsKey], array($id)));
            }

            // Remove from parent index
            $parent = $symbol->getParent();
            if ($parent !== null && isset($this->byParent[$parent])) {
                $this->byParent[$parent] = array_values(array_diff($this->byParent[$parent], array($id)));
            }
        }

        return $this;
    }

    /**
     * Get all symbols
     *
     * @return array<Symbol>
     */
    public function getAll()
    {
        return array_values($this->symbols);
    }

    /**
     * Get symbols by type
     *
     * @param string $type Symbol type
     *
     * @return array<Symbol>
     */
    public function getByType($type)
    {
        if (!isset($this->byType[$type])) {
            return array();
        }

        return $this->getSymbolsByIds($this->byType[$type]);
    }

    /**
     * Get symbols by file
     *
     * @param string $filePath File path
     *
     * @return array<Symbol>
     */
    public function getByFile($filePath)
    {
        if (!isset($this->byFile[$filePath])) {
            return array();
        }

        return $this->getSymbolsByIds($this->byFile[$filePath]);
    }

    /**
     * Get symbols by namespace
     *
     * @param string|null $namespace Namespace (null for global)
     *
     * @return array<Symbol>
     */
    public function getByNamespace($namespace)
    {
        $nsKey = $namespace !== null ? $namespace : '';
        if (!isset($this->byNamespace[$nsKey])) {
            return array();
        }

        return $this->getSymbolsByIds($this->byNamespace[$nsKey]);
    }

    /**
     * Get class members
     *
     * @param string $className Fully qualified class name
     *
     * @return array<Symbol>
     */
    public function getClassMembers($className)
    {
        if (!isset($this->byParent[$className])) {
            return array();
        }

        return $this->getSymbolsByIds($this->byParent[$className]);
    }

    /**
     * Get all classes
     *
     * @return array<Symbol>
     */
    public function getClasses()
    {
        return $this->getByType(Symbol::TYPE_CLASS);
    }

    /**
     * Get all interfaces
     *
     * @return array<Symbol>
     */
    public function getInterfaces()
    {
        return $this->getByType(Symbol::TYPE_INTERFACE);
    }

    /**
     * Get all traits
     *
     * @return array<Symbol>
     */
    public function getTraits()
    {
        return $this->getByType(Symbol::TYPE_TRAIT);
    }

    /**
     * Get all functions
     *
     * @return array<Symbol>
     */
    public function getFunctions()
    {
        return $this->getByType(Symbol::TYPE_FUNCTION);
    }

    /**
     * Get all constants
     *
     * @return array<Symbol>
     */
    public function getConstants()
    {
        return $this->getByType(Symbol::TYPE_CONSTANT);
    }

    /**
     * Find symbol by fully qualified name
     *
     * @param string $fqn Fully qualified name
     * @param string|null $type Optional type filter
     *
     * @return Symbol|null
     */
    public function findByFqn($fqn, $type = null)
    {
        foreach ($this->symbols as $symbol) {
            if ($symbol->getFullyQualifiedName() === $fqn) {
                if ($type === null || $symbol->getType() === $type) {
                    return $symbol;
                }
            }
        }

        return null;
    }

    /**
     * Find class by name (supports both short and fully qualified)
     *
     * @param string $name Class name
     *
     * @return Symbol|null
     */
    public function findClass($name)
    {
        // Try fully qualified name first
        $symbol = $this->findByFqn($name, Symbol::TYPE_CLASS);
        if ($symbol !== null) {
            return $symbol;
        }

        // Try short name
        foreach ($this->getClasses() as $class) {
            if ($class->getName() === $name) {
                return $class;
            }
        }

        return null;
    }

    /**
     * Find function by name
     *
     * @param string $name Function name
     *
     * @return Symbol|null
     */
    public function findFunction($name)
    {
        // Try fully qualified name first
        $symbol = $this->findByFqn($name, Symbol::TYPE_FUNCTION);
        if ($symbol !== null) {
            return $symbol;
        }

        // Try short name
        foreach ($this->getFunctions() as $function) {
            if ($function->getName() === $name) {
                return $function;
            }
        }

        return null;
    }

    /**
     * Find method in class
     *
     * @param string $className Class name
     * @param string $methodName Method name
     *
     * @return Symbol|null
     */
    public function findMethod($className, $methodName)
    {
        $members = $this->getClassMembers($className);

        foreach ($members as $member) {
            if ($member->getType() === Symbol::TYPE_METHOD && $member->getName() === $methodName) {
                return $member;
            }
        }

        return null;
    }

    /**
     * Get count of all symbols
     *
     * @return int
     */
    public function count()
    {
        return count($this->symbols);
    }

    /**
     * Get count by type
     *
     * @param string $type Symbol type
     *
     * @return int
     */
    public function countByType($type)
    {
        return isset($this->byType[$type]) ? count($this->byType[$type]) : 0;
    }

    /**
     * Get statistics
     *
     * @return array
     */
    public function getStats()
    {
        $stats = array(
            'total' => $this->count(),
            'byType' => array(),
            'files' => count($this->byFile),
            'namespaces' => count($this->byNamespace),
        );

        foreach ($this->byType as $type => $ids) {
            $stats['byType'][$type] = count($ids);
        }

        return $stats;
    }

    /**
     * Clear all symbols
     *
     * @return $this
     */
    public function clear()
    {
        $this->symbols = array();
        $this->byType = array();
        $this->byFile = array();
        $this->byNamespace = array();
        $this->byParent = array();

        return $this;
    }

    /**
     * Merge another symbol table into this one
     *
     * @param SymbolTable $other Symbol table to merge
     *
     * @return $this
     */
    public function merge(SymbolTable $other)
    {
        foreach ($other->getAll() as $symbol) {
            $this->add($symbol);
        }

        return $this;
    }

    /**
     * Get symbols by IDs
     *
     * @param array<string> $ids Symbol IDs
     *
     * @return array<Symbol>
     */
    private function getSymbolsByIds(array $ids)
    {
        $result = array();
        foreach ($ids as $id) {
            if (isset($this->symbols[$id])) {
                $result[] = $this->symbols[$id];
            }
        }
        return $result;
    }

    /**
     * Get all unique namespaces
     *
     * @return array<string>
     */
    public function getNamespaces()
    {
        return array_keys($this->byNamespace);
    }

    /**
     * Get all file paths
     *
     * @return array<string>
     */
    public function getFiles()
    {
        return array_keys($this->byFile);
    }
}
