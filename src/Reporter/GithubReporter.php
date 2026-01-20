<?php
/**
 * GitHub Reporter
 *
 * Outputs issues as GitHub Actions workflow commands (annotations)
 */

namespace PhpKnip\Reporter;

use PhpKnip\Analyzer\Issue;

/**
 * Reporter that outputs GitHub Actions annotations
 */
class GithubReporter implements ReporterInterface
{
    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'github';
    }

    /**
     * @inheritDoc
     */
    public function getFileExtension()
    {
        return null; // stdout
    }

    /**
     * @inheritDoc
     */
    public function report(array $issues, array $options = array())
    {
        $basePath = isset($options['basePath']) ? $options['basePath'] : '';
        $output = array();

        foreach ($issues as $issue) {
            $output[] = $this->formatIssue($issue, $basePath);
        }

        // Add summary
        if (!empty($issues)) {
            $output[] = '';
            $output[] = $this->formatSummary($issues);
        }

        return implode("\n", $output) . "\n";
    }

    /**
     * Format a single issue as GitHub annotation
     *
     * @param Issue $issue Issue to format
     * @param string $basePath Base path for relative paths
     *
     * @return string
     */
    private function formatIssue(Issue $issue, $basePath)
    {
        $severity = $this->mapSeverity($issue->getSeverity());
        $file = $this->getRelativePath($issue->getFilePath(), $basePath);
        $line = $issue->getLine();
        $message = $issue->getMessage();
        $title = $this->formatTitle($issue);

        // Build annotation
        // Format: ::{severity} file={file},line={line},title={title}::{message}
        $annotation = '::' . $severity;

        $params = array();
        if ($file !== null) {
            $params[] = 'file=' . $file;
        }
        if ($line !== null) {
            $params[] = 'line=' . $line;
        }
        if ($title !== null) {
            $params[] = 'title=' . $title;
        }

        if (!empty($params)) {
            $annotation .= ' ' . implode(',', $params);
        }

        $annotation .= '::' . $this->escapeMessage($message);

        return $annotation;
    }

    /**
     * Map issue severity to GitHub annotation level
     *
     * @param string $severity Issue severity
     *
     * @return string
     */
    private function mapSeverity($severity)
    {
        switch ($severity) {
            case Issue::SEVERITY_ERROR:
                return 'error';
            case Issue::SEVERITY_WARNING:
                return 'warning';
            case Issue::SEVERITY_INFO:
            default:
                return 'notice';
        }
    }

    /**
     * Format issue title
     *
     * @param Issue $issue Issue
     *
     * @return string
     */
    private function formatTitle(Issue $issue)
    {
        $type = $issue->getType();

        // Convert type to human-readable title
        $title = str_replace('-', ' ', $type);
        $title = ucwords($title);

        return $title;
    }

    /**
     * Escape message for GitHub annotation
     *
     * @param string $message Message to escape
     *
     * @return string
     */
    private function escapeMessage($message)
    {
        // GitHub annotations use %0A for newlines and %25 for %
        $message = str_replace('%', '%25', $message);
        $message = str_replace("\r", '', $message);
        $message = str_replace("\n", '%0A', $message);

        return $message;
    }

    /**
     * Format summary as GitHub annotation
     *
     * @param array $issues Issues
     *
     * @return string
     */
    private function formatSummary(array $issues)
    {
        $total = count($issues);
        $errors = 0;
        $warnings = 0;
        $notices = 0;

        foreach ($issues as $issue) {
            switch ($issue->getSeverity()) {
                case Issue::SEVERITY_ERROR:
                    $errors++;
                    break;
                case Issue::SEVERITY_WARNING:
                    $warnings++;
                    break;
                default:
                    $notices++;
                    break;
            }
        }

        $parts = array();
        if ($errors > 0) {
            $parts[] = $errors . ' error' . ($errors > 1 ? 's' : '');
        }
        if ($warnings > 0) {
            $parts[] = $warnings . ' warning' . ($warnings > 1 ? 's' : '');
        }
        if ($notices > 0) {
            $parts[] = $notices . ' notice' . ($notices > 1 ? 's' : '');
        }

        $summary = sprintf('PHP-Knip found %d issue%s', $total, $total > 1 ? 's' : '');
        if (!empty($parts)) {
            $summary .= ' (' . implode(', ', $parts) . ')';
        }

        return '::notice::' . $summary;
    }

    /**
     * Get relative path
     *
     * @param string|null $filePath Absolute path
     * @param string $basePath Base path
     *
     * @return string|null
     */
    private function getRelativePath($filePath, $basePath)
    {
        if ($filePath === null) {
            return null;
        }

        if (!empty($basePath) && strpos($filePath, $basePath) === 0) {
            return ltrim(substr($filePath, strlen($basePath)), '/');
        }

        return $filePath;
    }
}
