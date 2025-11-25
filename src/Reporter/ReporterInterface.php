<?php
/**
 * Reporter Interface
 *
 * Interface for result reporters
 */

namespace PhpKnip\Reporter;

use PhpKnip\Analyzer\Issue;

/**
 * Interface for outputting analysis results
 */
interface ReporterInterface
{
    /**
     * Get reporter name
     *
     * @return string
     */
    public function getName();

    /**
     * Report analysis results
     *
     * @param array<Issue> $issues Array of issues
     * @param array $options Reporter options
     *
     * @return string Formatted output
     */
    public function report(array $issues, array $options = array());

    /**
     * Get supported file extension for this reporter
     *
     * @return string|null File extension (e.g., 'json', 'xml') or null for stdout
     */
    public function getFileExtension();
}
