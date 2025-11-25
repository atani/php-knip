<?php
/**
 * Analysis Context
 *
 * Provides context for analyzers
 */

namespace PhpKnip\Analyzer;

use PhpKnip\Resolver\SymbolTable;
use PhpKnip\Resolver\Reference;

/**
 * Context object for analysis containing symbols and references
 */
class AnalysisContext
{
    /**
     * @var SymbolTable
     */
    private $symbolTable;

    /**
     * @var array<Reference>
     */
    private $references;

    /**
     * @var array Configuration
     */
    private $config;

    /**
     * @var array<string, array> Use statements by file
     */
    private $useStatements = array();

    /**
     * Constructor
     *
     * @param SymbolTable $symbolTable Symbol table
     * @param array $references References
     * @param array $config Configuration
     */
    public function __construct(SymbolTable $symbolTable, array $references = array(), array $config = array())
    {
        $this->symbolTable = $symbolTable;
        $this->references = $references;
        $this->config = $config;
    }

    /**
     * Get symbol table
     *
     * @return SymbolTable
     */
    public function getSymbolTable()
    {
        return $this->symbolTable;
    }

    /**
     * Get all references
     *
     * @return array<Reference>
     */
    public function getReferences()
    {
        return $this->references;
    }

    /**
     * Get references by type
     *
     * @param string $type Reference type
     *
     * @return array<Reference>
     */
    public function getReferencesByType($type)
    {
        return array_filter($this->references, function ($ref) use ($type) {
            return $ref->getType() === $type;
        });
    }

    /**
     * Get references to a symbol
     *
     * @param string $symbolName Symbol name
     *
     * @return array<Reference>
     */
    public function getReferencesToSymbol($symbolName)
    {
        return array_filter($this->references, function ($ref) use ($symbolName) {
            return $ref->getSymbolName() === $symbolName;
        });
    }

    /**
     * Check if symbol is referenced
     *
     * @param string $symbolName Symbol name (FQN)
     * @param array $refTypes Reference types to check (null for all)
     *
     * @return bool
     */
    public function isSymbolReferenced($symbolName, array $refTypes = null)
    {
        foreach ($this->references as $ref) {
            if ($refTypes !== null && !in_array($ref->getType(), $refTypes, true)) {
                continue;
            }

            if ($ref->getSymbolName() === $symbolName) {
                return true;
            }

            // Also check short name
            $shortName = $this->getShortName($symbolName);
            if ($ref->getSymbolName() === $shortName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Get configuration value
     *
     * @param string $key Configuration key
     * @param mixed $default Default value
     *
     * @return mixed
     */
    public function getConfigValue($key, $default = null)
    {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }

    /**
     * Set use statements for a file
     *
     * @param string $filePath File path
     * @param array $useStatements Use statements
     */
    public function setUseStatements($filePath, array $useStatements)
    {
        $this->useStatements[$filePath] = $useStatements;
    }

    /**
     * Get use statements for a file
     *
     * @param string $filePath File path
     *
     * @return array
     */
    public function getUseStatements($filePath)
    {
        return isset($this->useStatements[$filePath]) ? $this->useStatements[$filePath] : array();
    }

    /**
     * Get all use statements
     *
     * @return array
     */
    public function getAllUseStatements()
    {
        return $this->useStatements;
    }

    /**
     * Get short name from FQN
     *
     * @param string $fqn Fully qualified name
     *
     * @return string Short name
     */
    private function getShortName($fqn)
    {
        $pos = strrpos($fqn, '\\');
        return $pos !== false ? substr($fqn, $pos + 1) : $fqn;
    }

    /**
     * Add references
     *
     * @param array $references References to add
     */
    public function addReferences(array $references)
    {
        $this->references = array_merge($this->references, $references);
    }
}
