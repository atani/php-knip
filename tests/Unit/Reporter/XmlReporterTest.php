<?php
/**
 * XmlReporter Tests
 */

namespace PhpKnip\Tests\Unit\Reporter;

use PhpKnip\Tests\TestCase;
use PhpKnip\Reporter\XmlReporter;
use PhpKnip\Analyzer\Issue;

class XmlReporterTest extends TestCase
{
    /**
     * @var XmlReporter
     */
    private $reporter;

    protected function setUp(): void
    {
        $this->reporter = new XmlReporter();
    }

    public function testGetName()
    {
        $this->assertEquals('xml', $this->reporter->getName());
    }

    public function testGetFileExtension()
    {
        $this->assertEquals('xml', $this->reporter->getFileExtension());
    }

    public function testEmptyIssues()
    {
        $output = $this->reporter->report(array());

        $this->assertStringContains('<?xml version="1.0" encoding="UTF-8"?>', $output);
        $this->assertStringContains('<phpknip', $output);
        $this->assertStringContains('<summary>', $output);
        $this->assertStringContains('<total>0</total>', $output);
        $this->assertStringContains('<issues/>', $output);
    }

    public function testSingleIssue()
    {
        $issue = new Issue(
            Issue::TYPE_UNUSED_CLASS,
            "Class 'UnusedClass' is declared but never used",
            Issue::SEVERITY_WARNING
        );
        $issue->setFilePath('/path/to/UnusedClass.php');
        $issue->setLine(10);
        $issue->setSymbolName('UnusedClass');
        $issue->setSymbolType('class');

        $output = $this->reporter->report(array($issue));

        $this->assertStringContains('<total>1</total>', $output);
        $this->assertStringContains('type="unused-classes"', $output);
        $this->assertStringContains('severity="warning"', $output);
        $this->assertStringContains('file="/path/to/UnusedClass.php"', $output);
        $this->assertStringContains('line="10"', $output);
        $this->assertStringContains('<symbol name="UnusedClass" type="class"/>', $output);
    }

    public function testMultipleIssues()
    {
        $issues = array(
            new Issue(Issue::TYPE_UNUSED_CLASS, 'Class A unused', Issue::SEVERITY_WARNING),
            new Issue(Issue::TYPE_UNUSED_FUNCTION, 'Function B unused', Issue::SEVERITY_WARNING),
            new Issue(Issue::TYPE_UNUSED_USE, 'Use C unused', Issue::SEVERITY_INFO),
        );

        $output = $this->reporter->report($issues);

        $this->assertStringContains('<total>3</total>', $output);
        $this->assertStringContains('<warning>2</warning>', $output);
        $this->assertStringContains('<info>1</info>', $output);
    }

    public function testSeverityCounts()
    {
        $issues = array(
            new Issue(Issue::TYPE_UNUSED_CLASS, 'Error 1', Issue::SEVERITY_ERROR),
            new Issue(Issue::TYPE_UNUSED_CLASS, 'Warning 1', Issue::SEVERITY_WARNING),
            new Issue(Issue::TYPE_UNUSED_CLASS, 'Warning 2', Issue::SEVERITY_WARNING),
            new Issue(Issue::TYPE_UNUSED_CLASS, 'Info 1', Issue::SEVERITY_INFO),
        );

        $output = $this->reporter->report($issues);

        $this->assertStringContains('<error>1</error>', $output);
        $this->assertStringContains('<warning>2</warning>', $output);
        $this->assertStringContains('<info>1</info>', $output);
    }

    public function testGroupByFile()
    {
        $issue1 = new Issue(Issue::TYPE_UNUSED_CLASS, 'Class A unused', Issue::SEVERITY_WARNING);
        $issue1->setFilePath('/path/to/FileA.php');

        $issue2 = new Issue(Issue::TYPE_UNUSED_FUNCTION, 'Function B unused', Issue::SEVERITY_WARNING);
        $issue2->setFilePath('/path/to/FileA.php');

        $issue3 = new Issue(Issue::TYPE_UNUSED_CLASS, 'Class C unused', Issue::SEVERITY_WARNING);
        $issue3->setFilePath('/path/to/FileB.php');

        $output = $this->reporter->report(
            array($issue1, $issue2, $issue3),
            array('groupBy' => 'file')
        );

        $this->assertStringContains('<file path="/path/to/FileA.php">', $output);
        $this->assertStringContains('<file path="/path/to/FileB.php">', $output);
    }

