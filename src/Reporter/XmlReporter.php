<?php
/**
 * XML Reporter
 *
 * Outputs analysis results in XML format
 */

namespace PhpKnip\Reporter;

use PhpKnip\Analyzer\Issue;

/**
 * XML format reporter for analysis results
 */
class XmlReporter implements ReporterInterface
{
    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'xml';
    }

    /**
     * @inheritDoc
     */
    public function report(array $issues, array $options = array())
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = isset($options['pretty']) ? (bool) $options['pretty'] : true;

        // Root element
        $root = $dom->createElement('phpknip');
        $root->setAttribute('version', '0.1.0');
        $root->setAttribute('timestamp', date('c'));
        $dom->appendChild($root);

        // Summary element
        $summary = $this->createSummaryElement($dom, $issues);
        $root->appendChild($summary);

        // Issues element
        $issuesElement = $this->createIssuesElement($dom, $issues, $options);
        $root->appendChild($issuesElement);

        return $dom->saveXML();
    }

    /**
     * @inheritDoc
     */
    public function getFileExtension()
    {
        return 'xml';
    }

    /**
     * Create summary element
     *
     * @param \DOMDocument $dom DOM document
     * @param array $issues Issues
     *
     * @return \DOMElement
     */
    private function createSummaryElement(\DOMDocument $dom, array $issues)
    {
        $summary = $dom->createElement('summary');

        // Total count
        $total = $dom->createElement('total', count($issues));
        $summary->appendChild($total);

        // By severity
        $severityCounts = $this->countBySeverity($issues);
        $bySeverity = $dom->createElement('by-severity');
        foreach ($severityCounts as $severity => $count) {
            $severityElement = $dom->createElement($severity, $count);
            $bySeverity->appendChild($severityElement);
        }
        $summary->appendChild($bySeverity);

        // By type
        $typeCounts = $this->countByType($issues);
        $byType = $dom->createElement('by-type');
        foreach ($typeCounts as $type => $count) {
            $typeElement = $dom->createElement('type');
            $typeElement->setAttribute('name', $type);
            $typeElement->setAttribute('count', $count);
            $byType->appendChild($typeElement);
        }
        $summary->appendChild($byType);

        return $summary;
    }

    /**
     * Create issues element
     *
     * @param \DOMDocument $dom DOM document
     * @param array $issues Issues
     * @param array $options Options
     *
     * @return \DOMElement
     */
    private function createIssuesElement(\DOMDocument $dom, array $issues, array $options)
    {
        $issuesElement = $dom->createElement('issues');

        $groupBy = isset($options['groupBy']) ? $options['groupBy'] : null;

        if ($groupBy === 'file') {
            $grouped = $this->groupByFile($issues);
            foreach ($grouped as $file => $fileIssues) {
                $fileElement = $dom->createElement('file');
                $fileElement->setAttribute('path', $file);
                foreach ($fileIssues as $issue) {
                    $issueElement = $this->createIssueElement($dom, $issue);
                    $fileElement->appendChild($issueElement);
                }
                $issuesElement->appendChild($fileElement);
            }
        } elseif ($groupBy === 'type') {
            $grouped = $this->groupByType($issues);
            foreach ($grouped as $type => $typeIssues) {
                $typeElement = $dom->createElement('type');
                $typeElement->setAttribute('name', $type);
                foreach ($typeIssues as $issue) {
                    $issueElement = $this->createIssueElement($dom, $issue);
                    $typeElement->appendChild($issueElement);
                }
                $issuesElement->appendChild($typeElement);
            }
        } else {
            foreach ($issues as $issue) {
                $issueElement = $this->createIssueElement($dom, $issue);
                $issuesElement->appendChild($issueElement);
            }
        }

        return $issuesElement;
    }

    /**
     * Create issue element
     *
     * @param \DOMDocument $dom DOM document
     * @param Issue $issue Issue
     *
     * @return \DOMElement
     */
    private function createIssueElement(\DOMDocument $dom, Issue $issue)
    {
        $element = $dom->createElement('issue');

        $element->setAttribute('type', $issue->getType());
        $element->setAttribute('severity', $issue->getSeverity());

        if ($issue->getFilePath()) {
            $element->setAttribute('file', $issue->getFilePath());
        }

        if ($issue->getLine()) {
            $element->setAttribute('line', $issue->getLine());
        }

        // Message element
        $message = $dom->createElement('message');
        $message->appendChild($dom->createCDATASection($issue->getMessage()));
        $element->appendChild($message);

        // Symbol information
        if ($issue->getSymbolName()) {
            $symbol = $dom->createElement('symbol');
            $symbol->setAttribute('name', $issue->getSymbolName());
            if ($issue->getSymbolType()) {
                $symbol->setAttribute('type', $issue->getSymbolType());
            }
            $element->appendChild($symbol);
        }

        // Metadata
        $metadata = $issue->getMetadata();
        if (!empty($metadata)) {
            $metadataElement = $dom->createElement('metadata');
            foreach ($metadata as $key => $value) {
                $item = $dom->createElement('item');
                $item->setAttribute('key', $key);
                if (is_bool($value)) {
                    $item->setAttribute('value', $value ? 'true' : 'false');
                } elseif (is_array($value)) {
                    $item->setAttribute('value', json_encode($value));
                } else {
                    $item->setAttribute('value', (string) $value);
                }
                $metadataElement->appendChild($item);
            }
            $element->appendChild($metadataElement);
        }

        return $element;
    }

    /**
     * Count issues by severity
     *
     * @param array $issues Issues
     *
     * @return array
     */
    private function countBySeverity(array $issues)
    {
        $counts = array(
            Issue::SEVERITY_ERROR => 0,
            Issue::SEVERITY_WARNING => 0,
            Issue::SEVERITY_INFO => 0,
        );

        foreach ($issues as $issue) {
            $severity = $issue->getSeverity();
            if (isset($counts[$severity])) {
                $counts[$severity]++;
            }
        }

        return $counts;
    }

    /**
     * Count issues by type
     *
     * @param array $issues Issues
     *
     * @return array
     */
    private function countByType(array $issues)
    {
        $counts = array();

        foreach ($issues as $issue) {
            $type = $issue->getType();
            if (!isset($counts[$type])) {
                $counts[$type] = 0;
            }
            $counts[$type]++;
        }

        return $counts;
    }

    /**
     * Group issues by file
     *
     * @param array $issues Issues
     *
     * @return array
     */
    private function groupByFile(array $issues)
    {
        $grouped = array();

        foreach ($issues as $issue) {
            $file = $issue->getFilePath() ?: '(unknown)';
            if (!isset($grouped[$file])) {
                $grouped[$file] = array();
            }
            $grouped[$file][] = $issue;
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * Group issues by type
     *
     * @param array $issues Issues
     *
     * @return array
     */
    private function groupByType(array $issues)
    {
        $grouped = array();

        foreach ($issues as $issue) {
            $type = $issue->getType();
            if (!isset($grouped[$type])) {
                $grouped[$type] = array();
            }
            $grouped[$type][] = $issue;
        }

        ksort($grouped);

        return $grouped;
    }
}
