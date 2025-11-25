<?php
/**
 * Class Analyzer
 *
 * Detects unused classes, interfaces, and traits
 */

namespace PhpKnip\Analyzer;

use PhpKnip\Resolver\Symbol;
use PhpKnip\Resolver\Reference;

/**
 * Analyzes for unused classes, interfaces, and traits
 */
class ClassAnalyzer implements AnalyzerInterface
{
    /**
     * Reference types that indicate class usage
     */
    private static $classReferenceTypes = array(
        Reference::TYPE_NEW,
        Reference::TYPE_EXTENDS,
        Reference::TYPE_IMPLEMENTS,
        Reference::TYPE_USE_TRAIT,
        Reference::TYPE_STATIC_CALL,
        Reference::TYPE_STATIC_PROPERTY,
        Reference::TYPE_CONSTANT,
        Reference::TYPE_INSTANCEOF,
        Reference::TYPE_TYPE_HINT,
        Reference::TYPE_RETURN_TYPE,
        Reference::TYPE_CATCH,
        Reference::TYPE_CLASS_STRING,
    );

    /**
     * Magic methods that should not be flagged as unused
     */
    private static $magicMethods = array(
        '__construct',
        '__destruct',
        '__call',
        '__callStatic',
        '__get',
        '__set',
        '__isset',
        '__unset',
        '__sleep',
        '__wakeup',
        '__serialize',
        '__unserialize',
        '__toString',
        '__invoke',
        '__set_state',
        '__clone',
        '__debugInfo',
    );

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'class-analyzer';
    }

    /**
     * @inheritDoc
     */
    public function analyze(AnalysisContext $context)
    {
        $issues = array();

        // Check for unused classes
        $issues = array_merge($issues, $this->analyzeClasses($context));

        // Check for unused interfaces
        $issues = array_merge($issues, $this->analyzeInterfaces($context));

        // Check for unused traits
        $issues = array_merge($issues, $this->analyzeTraits($context));

        return $issues;
    }

    /**
     * Analyze classes for unused ones
     *
     * @param AnalysisContext $context Analysis context
     *
     * @return array<Issue>
     */
    private function analyzeClasses(AnalysisContext $context)
    {
        $issues = array();
        $symbolTable = $context->getSymbolTable();
        $references = $context->getReferences();

        // Build set of referenced class names
        $referencedClasses = $this->buildReferencedClassSet($references);

        foreach ($symbolTable->getClasses() as $class) {
            $fqn = $class->getFullyQualifiedName();
            $shortName = $class->getName();

            // Skip if referenced
            if ($this->isClassReferenced($fqn, $shortName, $referencedClasses)) {
                continue;
            }

            // Skip abstract classes that might be extended
            if ($class->isAbstract()) {
                if ($this->hasSubclasses($fqn, $references)) {
                    continue;
                }
            }

            // Skip if class has special annotations
            if ($this->shouldIgnore($class, $context)) {
                continue;
            }

            $issues[] = Issue::unusedClass(
                $fqn,
                $class->getFilePath(),
                $class->getStartLine()
            );
        }

        return $issues;
    }

    /**
     * Analyze interfaces for unused ones
     *
     * @param AnalysisContext $context Analysis context
     *
     * @return array<Issue>
     */
    private function analyzeInterfaces(AnalysisContext $context)
    {
        $issues = array();
        $symbolTable = $context->getSymbolTable();
        $references = $context->getReferences();

        // Build set of implemented interfaces
        $implementedInterfaces = $this->buildImplementedInterfaceSet($references);

        foreach ($symbolTable->getInterfaces() as $interface) {
            $fqn = $interface->getFullyQualifiedName();
            $shortName = $interface->getName();

            // Skip if implemented
            if ($this->isClassReferenced($fqn, $shortName, $implementedInterfaces)) {
                continue;
            }

            // Also check type hints and return types
            $typeRefs = $this->buildTypeReferenceSet($references);
            if ($this->isClassReferenced($fqn, $shortName, $typeRefs)) {
                continue;
            }

            // Skip if interface extends this interface
            if ($this->hasSubinterfaces($fqn, $references)) {
                continue;
            }

            // Skip if ignored
            if ($this->shouldIgnore($interface, $context)) {
                continue;
            }

            $issues[] = Issue::unusedInterface(
                $fqn,
                $interface->getFilePath(),
                $interface->getStartLine()
            );
        }

        return $issues;
    }

    /**
     * Analyze traits for unused ones
     *
     * @param AnalysisContext $context Analysis context
     *
     * @return array<Issue>
     */
    private function analyzeTraits(AnalysisContext $context)
    {
        $issues = array();
        $symbolTable = $context->getSymbolTable();
        $references = $context->getReferences();

        // Build set of used traits
        $usedTraits = $this->buildUsedTraitSet($references);

        foreach ($symbolTable->getTraits() as $trait) {
            $fqn = $trait->getFullyQualifiedName();
            $shortName = $trait->getName();

            // Skip if used
            if ($this->isClassReferenced($fqn, $shortName, $usedTraits)) {
                continue;
            }

            // Skip if ignored
            if ($this->shouldIgnore($trait, $context)) {
                continue;
            }

            $issues[] = Issue::unusedTrait(
                $fqn,
                $trait->getFilePath(),
                $trait->getStartLine()
            );
        }

        return $issues;
    }

    /**
     * Build set of referenced class names
     *
     * @param array $references References
     *
     * @return array
     */
    private function buildReferencedClassSet(array $references)
    {
        $set = array();

        foreach ($references as $ref) {
            if (in_array($ref->getType(), self::$classReferenceTypes, true)) {
                // For static calls, the class name is in symbolParent
                if ($ref->getType() === Reference::TYPE_STATIC_CALL) {
                    $name = $ref->getSymbolParent();
                } else {
                    $name = $ref->getSymbolName();
                }
                if ($name !== null && $name !== '(dynamic)') {
                    $set[$name] = true;
                    // Also add short name
                    $short = $this->getShortName($name);
                    $set[$short] = true;
                }
            }
        }

        return $set;
    }

    /**
     * Build set of implemented interfaces
     *
     * @param array $references References
     *
     * @return array
     */
    private function buildImplementedInterfaceSet(array $references)
    {
        $set = array();

        foreach ($references as $ref) {
            if ($ref->getType() === Reference::TYPE_IMPLEMENTS) {
                $name = $ref->getSymbolName();
                $set[$name] = true;
                $set[$this->getShortName($name)] = true;
            }
        }

        return $set;
    }

    /**
     * Build set of type references (type hints and return types)
     *
     * @param array $references References
     *
     * @return array
     */
    private function buildTypeReferenceSet(array $references)
    {
        $set = array();

        foreach ($references as $ref) {
            if ($ref->getType() === Reference::TYPE_TYPE_HINT ||
                $ref->getType() === Reference::TYPE_RETURN_TYPE) {
                $name = $ref->getSymbolName();
                $set[$name] = true;
                $set[$this->getShortName($name)] = true;
            }
        }

        return $set;
    }

    /**
     * Build set of used traits
     *
     * @param array $references References
     *
     * @return array
     */
    private function buildUsedTraitSet(array $references)
    {
        $set = array();

        foreach ($references as $ref) {
            if ($ref->getType() === Reference::TYPE_USE_TRAIT) {
                $name = $ref->getSymbolName();
                $set[$name] = true;
                $set[$this->getShortName($name)] = true;
            }
        }

        return $set;
    }

    /**
     * Check if class is referenced
     *
     * @param string $fqn Fully qualified name
     * @param string $shortName Short name
     * @param array $referencedSet Set of referenced names
     *
     * @return bool
     */
    private function isClassReferenced($fqn, $shortName, array $referencedSet)
    {
        return isset($referencedSet[$fqn]) || isset($referencedSet[$shortName]);
    }

    /**
     * Check if class has subclasses
     *
     * @param string $className Class name
     * @param array $references References
     *
     * @return bool
     */
    private function hasSubclasses($className, array $references)
    {
        $shortName = $this->getShortName($className);

        foreach ($references as $ref) {
            if ($ref->getType() === Reference::TYPE_EXTENDS) {
                $extended = $ref->getSymbolName();
                if ($extended === $className || $extended === $shortName) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if interface has sub-interfaces
     *
     * @param string $interfaceName Interface name
     * @param array $references References
     *
     * @return bool
     */
    private function hasSubinterfaces($interfaceName, array $references)
    {
        // Interface extends are also TYPE_EXTENDS
        return $this->hasSubclasses($interfaceName, $references);
    }

    /**
     * Check if symbol should be ignored
     *
     * @param Symbol $symbol Symbol to check
     * @param AnalysisContext $context Analysis context
     *
     * @return bool
     */
    private function shouldIgnore(Symbol $symbol, AnalysisContext $context)
    {
        $fqn = $symbol->getFullyQualifiedName();
        $shortName = $symbol->getName();

        // Check plugin patterns first
        if ($context->shouldPluginIgnoreSymbol($fqn) ||
            $context->shouldPluginIgnoreSymbol($shortName)) {
            return true;
        }

        // Check ignore patterns from config
        $ignorePatterns = $context->getConfigValue('ignore', array());
        $symbolPatterns = isset($ignorePatterns['symbols']) ? $ignorePatterns['symbols'] : array();

        foreach ($symbolPatterns as $pattern) {
            if ($this->matchesPattern($fqn, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if name matches pattern (with wildcard support)
     *
     * @param string $name Name to check
     * @param string $pattern Pattern (supports * wildcard)
     *
     * @return bool
     */
    private function matchesPattern($name, $pattern)
    {
        // Convert glob pattern to regex
        $regex = '/^' . str_replace(
            array('\\*', '\\?'),
            array('.*', '.'),
            preg_quote($pattern, '/')
        ) . '$/';

        return preg_match($regex, $name) === 1;
    }

    /**
     * Get short name from FQN
     *
     * @param string $fqn Fully qualified name
     *
     * @return string
     */
    private function getShortName($fqn)
    {
        $pos = strrpos($fqn, '\\');
        return $pos !== false ? substr($fqn, $pos + 1) : $fqn;
    }
}
