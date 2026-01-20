<?php
/**
 * Fix Result
 *
 * Represents the result of a fix operation
 */

namespace PhpKnip\Fixer;

use PhpKnip\Analyzer\Issue;

/**
 * Result of a fix operation
 */
class FixResult
{
    /**
     * @var bool Whether the fix was successful
     */
    private $success;

    /**
     * @var string|null New file content (null if not modified)
     */
    private $newContent;

    /**
     * @var Issue The issue that was fixed
     */
    private $issue;

    /**
     * @var string Description of what was fixed
     */
    private $description;

    /**
     * @var string|null Error message if fix failed
     */
    private $error;

    /**
     * @var array Lines that were removed
     */
    private $removedLines = array();

    /**
     * @var array Lines that were modified
     */
    private $modifiedLines = array();

    /**
     * Create a successful fix result
     *
     * @param Issue $issue The issue that was fixed
     * @param string $newContent New file content
     * @param string $description Description of the fix
     *
     * @return FixResult
     */
    public static function success(Issue $issue, $newContent, $description)
    {
        $result = new self();
        $result->success = true;
        $result->issue = $issue;
        $result->newContent = $newContent;
        $result->description = $description;
        return $result;
    }

    /**
     * Create a failed fix result
     *
     * @param Issue $issue The issue that could not be fixed
     * @param string $error Error message
     *
     * @return FixResult
     */
    public static function failure(Issue $issue, $error)
    {
        $result = new self();
        $result->success = false;
        $result->issue = $issue;
        $result->error = $error;
        $result->description = 'Fix failed: ' . $error;
        return $result;
    }

    /**
     * Create a skipped fix result (already fixed or not applicable)
     *
     * @param Issue $issue The issue
     * @param string $reason Reason for skipping
     *
     * @return FixResult
     */
    public static function skipped(Issue $issue, $reason)
    {
        $result = new self();
        $result->success = false;
        $result->issue = $issue;
        $result->description = 'Skipped: ' . $reason;
        return $result;
    }

    /**
     * @return bool
     */
    public function isSuccess()
    {
        return $this->success;
    }

    /**
     * @return string|null
     */
    public function getNewContent()
    {
        return $this->newContent;
    }

    /**
     * @return Issue
     */
    public function getIssue()
    {
        return $this->issue;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return string|null
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Set removed lines for display
     *
     * @param array $lines Array of line numbers
     *
     * @return $this
     */
    public function setRemovedLines(array $lines)
    {
        $this->removedLines = $lines;
        return $this;
    }

    /**
     * @return array
     */
    public function getRemovedLines()
    {
        return $this->removedLines;
    }

    /**
     * Set modified lines for display
     *
     * @param array $lines Array of line numbers
     *
     * @return $this
     */
    public function setModifiedLines(array $lines)
    {
        $this->modifiedLines = $lines;
        return $this;
    }

    /**
     * @return array
     */
    public function getModifiedLines()
    {
        return $this->modifiedLines;
    }

    /**
     * Check if content was modified
     *
     * @return bool
     */
    public function hasModification()
    {
        return $this->success && $this->newContent !== null;
    }
}
