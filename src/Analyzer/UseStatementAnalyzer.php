<?php
/**
 * Use Statement Analyzer
 *
 * Detects unused use statements
 */

namespace PhpKnip\Analyzer;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpKnip\Resolver\Reference;

/**
 * Analyzes for unused use statements
 */
class UseStatementAnalyzer implements AnalyzerInterface
{
    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'use-statement-analyzer';
    }

    /**
     * @inheritDoc
     */
    public function analyze(AnalysisContext $context)
    {
        $issues = array();

        // Get all use statements grouped by file
        $allUseStatements = $context->getAllUseStatements();

        foreach ($allUseStatements as $filePath => $useStatements) {
            $fileIssues = $this->analyzeFile($filePath, $useStatements, $context);
            $issues = array_merge($issues, $fileIssues);
        }

        return $issues;
    }

    /**
     * Analyze use statements in a single file
     *
     * @param string $filePath File path
     * @param array $useStatements Use statements in the file
     * @param AnalysisContext $context Analysis context
     *
     * @return array<Issue>
     */
    private function analyzeFile($filePath, array $useStatements, AnalysisContext $context)
    {
        $issues = array();

        // Get references in this file
        $fileReferences = $this->getFileReferences($filePath, $context->getReferences());

        // Build set of used names in this file
        $usedNames = $this->buildUsedNameSet($fileReferences);

        foreach ($useStatements as $use) {
            $fqn = $use['fqn'];
            $alias = $use['alias'];
            $line = isset($use['line']) ? $use['line'] : null;
            $type = isset($use['type']) ? $use['type'] : 'class';

            // Check if the alias (or short name) is used
            if ($this->isUseStatementUsed($alias, $fqn, $usedNames, $type)) {
                continue;
            }

            // Skip if in ignore list
            if ($this->shouldIgnore($fqn, $context)) {
                continue;
            }

            $issues[] = Issue::unusedUseStatement($fqn, $filePath, $line);
        }

        return $issues;
    }

    /**
     * Get references from a specific file
     *
     * @param string $filePath File path
     * @param array $allReferences All references
     *
     * @return array
     */
    private function getFileReferences($filePath, array $allReferences)
    {
        return array_filter($allReferences, function ($ref) use ($filePath) {
            return $ref->getFilePath() === $filePath;
        });
    }

    /**
     * Build set of used names from references
     *
     * @param array $references References
     *
     * @return array
     */
    private function buildUsedNameSet(array $references)
    {
        $set = array();

        foreach ($references as $ref) {
            // Skip use import references themselves
            if ($ref->getType() === Reference::TYPE_USE_IMPORT) {
                continue;
            }

            $name = $ref->getSymbolName();
            if ($name !== '(dynamic)') {
                // Add full name
                $set[$name] = true;

                // Add short name (for use alias matching)
                $parts = explode('\\', $name);
                $shortName = end($parts);
                $set[$shortName] = true;

                // Add first part (for qualified name usage like Foo\Bar)
                if (count($parts) > 1) {
                    $set[$parts[0]] = true;
                }
            }

            // Also check parent for static calls
            $parent = $ref->getSymbolParent();
            if ($parent !== null && $parent !== '(dynamic)') {
                $set[$parent] = true;
                $parentParts = explode('\\', $parent);
                $set[end($parentParts)] = true;
                if (count($parentParts) > 1) {
                    $set[$parentParts[0]] = true;
                }
            }
        }

        return $set;
    }

    /**
     * Check if use statement is used
     *
     * @param string $alias Use alias (or short name)
     * @param string $fqn Full qualified name
     * @param array $usedNames Set of used names
     * @param string $type Use type (class, function, constant)
     *
     * @return bool
     */
    private function isUseStatementUsed($alias, $fqn, array $usedNames, $type)
    {
        // Check if alias is used
        if (isset($usedNames[$alias])) {
            return true;
        }

        // Check if FQN is used directly
        if (isset($usedNames[$fqn])) {
            return true;
        }

        // For grouped uses like `use App\{Foo, Bar}`, check each part
        $parts = explode('\\', $fqn);
        $shortName = end($parts);
        if ($shortName !== $alias && isset($usedNames[$shortName])) {
            return true;
        }

        return false;
    }

    /**
     * Check if use statement should be ignored
     *
     * @param string $fqn Fully qualified name
     * @param AnalysisContext $context Analysis context
     *
     * @return bool
     */
    private function shouldIgnore($fqn, AnalysisContext $context)
    {
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
     * Match pattern with wildcard support
     *
     * @param string $name Name to check
     * @param string $pattern Pattern
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
}
