<?php
/**
 * HTML Reporter
 *
 * Outputs issues as a standalone HTML document
 */

namespace PhpKnip\Reporter;

use PhpKnip\Analyzer\Issue;

/**
 * Reporter that outputs HTML format
 */
class HtmlReporter implements ReporterInterface
{
    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'html';
    }

    /**
     * @inheritDoc
     */
    public function getFileExtension()
    {
        return 'html';
    }

    /**
     * @inheritDoc
     */
    public function report(array $issues, array $options = array())
    {
        $basePath = isset($options['basePath']) ? $options['basePath'] : '';
        $title = isset($options['title']) ? $options['title'] : 'PHP-Knip Analysis Report';

        $html = $this->renderDocument($issues, $title, $basePath);

        return $html;
    }

    /**
     * Render the full HTML document
     *
     * @param array $issues Issues to render
     * @param string $title Document title
     * @param string $basePath Base path for relative paths
     *
     * @return string
     */
    private function renderDocument(array $issues, $title, $basePath)
    {
        $summary = $this->buildSummary($issues);
        $groupedIssues = $this->groupIssuesByType($issues);

        $html = '<!DOCTYPE html>' . "\n";
        $html .= '<html lang="en">' . "\n";
        $html .= '<head>' . "\n";
        $html .= '  <meta charset="UTF-8">' . "\n";
        $html .= '  <meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
        $html .= '  <title>' . $this->escape($title) . '</title>' . "\n";
        $html .= $this->renderStyles();
        $html .= '</head>' . "\n";
        $html .= '<body>' . "\n";
        $html .= '  <div class="container">' . "\n";
        $html .= '    <h1>' . $this->escape($title) . '</h1>' . "\n";
        $html .= '    <p class="timestamp">Generated: ' . date('Y-m-d H:i:s') . '</p>' . "\n";
        $html .= $this->renderSummary($summary);
        $html .= $this->renderIssues($groupedIssues, $basePath);
        $html .= '  </div>' . "\n";
        $html .= '</body>' . "\n";
        $html .= '</html>' . "\n";

        return $html;
    }

    /**
     * Render CSS styles
     *
     * @return string
     */
    private function renderStyles()
    {
        return <<<'CSS'
  <style>
    * { box-sizing: border-box; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      line-height: 1.6;
      color: #333;
      background: #f5f5f5;
      margin: 0;
      padding: 20px;
    }
    .container {
      max-width: 1200px;
      margin: 0 auto;
      background: #fff;
      padding: 30px;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    h1 {
      margin-top: 0;
      color: #2c3e50;
      border-bottom: 2px solid #3498db;
      padding-bottom: 10px;
    }
    h2 {
      color: #34495e;
      margin-top: 30px;
      padding-bottom: 8px;
      border-bottom: 1px solid #eee;
    }
    .timestamp {
      color: #7f8c8d;
      font-size: 0.9em;
      margin-top: -10px;
    }
    .summary {
      display: flex;
      flex-wrap: wrap;
      gap: 15px;
      margin: 20px 0;
    }
    .summary-card {
      background: #f8f9fa;
      padding: 15px 25px;
      border-radius: 6px;
      border-left: 4px solid #3498db;
    }
    .summary-card.error { border-left-color: #e74c3c; }
    .summary-card.warning { border-left-color: #f39c12; }
    .summary-card.info { border-left-color: #3498db; }
    .summary-card .count {
      font-size: 2em;
      font-weight: bold;
      color: #2c3e50;
    }
    .summary-card .label {
      color: #7f8c8d;
      font-size: 0.9em;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
      font-size: 0.9em;
    }
    th, td {
      text-align: left;
      padding: 12px;
      border-bottom: 1px solid #eee;
    }
    th {
      background: #f8f9fa;
      font-weight: 600;
      color: #2c3e50;
    }
    tr:hover { background: #f8f9fa; }
    .severity {
      display: inline-block;
      padding: 3px 8px;
      border-radius: 3px;
      font-size: 0.8em;
      font-weight: 600;
      text-transform: uppercase;
    }
    .severity-error { background: #fce4ec; color: #c0392b; }
    .severity-warning { background: #fff3e0; color: #e67e22; }
    .severity-info { background: #e3f2fd; color: #2980b9; }
    .file-path { font-family: monospace; font-size: 0.85em; }
    .line-number {
      font-family: monospace;
      color: #7f8c8d;
      font-size: 0.85em;
    }
    .no-issues {
      text-align: center;
      color: #27ae60;
      padding: 40px;
      font-size: 1.2em;
    }
    .issue-type {
      background: #ecf0f1;
      padding: 2px 6px;
      border-radius: 3px;
      font-size: 0.8em;
      font-family: monospace;
    }
  </style>
CSS;
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
        $summary = array(
            'total' => count($issues),
            'errors' => 0,
            'warnings' => 0,
            'info' => 0,
            'byType' => array(),
        );

        foreach ($issues as $issue) {
            $severity = $issue->getSeverity();
            $type = $issue->getType();

            switch ($severity) {
                case Issue::SEVERITY_ERROR:
                    $summary['errors']++;
                    break;
                case Issue::SEVERITY_WARNING:
                    $summary['warnings']++;
                    break;
                default:
                    $summary['info']++;
                    break;
            }

            if (!isset($summary['byType'][$type])) {
                $summary['byType'][$type] = 0;
            }
            $summary['byType'][$type]++;
        }

        return $summary;
    }

    /**
     * Render summary section
     *
     * @param array $summary Summary data
     *
     * @return string
     */
    private function renderSummary(array $summary)
    {
        if ($summary['total'] === 0) {
            return '    <div class="no-issues">No issues found!</div>' . "\n";
        }

        $html = '    <div class="summary">' . "\n";
        $html .= '      <div class="summary-card">' . "\n";
        $html .= '        <div class="count">' . $summary['total'] . '</div>' . "\n";
        $html .= '        <div class="label">Total Issues</div>' . "\n";
        $html .= '      </div>' . "\n";

        if ($summary['errors'] > 0) {
            $html .= '      <div class="summary-card error">' . "\n";
            $html .= '        <div class="count">' . $summary['errors'] . '</div>' . "\n";
            $html .= '        <div class="label">Errors</div>' . "\n";
            $html .= '      </div>' . "\n";
        }

        if ($summary['warnings'] > 0) {
            $html .= '      <div class="summary-card warning">' . "\n";
            $html .= '        <div class="count">' . $summary['warnings'] . '</div>' . "\n";
            $html .= '        <div class="label">Warnings</div>' . "\n";
            $html .= '      </div>' . "\n";
        }

        if ($summary['info'] > 0) {
            $html .= '      <div class="summary-card info">' . "\n";
            $html .= '        <div class="count">' . $summary['info'] . '</div>' . "\n";
            $html .= '        <div class="label">Info</div>' . "\n";
            $html .= '      </div>' . "\n";
        }

        $html .= '    </div>' . "\n";

        return $html;
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

        // Sort by type name
        ksort($grouped);

        return $grouped;
    }

    /**
     * Render issues section
     *
     * @param array $groupedIssues Issues grouped by type
     * @param string $basePath Base path for relative paths
     *
     * @return string
     */
    private function renderIssues(array $groupedIssues, $basePath)
    {
        if (empty($groupedIssues)) {
            return '';
        }

        $html = '';

        foreach ($groupedIssues as $type => $issues) {
            $typeTitle = $this->formatTypeTitle($type);
            $html .= '    <h2>' . $this->escape($typeTitle) . ' (' . count($issues) . ')</h2>' . "\n";
            $html .= '    <table>' . "\n";
            $html .= '      <thead>' . "\n";
            $html .= '        <tr>' . "\n";
            $html .= '          <th>Severity</th>' . "\n";
            $html .= '          <th>File</th>' . "\n";
            $html .= '          <th>Line</th>' . "\n";
            $html .= '          <th>Symbol</th>' . "\n";
            $html .= '          <th>Message</th>' . "\n";
            $html .= '        </tr>' . "\n";
            $html .= '      </thead>' . "\n";
            $html .= '      <tbody>' . "\n";

            foreach ($issues as $issue) {
                $html .= $this->renderIssueRow($issue, $basePath);
            }

            $html .= '      </tbody>' . "\n";
            $html .= '    </table>' . "\n";
        }

        return $html;
    }

    /**
     * Render a single issue row
     *
     * @param Issue $issue Issue to render
     * @param string $basePath Base path for relative paths
     *
     * @return string
     */
    private function renderIssueRow(Issue $issue, $basePath)
    {
        $severity = $issue->getSeverity();
        $severityClass = 'severity-' . $severity;
        $filePath = $this->getRelativePath($issue->getFilePath(), $basePath);
        $line = $issue->getLine();
        $symbol = $issue->getSymbolName();
        $message = $issue->getMessage();

        $html = '        <tr>' . "\n";
        $html .= '          <td><span class="severity ' . $severityClass . '">' . $this->escape($severity) . '</span></td>' . "\n";
        $html .= '          <td class="file-path">' . $this->escape($filePath !== null ? $filePath : '-') . '</td>' . "\n";
        $html .= '          <td class="line-number">' . ($line !== null ? $line : '-') . '</td>' . "\n";
        $html .= '          <td>' . $this->escape($symbol !== null ? $symbol : '-') . '</td>' . "\n";
        $html .= '          <td>' . $this->escape($message) . '</td>' . "\n";
        $html .= '        </tr>' . "\n";

        return $html;
    }

    /**
     * Format type as human-readable title
     *
     * @param string $type Issue type
     *
     * @return string
     */
    private function formatTypeTitle($type)
    {
        // Convert "unused-classes" to "Unused Classes"
        $title = str_replace('-', ' ', $type);
        $title = ucwords($title);
        return $title;
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

    /**
     * Escape HTML entities
     *
     * @param string $string String to escape
     *
     * @return string
     */
    private function escape($string)
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
