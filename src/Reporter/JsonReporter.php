<?php
/**
 * JSON Reporter
 *
 * Outputs analysis results as JSON
 */

namespace PhpKnip\Reporter;

use PhpKnip\Analyzer\Issue;

/**
 * JSON format reporter for machine-readable output
 */
class JsonReporter implements ReporterInterface
{
    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'json';
    }

    /**
     * @inheritDoc
     */
    public function getFileExtension()
    {
        return 'json';
    }

    /**
     * @inheritDoc
     */
    public function report(array $issues, array $options = array())
    {
        $pretty = isset($options['pretty']) ? $options['pretty'] : false;
        $basePath = isset($options['basePath']) ? $options['basePath'] : '';
        $groupBy = isset($options['groupBy']) ? $options['groupBy'] : null;

        $data = array(
            'summary' => $this->buildSummary($issues),
            'issues' => array(),
        );

        if ($groupBy === 'type') {
            $data['issues'] = $this->groupByType($issues, $basePath);
        } elseif ($groupBy === 'file') {
            $data['issues'] = $this->groupByFile($issues, $basePath);
        } else {
            $data['issues'] = $this->formatIssues($issues, $basePath);
        }

        $flags = 0;
        if ($pretty) {
            $flags = JSON_PRETTY_PRINT;
        }

        // For PHP 5.4+, JSON_UNESCAPED_UNICODE is available
        if (defined('JSON_UNESCAPED_UNICODE')) {
            $flags |= JSON_UNESCAPED_UNICODE;
        }

        if (defined('JSON_UNESCAPED_SLASHES')) {
            $flags |= JSON_UNESCAPED_SLASHES;
        }

        return json_encode($data, $flags) . "\n";
    }

    /**
     * Build summary statistics
     *
     * @param array $issues Issues
     *
     * @return array
     */
    private function buildSummary(array $issues)
    {
        $byType = array();
        $bySeverity = array(
            Issue::SEVERITY_ERROR => 0,
            Issue::SEVERITY_WARNING => 0,
            Issue::SEVERITY_INFO => 0,
        );

        foreach ($issues as $issue) {
            $type = $issue->getType();
            if (!isset($byType[$type])) {
                $byType[$type] = 0;
            }
            $byType[$type]++;

            $severity = $issue->getSeverity();
            if (isset($bySeverity[$severity])) {
                $bySeverity[$severity]++;
            }
        }

        return array(
            'total' => count($issues),
            'byType' => $byType,
            'bySeverity' => $bySeverity,
        );
    }

    /**
     * Format issues as flat array
     *
     * @param array $issues Issues
     * @param string $basePath Base path
     *
     * @return array
     */
    private function formatIssues(array $issues, $basePath)
    {
        $formatted = array();

        foreach ($issues as $issue) {
            $formatted[] = $this->formatIssue($issue, $basePath);
        }

        return $formatted;
    }

    /**
     * Group issues by type
     *
     * @param array $issues Issues
     * @param string $basePath Base path
     *
     * @return array
     */
    private function groupByType(array $issues, $basePath)
    {
        $grouped = array();

        foreach ($issues as $issue) {
            $type = $issue->getType();
            if (!isset($grouped[$type])) {
                $grouped[$type] = array();
            }
            $grouped[$type][] = $this->formatIssue($issue, $basePath);
        }

        return $grouped;
    }

    /**
     * Group issues by file
     *
     * @param array $issues Issues
     * @param string $basePath Base path
     *
     * @return array
     */
    private function groupByFile(array $issues, $basePath)
    {
        $grouped = array();

        foreach ($issues as $issue) {
            $file = $issue->getFilePath() ?: '(unknown)';
            $relativePath = $this->getRelativePath($file, $basePath);

            if (!isset($grouped[$relativePath])) {
                $grouped[$relativePath] = array();
            }

            $formatted = $this->formatIssue($issue, $basePath);
            unset($formatted['file']); // Remove redundant file info
            $grouped[$relativePath][] = $formatted;
        }

        return $grouped;
    }

    /**
     * Format a single issue
     *
     * @param Issue $issue Issue
     * @param string $basePath Base path
     *
     * @return array
     */
    private function formatIssue(Issue $issue, $basePath)
    {
        $file = $issue->getFilePath();
        $relativePath = $file !== null ? $this->getRelativePath($file, $basePath) : null;

        $data = array(
            'type' => $issue->getType(),
            'severity' => $issue->getSeverity(),
            'message' => $issue->getMessage(),
            'symbol' => $issue->getSymbolName(),
            'symbolType' => $issue->getSymbolType(),
            'file' => $relativePath,
            'line' => $issue->getLine(),
        );

        $metadata = $issue->getMetadata();
        if (!empty($metadata)) {
            $data['metadata'] = $metadata;
        }

        return $data;
    }

    /**
     * Get relative path
     *
     * @param string $path Full path
     * @param string $basePath Base path
     *
     * @return string
     */
    private function getRelativePath($path, $basePath)
    {
        if (empty($basePath)) {
            return $path;
        }

        $basePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (strpos($path, $basePath) === 0) {
            return substr($path, strlen($basePath));
        }

        return $path;
    }
}
