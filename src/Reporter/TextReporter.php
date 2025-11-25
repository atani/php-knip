<?php
/**
 * Text Reporter
 *
 * Outputs analysis results as human-readable text
 */

namespace PhpKnip\Reporter;

use PhpKnip\Analyzer\Issue;

/**
 * Text format reporter for console output
 */
class TextReporter implements ReporterInterface
{
    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'text';
    }

    /**
     * @inheritDoc
     */
    public function getFileExtension()
    {
        return 'txt';
    }

    /**
     * @inheritDoc
     */
    public function report(array $issues, array $options = array())
    {
        if (empty($issues)) {
            return "No issues found.\n";
        }

        $showColors = isset($options['colors']) ? $options['colors'] : true;
        $basePath = isset($options['basePath']) ? $options['basePath'] : '';
        $groupBy = isset($options['groupBy']) ? $options['groupBy'] : 'type';

        $output = '';

        if ($groupBy === 'file') {
            $output = $this->reportGroupedByFile($issues, $basePath, $showColors);
        } else {
            $output = $this->reportGroupedByType($issues, $basePath, $showColors);
        }

        // Summary
        $output .= $this->renderSummary($issues, $showColors);

        return $output;
    }

    /**
     * Report issues grouped by type
     *
     * @param array $issues Issues
     * @param string $basePath Base path for relative paths
     * @param bool $showColors Whether to show colors
     *
     * @return string
     */
    private function reportGroupedByType(array $issues, $basePath, $showColors)
    {
        $grouped = $this->groupIssuesByType($issues);
        $output = '';

        foreach ($grouped as $type => $typeIssues) {
            $typeLabel = $this->getTypeLabel($type);
            $count = count($typeIssues);

            $output .= $this->formatHeader(
                sprintf("%s (%d)", $typeLabel, $count),
                $showColors
            );

            foreach ($typeIssues as $issue) {
                $output .= $this->formatIssue($issue, $basePath, $showColors);
            }

            $output .= "\n";
        }

        return $output;
    }

    /**
     * Report issues grouped by file
     *
     * @param array $issues Issues
     * @param string $basePath Base path for relative paths
     * @param bool $showColors Whether to show colors
     *
     * @return string
     */
    private function reportGroupedByFile(array $issues, $basePath, $showColors)
    {
        $grouped = $this->groupIssuesByFile($issues);
        $output = '';

        foreach ($grouped as $file => $fileIssues) {
            $relativePath = $this->getRelativePath($file, $basePath);
            $count = count($fileIssues);

            $output .= $this->formatHeader(
                sprintf("%s (%d)", $relativePath, $count),
                $showColors
            );

            foreach ($fileIssues as $issue) {
                $output .= $this->formatIssueWithoutFile($issue, $showColors);
            }

            $output .= "\n";
        }

        return $output;
    }

    /**
     * Group issues by type
     *
     * @param array $issues Issues
     *
     * @return array
     */
    private function groupIssuesByType(array $issues)
    {
        $grouped = array();

        foreach ($issues as $issue) {
            $type = $issue->getType();
            if (!isset($grouped[$type])) {
                $grouped[$type] = array();
            }
            $grouped[$type][] = $issue;
        }

        return $grouped;
    }

    /**
     * Group issues by file
     *
     * @param array $issues Issues
     *
     * @return array
     */
    private function groupIssuesByFile(array $issues)
    {
        $grouped = array();

        foreach ($issues as $issue) {
            $file = $issue->getFilePath() ?: '(unknown)';
            if (!isset($grouped[$file])) {
                $grouped[$file] = array();
            }
            $grouped[$file][] = $issue;
        }

        return $grouped;
    }

    /**
     * Format a single issue
     *
     * @param Issue $issue Issue
     * @param string $basePath Base path
     * @param bool $showColors Show colors
     *
     * @return string
     */
    private function formatIssue(Issue $issue, $basePath, $showColors)
    {
        $location = $this->formatLocation($issue, $basePath);
        $symbol = $issue->getSymbolName() ?: '';
        $severity = $issue->getSeverity();

        $severityIcon = $this->getSeverityIcon($severity, $showColors);

        return sprintf(
            "  %s %s %s\n",
            $severityIcon,
            $this->formatSymbol($symbol, $showColors),
            $this->formatLocationText($location, $showColors)
        );
    }

    /**
     * Format a single issue without file (for grouped by file output)
     *
     * @param Issue $issue Issue
     * @param bool $showColors Show colors
     *
     * @return string
     */
    private function formatIssueWithoutFile(Issue $issue, $showColors)
    {
        $line = $issue->getLine();
        $symbol = $issue->getSymbolName() ?: '';
        $type = $this->getTypeLabel($issue->getType());
        $severity = $issue->getSeverity();

        $severityIcon = $this->getSeverityIcon($severity, $showColors);
        $lineText = $line !== null ? sprintf(":%d", $line) : '';

        return sprintf(
            "  %s %s [%s]%s\n",
            $severityIcon,
            $this->formatSymbol($symbol, $showColors),
            $type,
            $lineText
        );
    }

    /**
     * Format location string
     *
     * @param Issue $issue Issue
     * @param string $basePath Base path
     *
     * @return string
     */
    private function formatLocation(Issue $issue, $basePath)
    {
        $file = $issue->getFilePath();
        if ($file === null) {
            return '';
        }

        $relativePath = $this->getRelativePath($file, $basePath);
        $line = $issue->getLine();

        if ($line !== null) {
            return sprintf('%s:%d', $relativePath, $line);
        }

        return $relativePath;
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

    /**
     * Format header
     *
     * @param string $text Header text
     * @param bool $showColors Show colors
     *
     * @return string
     */
    private function formatHeader($text, $showColors)
    {
        if ($showColors) {
            return sprintf("\033[1;33m%s\033[0m\n", $text);
        }

        return $text . "\n";
    }

    /**
     * Format symbol name
     *
     * @param string $symbol Symbol name
     * @param bool $showColors Show colors
     *
     * @return string
     */
    private function formatSymbol($symbol, $showColors)
    {
        if ($showColors) {
            return sprintf("\033[1;37m%s\033[0m", $symbol);
        }

        return $symbol;
    }

    /**
     * Format location text
     *
     * @param string $location Location string
     * @param bool $showColors Show colors
     *
     * @return string
     */
    private function formatLocationText($location, $showColors)
    {
        if (empty($location)) {
            return '';
        }

        if ($showColors) {
            return sprintf("\033[0;36m%s\033[0m", $location);
        }

        return $location;
    }

    /**
     * Get severity icon
     *
     * @param string $severity Severity level
     * @param bool $showColors Show colors
     *
     * @return string
     */
    private function getSeverityIcon($severity, $showColors)
    {
        $icons = array(
            Issue::SEVERITY_ERROR => array('✖', "\033[0;31m✖\033[0m"),
            Issue::SEVERITY_WARNING => array('⚠', "\033[0;33m⚠\033[0m"),
            Issue::SEVERITY_INFO => array('ℹ', "\033[0;34mℹ\033[0m"),
        );

        $icon = isset($icons[$severity]) ? $icons[$severity] : array('•', '•');

        return $showColors ? $icon[1] : $icon[0];
    }

    /**
     * Get human-readable type label
     *
     * @param string $type Issue type
     *
     * @return string
     */
    private function getTypeLabel($type)
    {
        $labels = array(
            Issue::TYPE_UNUSED_FILE => 'Unused Files',
            Issue::TYPE_UNUSED_CLASS => 'Unused Classes',
            Issue::TYPE_UNUSED_INTERFACE => 'Unused Interfaces',
            Issue::TYPE_UNUSED_TRAIT => 'Unused Traits',
            Issue::TYPE_UNUSED_METHOD => 'Unused Methods',
            Issue::TYPE_UNUSED_FUNCTION => 'Unused Functions',
            Issue::TYPE_UNUSED_CONSTANT => 'Unused Constants',
            Issue::TYPE_UNUSED_PROPERTY => 'Unused Properties',
            Issue::TYPE_UNUSED_PARAMETER => 'Unused Parameters',
            Issue::TYPE_UNUSED_VARIABLE => 'Unused Variables',
            Issue::TYPE_UNUSED_USE => 'Unused Use Statements',
            Issue::TYPE_UNUSED_DEPENDENCY => 'Unused Dependencies',
        );

        return isset($labels[$type]) ? $labels[$type] : $type;
    }

    /**
     * Render summary section
     *
     * @param array $issues All issues
     * @param bool $showColors Show colors
     *
     * @return string
     */
    private function renderSummary(array $issues, $showColors)
    {
        $total = count($issues);
        $errors = 0;
        $warnings = 0;

        foreach ($issues as $issue) {
            if ($issue->getSeverity() === Issue::SEVERITY_ERROR) {
                $errors++;
            } elseif ($issue->getSeverity() === Issue::SEVERITY_WARNING) {
                $warnings++;
            }
        }

        $summary = sprintf(
            "Found %d issue%s (%d error%s, %d warning%s)\n",
            $total,
            $total !== 1 ? 's' : '',
            $errors,
            $errors !== 1 ? 's' : '',
            $warnings,
            $warnings !== 1 ? 's' : ''
        );

        if ($showColors) {
            if ($errors > 0) {
                return sprintf("\033[0;31m%s\033[0m", $summary);
            } elseif ($warnings > 0) {
                return sprintf("\033[0;33m%s\033[0m", $summary);
            }

            return sprintf("\033[0;32m%s\033[0m", $summary);
        }

        return $summary;
    }
}
