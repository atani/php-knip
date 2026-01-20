<?php
/**
 * Fixer Manager
 *
 * Manages and applies code fixers
 */

namespace PhpKnip\Fixer;

use PhpKnip\Analyzer\Issue;

/**
 * Manages code fixers
 */
class FixerManager
{
    /**
     * @var array<FixerInterface> Registered fixers
     */
    private $fixers = array();

    /**
     * @var bool Whether to actually apply fixes (false = dry run)
     */
    private $dryRun = true;

    /**
     * @var array<FixResult> Results from fix operations
     */
    private $results = array();

    /**
     * @var array<string, string> File content cache (path => content)
     */
    private $fileCache = array();

    /**
     * @var array<string, bool> Modified files (path => true)
     */
    private $modifiedFiles = array();

    /**
     * Register a fixer
     *
     * @param FixerInterface $fixer Fixer to register
     *
     * @return $this
     */
    public function registerFixer(FixerInterface $fixer)
    {
        $this->fixers[$fixer->getName()] = $fixer;
        return $this;
    }

    /**
     * Register built-in fixers
     *
     * @return $this
     */
    public function registerBuiltinFixers()
    {
        $this->registerFixer(new UseStatementFixer());
        return $this;
    }

    /**
     * Get all registered fixers
     *
     * @return array<FixerInterface>
     */
    public function getFixers()
    {
        return $this->fixers;
    }

    /**
     * Get fixer by name
     *
     * @param string $name Fixer name
     *
     * @return FixerInterface|null
     */
    public function getFixer($name)
    {
        return isset($this->fixers[$name]) ? $this->fixers[$name] : null;
    }

    /**
     * Set dry run mode
     *
     * @param bool $dryRun True for dry run, false to apply fixes
     *
     * @return $this
     */
    public function setDryRun($dryRun)
    {
        $this->dryRun = $dryRun;
        return $this;
    }

    /**
     * Check if in dry run mode
     *
     * @return bool
     */
    public function isDryRun()
    {
        return $this->dryRun;
    }

    /**
     * Fix a single issue
     *
     * @param Issue $issue Issue to fix
     *
     * @return FixResult
     */
    public function fixIssue(Issue $issue)
    {
        // Find a fixer that can handle this issue
        $fixer = $this->findFixer($issue);

        if ($fixer === null) {
            return FixResult::skipped($issue, 'No fixer available for this issue type');
        }

        // Get file content
        $filePath = $issue->getFilePath();
        if ($filePath === null) {
            return FixResult::failure($issue, 'Issue has no file path');
        }

        $content = $this->getFileContent($filePath);
        if ($content === null) {
            return FixResult::failure($issue, 'Could not read file: ' . $filePath);
        }

        // Apply the fix
        $result = $fixer->fix($issue, $content);

        if ($result->isSuccess() && $result->hasModification()) {
            // Update cache with new content
            $this->fileCache[$filePath] = $result->getNewContent();
            $this->modifiedFiles[$filePath] = true;
        }

        $this->results[] = $result;

        return $result;
    }

    /**
     * Fix multiple issues
     *
     * @param array<Issue> $issues Issues to fix
     *
     * @return array<FixResult>
     */
    public function fixIssues(array $issues)
    {
        // Sort fixers by priority
        $sortedFixers = $this->fixers;
        uasort($sortedFixers, function (FixerInterface $a, FixerInterface $b) {
            return $b->getPriority() - $a->getPriority();
        });

        // Group issues by file for more efficient processing
        $issuesByFile = array();
        foreach ($issues as $issue) {
            $filePath = $issue->getFilePath();
            if ($filePath === null) {
                continue;
            }

            if (!isset($issuesByFile[$filePath])) {
                $issuesByFile[$filePath] = array();
            }
            $issuesByFile[$filePath][] = $issue;
        }

        // Process each file's issues
        // Sort issues within each file by line number (descending) to avoid line number shifts
        foreach ($issuesByFile as $filePath => $fileIssues) {
            usort($fileIssues, function (Issue $a, Issue $b) {
                $lineA = $a->getLine();
                $lineB = $b->getLine();

                if ($lineA === null && $lineB === null) {
                    return 0;
                }
                if ($lineA === null) {
                    return 1;
                }
                if ($lineB === null) {
                    return -1;
                }

                return $lineB - $lineA; // Descending order
            });

            // Fix each issue in the file
            foreach ($fileIssues as $issue) {
                $this->fixIssue($issue);
            }
        }

        return $this->results;
    }

    /**
     * Write all modified files to disk
     *
     * @return array<string, bool> Map of file paths to success status
     */
    public function applyFixes()
    {
        if ($this->dryRun) {
            return array();
        }

        $written = array();

        foreach ($this->modifiedFiles as $filePath => $modified) {
            if (!$modified || !isset($this->fileCache[$filePath])) {
                continue;
            }

            $content = $this->fileCache[$filePath];
            $success = file_put_contents($filePath, $content) !== false;
            $written[$filePath] = $success;
        }

        return $written;
    }

    /**
     * Get all fix results
     *
     * @return array<FixResult>
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * Get successful fix results
     *
     * @return array<FixResult>
     */
    public function getSuccessfulResults()
    {
        return array_filter($this->results, function (FixResult $result) {
            return $result->isSuccess();
        });
    }

    /**
     * Get modified file paths
     *
     * @return array<string>
     */
    public function getModifiedFilePaths()
    {
        return array_keys($this->modifiedFiles);
    }

    /**
     * Get the modified content for a file
     *
     * @param string $filePath File path
     *
     * @return string|null Modified content or null if not modified
     */
    public function getModifiedContent($filePath)
    {
        return isset($this->fileCache[$filePath]) && isset($this->modifiedFiles[$filePath])
            ? $this->fileCache[$filePath]
            : null;
    }

    /**
     * Clear all cached data and results
     *
     * @return $this
     */
    public function clear()
    {
        $this->results = array();
        $this->fileCache = array();
        $this->modifiedFiles = array();
        return $this;
    }

    /**
     * Find a fixer that can handle the given issue
     *
     * @param Issue $issue Issue to fix
     *
     * @return FixerInterface|null
     */
    private function findFixer(Issue $issue)
    {
        // Sort fixers by priority (descending)
        $sortedFixers = $this->fixers;
        uasort($sortedFixers, function (FixerInterface $a, FixerInterface $b) {
            return $b->getPriority() - $a->getPriority();
        });

        foreach ($sortedFixers as $fixer) {
            if ($fixer->canFix($issue)) {
                return $fixer;
            }
        }

        return null;
    }

    /**
     * Get file content (from cache or disk)
     *
     * @param string $filePath File path
     *
     * @return string|null
     */
    private function getFileContent($filePath)
    {
        // Return from cache if available
        if (isset($this->fileCache[$filePath])) {
            return $this->fileCache[$filePath];
        }

        // Read from disk
        if (!file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        $this->fileCache[$filePath] = $content;
        return $content;
    }

    /**
     * Get summary statistics
     *
     * @return array
     */
    public function getSummary()
    {
        $total = count($this->results);
        $successful = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($this->results as $result) {
            if ($result->isSuccess()) {
                $successful++;
            } elseif ($result->getError() !== null) {
                $failed++;
            } else {
                $skipped++;
            }
        }

        return array(
            'total' => $total,
            'successful' => $successful,
            'failed' => $failed,
            'skipped' => $skipped,
            'files_modified' => count($this->modifiedFiles),
        );
    }
}
