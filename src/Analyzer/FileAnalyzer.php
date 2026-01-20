<?php
/**
 * File Analyzer
 *
 * Detects files where all defined symbols are unused
 */

namespace PhpKnip\Analyzer;

use PhpKnip\Resolver\Symbol;
use PhpKnip\Resolver\Reference;

/**
 * Analyzes for files with only unused symbols
 */
class FileAnalyzer implements AnalyzerInterface
{
    /**
     * Entry point patterns that should never be flagged as unused
     */
    private static $entryPointPatterns = array(
        'bin/*',
        'public/index.php',
        'public/*.php',
        'index.php',
        'bootstrap.php',
        'bootstrap/*.php',
        'artisan',
        'console/*',
        'cli/*',
    );

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'file-analyzer';
    }

    /**
     * @inheritDoc
     */
    public function analyze(AnalysisContext $context)
    {
        $issues = array();
        $symbolTable = $context->getSymbolTable();
        $references = $context->getReferences();

        // Build set of referenced symbol names
        $referencedSymbols = $this->buildReferencedSymbolSet($references);

        // Group symbols by file
        $symbolsByFile = $this->groupSymbolsByFile($symbolTable);

        foreach ($symbolsByFile as $filePath => $symbols) {
            // Skip empty files (no symbols)
            if (empty($symbols)) {
                continue;
            }

            // Skip entry point files
            if ($this->isEntryPoint($filePath, $context)) {
                continue;
            }

            // Skip if ignored by config
            if ($this->shouldIgnoreFile($filePath, $context)) {
                continue;
            }

            // Check if any symbol in this file is referenced
            $hasReferencedSymbol = false;
            foreach ($symbols as $symbol) {
                if ($this->isSymbolReferenced($symbol, $referencedSymbols)) {
                    $hasReferencedSymbol = true;
                    break;
                }
            }

            // If no symbols are referenced, the file is unused
            if (!$hasReferencedSymbol) {
                $issues[] = Issue::unusedFile($filePath);
            }
        }

        return $issues;
    }

    /**
     * Build set of referenced symbol names
     *
     * @param array $references All references
     *
     * @return array
     */
    private function buildReferencedSymbolSet(array $references)
    {
        $set = array();

        foreach ($references as $ref) {
            $name = $ref->getSymbolName();
            $set[$name] = true;

            // For class members, also add parent::member format
            $parent = $ref->getSymbolParent();
            if ($parent !== null) {
                $set[$parent . '::' . $name] = true;
            }
        }

        return $set;
    }

    /**
     * Group symbols by file
     *
     * @param \PhpKnip\Resolver\SymbolTable $symbolTable Symbol table
     *
     * @return array
     */
    private function groupSymbolsByFile($symbolTable)
    {
        $byFile = array();

        // Only consider top-level symbols (classes, interfaces, traits, functions, constants)
        $topLevelTypes = array(
            Symbol::TYPE_CLASS,
            Symbol::TYPE_INTERFACE,
            Symbol::TYPE_TRAIT,
            Symbol::TYPE_FUNCTION,
            Symbol::TYPE_CONSTANT,
        );

        foreach ($symbolTable->getAll() as $symbol) {
            if (!in_array($symbol->getType(), $topLevelTypes)) {
                continue;
            }

            $filePath = $symbol->getFilePath();
            if ($filePath === null) {
                continue;
            }

            if (!isset($byFile[$filePath])) {
                $byFile[$filePath] = array();
            }
            $byFile[$filePath][] = $symbol;
        }

        return $byFile;
    }

    /**
     * Check if symbol is referenced
     *
     * @param Symbol $symbol Symbol to check
     * @param array $referencedSymbols Set of referenced symbols
     *
     * @return bool
     */
    private function isSymbolReferenced(Symbol $symbol, array $referencedSymbols)
    {
        $name = $symbol->getName();
        $fqn = $symbol->getFullyQualifiedName();

        // Check short name
        if (isset($referencedSymbols[$name])) {
            return true;
        }

        // Check FQN
        if ($fqn !== null && isset($referencedSymbols[$fqn])) {
            return true;
        }

        return false;
    }

    /**
     * Check if file is an entry point
     *
     * @param string $filePath File path
     * @param AnalysisContext $context Analysis context
     *
     * @return bool
     */
    private function isEntryPoint($filePath, AnalysisContext $context)
    {
        // Get base path from config
        $basePath = $context->getConfigValue('basePath', '');

        // Normalize path
        $relativePath = $filePath;
        if (!empty($basePath) && strpos($filePath, $basePath) === 0) {
            $relativePath = substr($filePath, strlen($basePath) + 1);
        }

        // Check default entry point patterns
        foreach (self::$entryPointPatterns as $pattern) {
            if ($this->matchesPattern($relativePath, $pattern)) {
                return true;
            }
        }

        // Check config entry points
        $entryPoints = $context->getConfigValue('entry_points', array());
        foreach ($entryPoints as $pattern) {
            if ($this->matchesPattern($relativePath, $pattern) ||
                $this->matchesPattern($filePath, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if file should be ignored
     *
     * @param string $filePath File path
     * @param AnalysisContext $context Analysis context
     *
     * @return bool
     */
    private function shouldIgnoreFile($filePath, AnalysisContext $context)
    {
        // Check plugin patterns
        if ($context->shouldPluginIgnoreFile($filePath)) {
            return true;
        }

        // Check config patterns
        $ignorePatterns = $context->getConfigValue('ignore', array());
        $pathPatterns = isset($ignorePatterns['paths']) ? $ignorePatterns['paths'] : array();

        foreach ($pathPatterns as $pattern) {
            if ($this->matchesPattern($filePath, $pattern)) {
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
        // Convert glob pattern to regex
        $regex = '/^' . str_replace(
            array('\\*\\*', '\\*', '\\?'),
            array('.*', '[^/]*', '.'),
            preg_quote($pattern, '/')
        ) . '$/';

        return preg_match($regex, $name) === 1;
    }
}