    public function testGroupByType()
    {
        $issue1 = new Issue(Issue::TYPE_UNUSED_CLASS, 'Class A unused', Issue::SEVERITY_WARNING);
        $issue2 = new Issue(Issue::TYPE_UNUSED_CLASS, 'Class B unused', Issue::SEVERITY_WARNING);
        $issue3 = new Issue(Issue::TYPE_UNUSED_FUNCTION, 'Function C unused', Issue::SEVERITY_WARNING);

        $output = $this->reporter->report(
            array($issue1, $issue2, $issue3),
            array('groupBy' => 'type')
        );

        $this->assertStringContains('<type name="unused-classes">', $output);
        $this->assertStringContains('<type name="unused-functions">', $output);
    }

    public function testIssueWithMetadata()
    {
        $issue = new Issue(Issue::TYPE_UNUSED_DEPENDENCY, 'Unused dependency', Issue::SEVERITY_WARNING);
        $issue->setSymbolName('vendor/package');
        $issue->setMetadata('isDev', true);
        $issue->setMetadata('version', '1.0.0');

        $output = $this->reporter->report(array($issue));

        $this->assertStringContains('<metadata>', $output);
        $this->assertStringContains('key="isDev"', $output);
        $this->assertStringContains('value="true"', $output);
        $this->assertStringContains('key="version"', $output);
        $this->assertStringContains('value="1.0.0"', $output);
    }

    public function testOutputIsValidXml()
    {
        $issues = array(
            new Issue(Issue::TYPE_UNUSED_CLASS, 'Class A unused', Issue::SEVERITY_WARNING),
            new Issue(Issue::TYPE_UNUSED_FUNCTION, 'Function B unused', Issue::SEVERITY_WARNING),
        );

        $output = $this->reporter->report($issues);

        $dom = new \DOMDocument();
        $result = $dom->loadXML($output);

        $this->assertTrue($result, 'Output should be valid XML');
    }

    public function testTimestampIncluded()
    {
        $output = $this->reporter->report(array());

        $this->assertStringContains('timestamp="', $output);
    }

    public function testVersionIncluded()
    {
        $output = $this->reporter->report(array());

        $this->assertStringContains('version="0.1.0"', $output);
    }

    public function testMessageWithSpecialCharacters()
    {
        $issue = new Issue(
            Issue::TYPE_UNUSED_CLASS,
            "Class 'Test<Foo>&Bar' is unused",
            Issue::SEVERITY_WARNING
        );

        $output = $this->reporter->report(array($issue));

        // Should be wrapped in CDATA
        $this->assertStringContains('<![CDATA[', $output);
        $this->assertStringContains("Class 'Test<Foo>&Bar' is unused", $output);

        // Output should still be valid XML
        $dom = new \DOMDocument();
        $result = $dom->loadXML($output);
        $this->assertTrue($result);
    }

    public function testPrettyPrintOption()
    {
        $issue = new Issue(Issue::TYPE_UNUSED_CLASS, 'Test', Issue::SEVERITY_WARNING);

        $prettyOutput = $this->reporter->report(array($issue), array('pretty' => true));
        $compactOutput = $this->reporter->report(array($issue), array('pretty' => false));

        // Pretty output should have more newlines
        $this->assertGreaterThan(
            substr_count($compactOutput, "\n"),
            substr_count($prettyOutput, "\n")
        );
    }

    public function testByTypeCountsInSummary()
    {
        $issues = array(
            new Issue(Issue::TYPE_UNUSED_CLASS, 'Class A', Issue::SEVERITY_WARNING),
            new Issue(Issue::TYPE_UNUSED_CLASS, 'Class B', Issue::SEVERITY_WARNING),
            new Issue(Issue::TYPE_UNUSED_FUNCTION, 'Function C', Issue::SEVERITY_WARNING),
        );

        $output = $this->reporter->report($issues);

        $this->assertStringContains('<by-type>', $output);
        $this->assertStringContains('name="unused-classes"', $output);
        $this->assertStringContains('count="2"', $output);
        $this->assertStringContains('name="unused-functions"', $output);
        $this->assertStringContains('count="1"', $output);
    }
}
