<?php
/**
 * Constant Analyzer
 *
 * Detects unused constants (global and class constants)
 */

namespace PhpKnip\Analyzer;

use PhpKnip\Resolver\Symbol;
use PhpKnip\Resolver\Reference;

/**
 * Analyzes for unused constants
 */
class ConstantAnalyzer implements AnalyzerInterface
{
    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'constant-analyzer';
    }

    /**
     * @inheritDoc
     */
    public function analyze(AnalysisContext $context)
    {
        $issues = array();

        // Check for unused global constants
        $issues = array_merge($issues, $this->analyzeGlobalConstants($context));

        // Check for unused class constants
        $issues = array_merge($issues, $this->analyzeClassConstants($context));

        return $issues;
    }

    /**
     * Analyze global constants
     *
     * @param AnalysisContext $context Analysis context
     *
     * @return array<Issue>
     */
    private function analyzeGlobalConstants(AnalysisContext $context)
    {
        $issues = array();
        $symbolTable = $context->getSymbolTable();
        $references = $context->getReferences();

        // Build set of referenced constant names
        $referencedConstants = $this->buildReferencedConstantSet($references);

        foreach ($symbolTable->getConstants() as $constant) {
            $name = $constant->getName();
            $fqn = $constant->getFullyQualifiedName();

            // Skip if referenced
            if ($this->isConstantReferenced($name, $fqn, $referencedConstants)) {
                continue;
            }

            // Skip if ignored by config or plugin
            if ($this->shouldIgnore($constant, $context)) {
                continue;
            }

            $issues[] = Issue::unusedConstant(
                $fqn !== null ? $fqn : $name,
                $constant->getFilePath(),
                $constant->getStartLine()
            );
        }

        return $issues;
    }

    /**
     * Analyze class constants
     *
     * @param AnalysisContext $context Analysis context
     *
     * @return array<Issue>
     */
    private function analyzeClassConstants(AnalysisContext $context)
    {
        $issues = array();
        $symbolTable = $context->getSymbolTable();
        $references = $context->getReferences();

        // Build set of referenced class constants
        $referencedConstants = $this->buildReferencedClassConstantSet($references);

        // Get all class constants
        $classConstants = $symbolTable->getByType(Symbol::TYPE_CLASS_CONSTANT);

        foreach ($classConstants as $constant) {
            $constantName = $constant->getName();
            $className = $constant->getParent();

            // Skip if no parent class (should not happen for class constants)
            if ($className === null) {
                continue;
            }

            $fullName = $className . '::' . $constantName;

            // Skip if referenced
            if ($this->isClassConstantReferenced($constantName, $className, $referencedConstants)) {
                continue;
            }

            // Skip if ignored by config or plugin
            if ($this->shouldIgnore($constant, $context)) {
                continue;
            }

            $issues[] = Issue::unusedConstant(
                $fullName,
                $constant->getFilePath(),
                $constant->getStartLine()
            );
        }

        return $issues;
    }

    /**
     * Build set of referenced global constant names
     *
     * @param array $references All references
     *
     * @return array
     */
    private function buildReferencedConstantSet(array $references)
    {
        $set = array();

        foreach ($references as $ref) {
            if ($ref->getType() === Reference::TYPE_CONSTANT) {
                // Global constant (no parent)
                if ($ref->getSymbolParent() === null) {
                    $name = $ref->getSymbolName();
                    $set[$name] = true;
                }
            }
        }

        return $set;
    }

    /**
     * Build set of referenced class constant names
     *
     * @param array $references All references
     *
     * @return array
     */
    private function buildReferencedClassConstantSet(array $references)
    {
        $set = array();

        foreach ($references as $ref) {
            if ($ref->getType() === Reference::TYPE_CONSTANT) {
                $parent = $ref->getSymbolParent();
                // Class constant (has parent)
                if ($parent !== null) {
                    $name = $ref->getSymbolName();
                    // Store both full name and just constant name
                    $set[$parent . '::' . $name] = true;
                    $set[$name] = true;
                }
            }
        }

        return $set;
    }

    /**
     * Check if global constant is referenced
     *
     * @param string $name Short name
     * @param string|null $fqn Fully qualified name
     * @param array $referencedConstants Referenced constants set
     *
     * @return bool
     */
    private function isConstantReferenced($name, $fqn, array $referencedConstants)
    {
        if (isset($referencedConstants[$name])) {
            return true;
        }

        if ($fqn !== null && isset($referencedConstants[$fqn])) {
            return true;
        }

        return false;
    }

    /**
     * Check if class constant is referenced
     *
     * @param string $constantName Constant name
     * @param string $className Class name
     * @param array $referencedConstants Referenced constants set
     *
     * @return bool
     */
    private function isClassConstantReferenced($constantName, $className, array $referencedConstants)
    {
        $fullName = $className . '::' . $constantName;

        if (isset($referencedConstants[$fullName])) {
            return true;
        }

        // Also check with just constant name (for self::CONST, static::CONST)
        if (isset($referencedConstants[$constantName])) {
            return true;
        }

        // Check short class name
        $shortClassName = $this->getShortName($className);
        $shortFullName = $shortClassName . '::' . $constantName;
        if (isset($referencedConstants[$shortFullName])) {
            return true;
        }

        return false;
    }

    /**
     * Check if constant should be ignored
     *
     * @param Symbol $symbol Constant symbol
     * @param AnalysisContext $context Analysis context
     *
     * @return bool
     */
    private function shouldIgnore(Symbol $symbol, AnalysisContext $context)
    {
        $name = $symbol->getName();
        $fqn = $symbol->getFullyQualifiedName();
        $parent = $symbol->getParent();

        // Build full name for class constants
        $fullName = $parent !== null ? $parent . '::' . $name : ($fqn !== null ? $fqn : $name);

        // Check plugin patterns
        if ($context->shouldPluginIgnoreSymbol($fullName) ||
            $context->shouldPluginIgnoreSymbol($name)) {
            return true;
        }

        // Check config patterns
        $ignorePatterns = $context->getConfigValue('ignore', array());
        $symbolPatterns = isset($ignorePatterns['symbols']) ? $ignorePatterns['symbols'] : array();

        foreach ($symbolPatterns as $pattern) {
            if ($this->matchesPattern($fullName, $pattern) ||
                $this->matchesPattern($name, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if name matches a glob pattern
     *
     * @param string $name Name to check
     * @param string $pattern Glob pattern
     *
     * @return bool
     */
    private function matchesPattern($name, $pattern)
    {
        $regex = '/^' . str_replace(
            array('\\*', '\\?'),
            array('.*', '.'),
            preg_quote($pattern, '/')
        ) . '$/';

        return preg_match($regex, $name) === 1;
    }

    /**
     * Get short name from fully qualified name
     *
     * @param string $fqn Fully qualified name
     *
     * @return string
     */
    private function getShortName($fqn)
    {
        $parts = explode('\\', $fqn);
        return end($parts);
    }
}
