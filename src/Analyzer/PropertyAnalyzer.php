<?php
/**
 * Property Analyzer
 *
 * Detects unused private properties
 */

namespace PhpKnip\Analyzer;

use PhpKnip\Resolver\Symbol;
use PhpKnip\Resolver\Reference;

/**
 * Analyzes for unused private properties
 */
class PropertyAnalyzer implements AnalyzerInterface
{
    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'property-analyzer';
    }

    /**
     * @inheritDoc
     */
    public function analyze(AnalysisContext $context)
    {
        $issues = array();
        $symbolTable = $context->getSymbolTable();
        $references = $context->getReferences();

        // Build set of accessed property names
        $accessedProperties = $this->buildAccessedPropertySet($references);

        // Get all properties
        $properties = $symbolTable->getByType(Symbol::TYPE_PROPERTY);

        foreach ($properties as $property) {
            $propertyName = $property->getName();
            $className = $property->getParent();
            $visibility = $property->getVisibility();

            // Skip if no parent class
            if ($className === null) {
                continue;
            }

            // Only check private properties
            // Public/protected properties can be accessed externally or inherited
            if ($visibility !== Symbol::VISIBILITY_PRIVATE) {
                continue;
            }

            // Skip if accessed
            if ($this->isPropertyAccessed($propertyName, $className, $accessedProperties)) {
                continue;
            }

            // Skip if ignored by config or plugin
            if ($this->shouldIgnore($property, $context)) {
                continue;
            }

            $issues[] = Issue::unusedProperty(
                $propertyName,
                $className,
                $property->getFilePath(),
                $property->getStartLine()
            );
        }

        return $issues;
    }

    /**
     * Build set of accessed property names
     *
     * @param array $references All references
     *
     * @return array
     */
    private function buildAccessedPropertySet(array $references)
    {
        $set = array();

        foreach ($references as $ref) {
            $type = $ref->getType();

            // Property access: $this->property, $obj->property
            if ($type === Reference::TYPE_PROPERTY_ACCESS) {
                $propertyName = $ref->getSymbolName();
                $set[$propertyName] = true;

                // Also store with context if available
                $context = $ref->getContext();
                if ($context !== null) {
                    $parts = explode('::', $context);
                    if (count($parts) >= 1) {
                        $contextClass = $parts[0];
                        $set[$contextClass . '::$' . $propertyName] = true;
                    }
                }
            }

            // Static property access: self::$property, static::$property, ClassName::$property
            if ($type === Reference::TYPE_STATIC_PROPERTY) {
                $propertyName = $ref->getSymbolName();
                $parent = $ref->getSymbolParent();

                $set[$propertyName] = true;

                if ($parent !== null) {
                    $set[$parent . '::$' . $propertyName] = true;

                    // Also store short class name
                    $shortParent = $this->getShortName($parent);
                    $set[$shortParent . '::$' . $propertyName] = true;
                }
            }
        }

        return $set;
    }

    /**
     * Check if property is accessed
     *
     * @param string $propertyName Property name
     * @param string $className Class name
     * @param array $accessedProperties Set of accessed properties
     *
     * @return bool
     */
    private function isPropertyAccessed($propertyName, $className, array $accessedProperties)
    {
        // Check by property name only (for $this->property access)
        if (isset($accessedProperties[$propertyName])) {
            return true;
        }

        // Check full name
        $fullName = $className . '::$' . $propertyName;
        if (isset($accessedProperties[$fullName])) {
            return true;
        }

        // Check short class name
        $shortClassName = $this->getShortName($className);
        $shortFullName = $shortClassName . '::$' . $propertyName;
        if (isset($accessedProperties[$shortFullName])) {
            return true;
        }

        return false;
    }

    /**
     * Check if property should be ignored
     *
     * @param Symbol $symbol Property symbol
     * @param AnalysisContext $context Analysis context
     *
     * @return bool
     */
    private function shouldIgnore(Symbol $symbol, AnalysisContext $context)
    {
        $propertyName = $symbol->getName();
        $className = $symbol->getParent();
        $fullName = $className . '::$' . $propertyName;

        // Check plugin patterns
        if ($context->shouldPluginIgnoreSymbol($fullName) ||
            $context->shouldPluginIgnoreSymbol($propertyName)) {
            return true;
        }

        // Check config patterns
        $ignorePatterns = $context->getConfigValue('ignore', array());
        $symbolPatterns = isset($ignorePatterns['symbols']) ? $ignorePatterns['symbols'] : array();

        foreach ($symbolPatterns as $pattern) {
            if ($this->matchesPattern($fullName, $pattern) ||
                $this->matchesPattern($propertyName, $pattern)) {
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
