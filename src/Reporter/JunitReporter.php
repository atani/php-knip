<?php
/**
 * JUnit Reporter
 *
 * Outputs analysis results in JUnit XML format for CI integration
 */

namespace PhpKnip\Reporter;

use PhpKnip\Analyzer\Issue;

/**
 * JUnit XML format reporter for CI systems
 *
 * This reporter outputs issues in JUnit XML format which is widely
 * supported by CI systems like Jenkins, GitHub Actions, GitLab CI, etc.
 *
 * Each issue type is treated as a test suite, and each issue becomes
 * a test case with a failure.
 */
class JunitReporter implements ReporterInterface
{
    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'junit';
    }

    /**
     * @inheritDoc
     */
    public function report(array $issues, array $options = array())
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = isset($options['pretty']) ? (bool) $options['pretty'] : true;

        // Root testsuites element
        $testsuites = $dom->createElement('testsuites');
        $testsuites->setAttribute('name', 'php-knip');
        $testsuites->setAttribute('time', '0');
        $dom->appendChild($testsuites);

        // Group issues by type (each type becomes a testsuite)
        $groupedByType = $this->groupByType($issues);

        $totalTests = 0;
        $totalFailures = 0;
        $totalErrors = 0;

        foreach ($groupedByType as $type => $typeIssues) {
            $testsuite = $this->createTestSuite($dom, $type, $typeIssues);
            $testsuites->appendChild($testsuite);

            $totalTests += count($typeIssues);
            foreach ($typeIssues as $issue) {
                if ($issue->getSeverity() === Issue::SEVERITY_ERROR) {
                    $totalErrors++;
                } else {
                    $totalFailures++;
                }
            }
        }

        // Add summary attributes to root
        $testsuites->setAttribute('tests', $totalTests);
        $testsuites->setAttribute('failures', $totalFailures);
        $testsuites->setAttribute('errors', $totalErrors);

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
     * Create a testsuite element for a type of issues
     *
     * @param \DOMDocument $dom DOM document
     * @param string $type Issue type
     * @param array $issues Issues of this type
     *
     * @return \DOMElement
     */
    private function createTestSuite(\DOMDocument $dom, $type, array $issues)
    {
        $testsuite = $dom->createElement('testsuite');
        $testsuite->setAttribute('name', $this->formatTypeName($type));
        $testsuite->setAttribute('tests', count($issues));

        $failures = 0;
        $errors = 0;

        foreach ($issues as $issue) {
            if ($issue->getSeverity() === Issue::SEVERITY_ERROR) {
                $errors++;
            } else {
                $failures++;
            }
        }

        $testsuite->setAttribute('failures', $failures);
        $testsuite->setAttribute('errors', $errors);
        $testsuite->setAttribute('time', '0');

        // Create test cases
        foreach ($issues as $issue) {
            $testcase = $this->createTestCase($dom, $issue);
            $testsuite->appendChild($testcase);
        }

        return $testsuite;
    }

    /**
     * Create a testcase element for an issue
     *
     * @param \DOMDocument $dom DOM document
     * @param Issue $issue Issue
     *
     * @return \DOMElement
     */
    private function createTestCase(\DOMDocument $dom, Issue $issue)
    {
        $testcase = $dom->createElement('testcase');

        // Name is the symbol name or a generated name
        $name = $issue->getSymbolName() ?: $this->generateIssueName($issue);
        $testcase->setAttribute('name', $name);

        // Classname is the file path (without extension) or issue type
        $classname = $issue->getFilePath()
            ? $this->pathToClassname($issue->getFilePath())
            : $issue->getType();
        $testcase->setAttribute('classname', $classname);

        $testcase->setAttribute('time', '0');

        // Add file and line if available
        if ($issue->getFilePath()) {
            $testcase->setAttribute('file', $issue->getFilePath());
        }

        if ($issue->getLine()) {
            $testcase->setAttribute('line', $issue->getLine());
        }

        // Create failure or error element based on severity
        if ($issue->getSeverity() === Issue::SEVERITY_ERROR) {
            $resultElement = $dom->createElement('error');
        } else {
            $resultElement = $dom->createElement('failure');
        }

        $resultElement->setAttribute('type', $issue->getType());
        $resultElement->setAttribute('message', $issue->getMessage());

        // Add detailed content
        $content = $this->formatIssueContent($issue);
        $resultElement->appendChild($dom->createCDATASection($content));

        $testcase->appendChild($resultElement);

        return $testcase;
    }

    /**
     * Format issue type name for display
     *
     * @param string $type Type
     *
     * @return string
     */
    private function formatTypeName($type)
    {
        return str_replace(array('-', '_'), ' ', ucfirst($type));
    }

    /**
     * Generate a name for an issue without a symbol name
     *
     * @param Issue $issue Issue
     *
     * @return string
     */
    private function generateIssueName(Issue $issue)
    {
        $parts = array($issue->getType());

        if ($issue->getFilePath()) {
            $parts[] = basename($issue->getFilePath());
        }

        if ($issue->getLine()) {
            $parts[] = 'line ' . $issue->getLine();
        }

        return implode(' - ', $parts);
    }

    /**
     * Convert file path to JUnit classname format
     *
     * @param string $path File path
     *
     * @return string
     */
    private function pathToClassname($path)
    {
        // Remove extension
        $classname = preg_replace('/\.[^.]+$/', '', $path);

        // Convert path separators to dots
        $classname = str_replace(array('/', '\\'), '.', $classname);

        // Remove leading dots
        $classname = ltrim($classname, '.');

        return $classname;
    }

    /**
     * Format issue content for detailed output
     *
     * @param Issue $issue Issue
     *
     * @return string
     */
    private function formatIssueContent(Issue $issue)
    {
        $lines = array();

        $lines[] = 'Type: ' . $issue->getType();
        $lines[] = 'Severity: ' . $issue->getSeverity();
        $lines[] = 'Message: ' . $issue->getMessage();

        if ($issue->getFilePath()) {
            $location = $issue->getFilePath();
            if ($issue->getLine()) {
                $location .= ':' . $issue->getLine();
            }
            $lines[] = 'Location: ' . $location;
        }

        if ($issue->getSymbolName()) {
            $symbol = $issue->getSymbolName();
            if ($issue->getSymbolType()) {
                $symbol .= ' (' . $issue->getSymbolType() . ')';
            }
            $lines[] = 'Symbol: ' . $symbol;
        }

        $metadata = $issue->getMetadata();
        if (!empty($metadata)) {
            $lines[] = 'Metadata:';
            foreach ($metadata as $key => $value) {
                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                } elseif (is_array($value)) {
                    $value = json_encode($value);
                }
                $lines[] = '  ' . $key . ': ' . $value;
            }
        }

        return implode("\n", $lines);
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
