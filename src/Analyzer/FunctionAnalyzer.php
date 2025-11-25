<?php
/**
 * Function Analyzer
 *
 * Detects unused functions
 */

namespace PhpKnip\Analyzer;

use PhpKnip\Resolver\Symbol;
use PhpKnip\Resolver\Reference;

/**
 * Analyzes for unused functions
 */
class FunctionAnalyzer implements AnalyzerInterface
{
    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'function-analyzer';
    }

    /**
     * @inheritDoc
     */
    public function analyze(AnalysisContext $context)
    {
        $issues = array();
        $symbolTable = $context->getSymbolTable();
        $references = $context->getReferences();

        // Build set of called function names
        $calledFunctions = $this->buildCalledFunctionSet($references);

        foreach ($symbolTable->getFunctions() as $function) {
            $fqn = $function->getFullyQualifiedName();
            $shortName = $function->getName();

            // Skip if called
            if ($this->isFunctionCalled($fqn, $shortName, $calledFunctions)) {
                continue;
            }

            // Skip if ignored
            if ($this->shouldIgnore($function, $context)) {
                continue;
            }

            // Skip callback-style references
            if ($this->hasCallbackReference($fqn, $shortName, $references)) {
                continue;
            }

            $issues[] = Issue::unusedFunction(
                $fqn,
                $function->getFilePath(),
                $function->getStartLine()
            );
        }

        return $issues;
    }

    /**
     * Build set of called function names
     *
     * @param array $references References
     *
     * @return array
     */
    private function buildCalledFunctionSet(array $references)
    {
        $set = array();

        foreach ($references as $ref) {
            if ($ref->getType() === Reference::TYPE_FUNCTION_CALL) {
                $name = $ref->getSymbolName();
                if ($name !== '(dynamic)') {
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
     * Check if function is called
     *
     * @param string $fqn Fully qualified name
     * @param string $shortName Short name
     * @param array $calledSet Set of called function names
     *
     * @return bool
     */
    private function isFunctionCalled($fqn, $shortName, array $calledSet)
    {
        return isset($calledSet[$fqn]) || isset($calledSet[$shortName]);
    }

    /**
     * Check if function has callback-style reference
     *
     * This handles cases like:
     * - array_map('myFunction', $arr)
     * - call_user_func('myFunction')
     * - $callback = 'myFunction';
     *
     * @param string $fqn Function FQN
     * @param string $shortName Short name
     * @param array $references References
     *
     * @return bool
     */
    private function hasCallbackReference($fqn, $shortName, array $references)
    {
        // Check for string references that might be callbacks
        foreach ($references as $ref) {
            // Check if function name appears in metadata (string literals)
            $metadata = $ref->getMetadata();
            if (isset($metadata['stringLiterals'])) {
                foreach ($metadata['stringLiterals'] as $literal) {
                    if ($literal === $fqn || $literal === $shortName) {
                        return true;
                    }
                }
            }
        }

        return false;
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
        // Check ignore patterns from config
        $ignorePatterns = $context->getConfigValue('ignore', array());
        $symbolPatterns = isset($ignorePatterns['symbols']) ? $ignorePatterns['symbols'] : array();

        $fqn = $symbol->getFullyQualifiedName();
        $shortName = $symbol->getName();

        foreach ($symbolPatterns as $pattern) {
            if ($this->matchesPattern($fqn, $pattern) || $this->matchesPattern($shortName, $pattern)) {
                return true;
            }
        }

        // Check if function name starts with underscore (often intentionally unused)
        if (strpos($shortName, '_') === 0) {
            return true;
        }

        return false;
    }

    /**
     * Check if name matches pattern
     *
     * @param string $name Name to check
     * @param string $pattern Pattern (supports * wildcard)
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
