<?php
/**
 * CSV Reporter
 *
 * Outputs issues in CSV format
 */

namespace PhpKnip\Reporter;

use PhpKnip\Analyzer\Issue;

/**
 * Reporter that outputs CSV format
 */
class CsvReporter implements ReporterInterface
{
    /**
     * CSV column headers
     */
    private static $headers = array(
        'type',
        'severity',
        'file',
        'line',
        'symbol',
        'symbolType',
        'message',
    );

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'csv';
    }

    /**
     * @inheritDoc
     */
    public function getFileExtension()
    {
        return 'csv';
    }

    /**
     * @inheritDoc
     */
    public function report(array $issues, array $options = array())
    {
        $basePath = isset($options['basePath']) ? $options['basePath'] : '';
        $includeHeader = isset($options['includeHeader']) ? $options['includeHeader'] : true;
        $delimiter = isset($options['delimiter']) ? $options['delimiter'] : ',';
        $enclosure = isset($options['enclosure']) ? $options['enclosure'] : '"';

        $output = array();

        // Add header row
        if ($includeHeader) {
            $output[] = $this->formatRow(self::$headers, $delimiter, $enclosure);
        }

        // Add issue rows
        foreach ($issues as $issue) {
            $row = $this->issueToRow($issue, $basePath);
            $output[] = $this->formatRow($row, $delimiter, $enclosure);
        }

        return implode("\n", $output) . "\n";
    }

    /**
     * Convert issue to CSV row
     *
     * @param Issue $issue Issue to convert
     * @param string $basePath Base path for relative paths
     *
     * @return array
     */
    private function issueToRow(Issue $issue, $basePath)
    {
        return array(
            $issue->getType(),
            $issue->getSeverity(),
            $this->getRelativePath($issue->getFilePath(), $basePath),
            $issue->getLine() !== null ? (string) $issue->getLine() : '',
            $issue->getSymbolName() !== null ? $issue->getSymbolName() : '',
            $issue->getSymbolType() !== null ? $issue->getSymbolType() : '',
            $issue->getMessage(),
        );
    }

    /**
     * Format a row as CSV
     *
     * @param array $row Row data
     * @param string $delimiter Field delimiter
     * @param string $enclosure Field enclosure
     *
     * @return string
     */
    private function formatRow(array $row, $delimiter, $enclosure)
    {
        $fields = array();

        foreach ($row as $value) {
            // Escape enclosure characters
            $value = str_replace($enclosure, $enclosure . $enclosure, (string) $value);

            // Enclose if contains delimiter, enclosure, or newline
            if (strpos($value, $delimiter) !== false ||
                strpos($value, $enclosure) !== false ||
                strpos($value, "\n") !== false ||
                strpos($value, "\r") !== false) {
                $value = $enclosure . $value . $enclosure;
            }

            $fields[] = $value;
        }

        return implode($delimiter, $fields);
    }

    /**
     * Get relative path
     *
     * @param string|null $filePath Absolute path
     * @param string $basePath Base path
     *
     * @return string
     */
    private function getRelativePath($filePath, $basePath)
    {
        if ($filePath === null) {
            return '';
        }

        if (!empty($basePath) && strpos($filePath, $basePath) === 0) {
            return ltrim(substr($filePath, strlen($basePath)), '/');
        }

        return $filePath;
    }
}
