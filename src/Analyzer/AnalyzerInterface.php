<?php
/**
 * Analyzer Interface
 *
 * Common interface for all analyzers
 */

namespace PhpKnip\Analyzer;

use PhpKnip\Resolver\SymbolTable;

/**
 * Interface for code analyzers
 */
interface AnalyzerInterface
{
    /**
     * Get analyzer name
     *
     * @return string
     */
    public function getName();

    /**
     * Analyze and return issues
     *
     * @param AnalysisContext $context Analysis context
     *
     * @return array<Issue> Found issues
     */
    public function analyze(AnalysisContext $context);
}
