<?php
/**
 * CsvReporter Tests
 */

namespace PhpKnip\Tests\Unit\Reporter;

use PhpKnip\Tests\TestCase;
use PhpKnip\Reporter\CsvReporter;
use PhpKnip\Analyzer\Issue;

class CsvReporterTest extends TestCase
{
    /**
     * @var CsvReporter
     */
    private $reporter;

    protected function setUp(): void
    {
        $this->reporter = new CsvReporter();
    }

    public function testGetName()
    {
        $this->assertEquals('csv', $this->reporter->getName());
    }

    public function testGetFileExtension()
    {
        $this->assertEquals('csv', $this->reporter->getFileExtension());
    }

    public function testEmptyIssuesReturnsHeaderOnly()
    {
        $output = $this->reporter->report(array());

        $lines = explode("\n", trim($output));
        $this->assertCount(1, $lines);
        $this->assertStringContains('type,severity,file,line,symbol,symbolType,message', $output);
    }

    public function testHeaderCanBeDisabled()
    {
        $output = $this->reporter->report(array(), array('includeHeader' => false));

        $this->assertEquals("\n", $output);
    }

    public function testSingleIssueOutputsCorrectColumns()
    {
        $issue = Issue::unusedClass('App\\UnusedClass', '/src/UnusedClass.php', 10);

        $output = $this->reporter->report(array($issue));

        $lines = explode("\n", trim($output));
        $this->assertCount(2, $lines); // header + 1 issue

        $this->assertStringContains('unused-classes', $output);
        $this->assertStringContains('error', $output);
        $this->assertStringContains('/src/UnusedClass.php', $output);
        $this->assertStringContains('10', $output);
        $this->assertStringContains('App\\UnusedClass', $output);
        $this->assertStringContains('class', $output);
    }

    public function testMultipleIssuesGenerateMultipleRows()
    {
        $issues = array(
            Issue::unusedClass('App\\A', '/src/A.php', 10),
            Issue::unusedClass('App\\B', '/src/B.php', 20),
            Issue::unusedFunction('App\\func', '/src/helpers.php', 5),
        );

        $output = $this->reporter->report($issues);

        $lines = explode("\n", trim($output));
        $this->assertCount(4, $lines); // header + 3 issues
    }

    public function testRelativePathsWithBasePath()
    {
        $issue = Issue::unusedClass('App\\Test', '/var/www/project/src/Test.php', 10);

        $output = $this->reporter->report(
            array($issue),
            array('basePath' => '/var/www/project')
        );

        $this->assertStringContains('src/Test.php', $output);
        $this->assertStringNotContains('/var/www/project', $output);
    }

    public function testCustomDelimiter()
    {
        $issue = Issue::unusedClass('App\\Test', '/src/Test.php', 10);

        $output = $this->reporter->report(array($issue), array('delimiter' => ';'));

        $this->assertStringContains(';', $output);
        // Should use semicolon instead of comma
        $lines = explode("\n", trim($output));
        $this->assertStringContains('type;severity;file', $lines[0]);
    }

    public function testCustomEnclosure()
    {
        $issue = new Issue(Issue::TYPE_UNUSED_CLASS, 'Message with "quotes"', Issue::SEVERITY_ERROR);
        $issue->setSymbolName('Test');
        $issue->setFilePath('/test.php');
        $issue->setLine(1);
        $issue->setSymbolType('class');

        $output = $this->reporter->report(array($issue), array('enclosure' => "'"));

        // With single-quote enclosure, double quotes in message don't need escaping
        $this->assertStringContains('Message with "quotes"', $output);
    }

    public function testValuesWithCommasAreQuoted()
    {
        $issue = new Issue(Issue::TYPE_UNUSED_CLASS, 'Message, with comma', Issue::SEVERITY_ERROR);
        $issue->setSymbolName('Test');
        $issue->setFilePath('/test.php');
        $issue->setLine(1);
        $issue->setSymbolType('class');

        $output = $this->reporter->report(array($issue));

        $this->assertStringContains('"Message, with comma"', $output);
    }

    public function testValuesWithQuotesAreEscaped()
    {
        $issue = new Issue(Issue::TYPE_UNUSED_CLASS, 'Say "hello"', Issue::SEVERITY_ERROR);
        $issue->setSymbolName('Test');
        $issue->setFilePath('/test.php');
        $issue->setLine(1);
        $issue->setSymbolType('class');

        $output = $this->reporter->report(array($issue));

        // Quotes should be doubled and the field should be enclosed
        $this->assertStringContains('""hello""', $output);
    }

    public function testValuesWithNewlinesAreQuoted()
    {
        $issue = new Issue(Issue::TYPE_UNUSED_CLASS, "Line1\nLine2", Issue::SEVERITY_ERROR);
        $issue->setSymbolName('Test');
        $issue->setFilePath('/test.php');
        $issue->setLine(1);
        $issue->setSymbolType('class');

        $output = $this->reporter->report(array($issue));

        $this->assertStringContains('"', $output);
    }

    public function testNullValuesAreEmptyStrings()
    {
        $issue = Issue::unusedFile('/src/orphan.php');
        // unusedFile has no line number

        $output = $this->reporter->report(array($issue));

        // Line column should be empty, not "null"
        $this->assertStringNotContains('null', strtolower($output));
    }

    public function testAllColumnsPresent()
    {
        $issue = Issue::unusedClass('App\\Test', '/src/Test.php', 42);

        $output = $this->reporter->report(array($issue));

        // Check header columns
        $this->assertStringContains('type', $output);
        $this->assertStringContains('severity', $output);
        $this->assertStringContains('file', $output);
        $this->assertStringContains('line', $output);
        $this->assertStringContains('symbol', $output);
        $this->assertStringContains('symbolType', $output);
        $this->assertStringContains('message', $output);
    }

    public function testSeverityValuesAreCorrect()
    {
        $issues = array(
            Issue::unusedClass('App\\Error', '/src/Error.php', 10),  // error
            Issue::unusedUseStatement('App\\Warn', '/src/Warn.php', 5),  // warning
        );

        $output = $this->reporter->report($issues);

        $this->assertStringContains(',error,', $output);
        $this->assertStringContains(',warning,', $output);
    }

    public function testOutputEndsWithNewline()
    {
        $issue = Issue::unusedClass('App\\Test', '/src/Test.php', 10);

        $output = $this->reporter->report(array($issue));

        $this->assertStringEndsWith("\n", $output);
    }
}
