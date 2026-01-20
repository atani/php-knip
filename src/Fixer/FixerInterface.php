<?php
/**
 * Fixer Interface
 *
 * Defines the contract for automatic code fixers
 */

namespace PhpKnip\Fixer;

use PhpKnip\Analyzer\Issue;

/**
 * Interface for code fixers
 *
 * Fixers can automatically remove or modify unused code
 */
interface FixerInterface
{
    /**
     * Get fixer name
     *
     * @return string Unique fixer identifier
     */
    public function getName();

    /**
     * Get fixer description
     *
     * @return string Human-readable description
     */
    public function getDescription();

    /**
     * Get priority for this fixer
     *
     * Higher priority fixers are applied first.
     * Default should be 0.
     *
     * @return int Priority value
     */
    public function getPriority();

    /**
     * Check if this fixer can handle the given issue
     *
     * @param Issue $issue Issue to check
     *
     * @return bool True if this fixer can handle the issue
     */
    public function canFix(Issue $issue);

    /**
     * Apply fix to the file content
     *
     * @param Issue $issue Issue to fix
     * @param string $content Current file content
     *
     * @return FixResult Result of the fix operation
     */
    public function fix(Issue $issue, $content);

    /**
     * Get the issue types this fixer can handle
     *
     * @return array<string> Array of issue type constants
     */
    public function getSupportedTypes();
}
