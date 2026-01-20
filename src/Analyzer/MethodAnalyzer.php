<?php
/**
 * Method Analyzer
 *
 * Detects unused private methods
 */

namespace PhpKnip\Analyzer;

use PhpKnip\Resolver\Symbol;
use PhpKnip\Resolver\Reference;

/**
 * Analyzes for unused private methods
 */
class MethodAnalyzer implements AnalyzerInterface
{
    /**
     * Magic methods that should never be flagged as unused
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
        return 'method-analyzer';
    }

    /**
     * @inheritDoc
     */
    public function analyze(AnalysisContext $context)
    {
        $issues = array();
        $symbolTable = $context->getSymbolTable();
        $references = $context->getReferences();

        // Build set of called method names
        $calledMethods = $this->buildCalledMethodSet($references);

        // Get all methods
        $methods = $symbolTable->getByType(Symbol::TYPE_METHOD);

        foreach ($methods as $method) {
            $methodName = $method->getName();
            $className = $method->getParent();
            $visibility = $method->getVisibility();

            // Skip if no parent class
            if ($className === null) {
                continue;
            }

            // Only check private methods
            // Public/protected methods can be called externally or overridden
            if ($visibility !== Symbol::VISIBILITY_PRIVATE) {
                continue;
            }

            // Skip magic methods
            if (in_array($methodName, self::$magicMethods)) {
                continue;
            }

            // Skip if called
            if ($this->isMethodCalled($methodName, $className, $calledMethods)) {
                continue;
            }

            // Skip if ignored by config or plugin
            if ($this->shouldIgnore($method, $context)) {
                continue;
            }

            $issues[] = Issue::unusedMethod(
                $methodName,
                $className,
                $method->getFilePath(),
                $method->getStartLine()
            );
        }

        return $issues;
    }

    /**
     * Build set of called method names
     *
     * @param array $references All references
     *
     * @return array
     */
    private function buildCalledMethodSet(array $references)
    {
        $set = array();

        foreach ($references as $ref) {
            $type = $ref->getType();

            // Method calls: $this->method(), $obj->method()
            if ($type === Reference::TYPE_METHOD_CALL) {
                $methodName = $ref->getSymbolName();
                $set[$methodName] = true;

                // Also store with context if available
                $context = $ref->getContext();
                if ($context !== null) {
                    // Context might be "ClassName::methodName"
                    $parts = explode('::', $context);
                    if (count($parts) >= 1) {
                        $contextClass = $parts[0];
                        $set[$contextClass . '::' . $methodName] = true;
                    }
                }
            }

            // Static calls: self::method(), static::method(), ClassName::method()
            if ($type === Reference::TYPE_STATIC_CALL) {
                $methodName = $ref->getSymbolName();
                $parent = $ref->getSymbolParent();

                $set[$methodName] = true;

                if ($parent !== null) {
                    $set[$parent . '::' . $methodName] = true;

                    // Also store short class name
                    $shortParent = $this->getShortName($parent);
                    $set[$shortParent . '::' . $methodName] = true;
                }
            }
        }

        return $set;
    }

    /**
     * Check if method is called
     *
     * @param string $methodName Method name
     * @param string $className Class name
     * @param array $calledMethods Set of called methods
     *
     * @return bool
     */
    private function isMethodCalled($methodName, $className, array $calledMethods)
    {
        // Check by method name only (for $this->method() calls)
        if (isset($calledMethods[$methodName])) {
            return true;
        }

        // Check full name
        $fullName = $className . '::' . $methodName;
        if (isset($calledMethods[$fullName])) {
            return true;
        }

        // Check short class name
        $shortClassName = $this->getShortName($className);
        $shortFullName = $shortClassName . '::' . $methodName;
        if (isset($calledMethods[$shortFullName])) {
            return true;
        }

        return false;
    }

    /**
     * Check if method should be ignored
     *
     * @param Symbol $symbol Method symbol
     * @param AnalysisContext $context Analysis context
     *
     * @return bool
     */
    private function shouldIgnore(Symbol $symbol, AnalysisContext $context)
    {
        $methodName = $symbol->getName();
        $className = $symbol->getParent();
        $fullName = $className . '::' . $methodName;

        // Check plugin patterns
        if ($context->shouldPluginIgnoreSymbol($fullName) ||
            $context->shouldPluginIgnoreSymbol($methodName)) {
            return true;
        }

        // Check config patterns
        $ignorePatterns = $context->getConfigValue('ignore', array());
        $symbolPatterns = isset($ignorePatterns['symbols']) ? $ignorePatterns['symbols'] : array();

        foreach ($symbolPatterns as $pattern) {
            if ($this->matchesPattern($fullName, $pattern) ||
                $this->matchesPattern($methodName, $pattern)) {
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
