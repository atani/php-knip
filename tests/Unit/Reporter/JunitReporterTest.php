<?php
/**
 * JunitReporter Tests
 */

namespace PhpKnip\Tests\Unit\Reporter;

use PhpKnip\Tests\TestCase;
use PhpKnip\Reporter\JunitReporter;
use PhpKnip\Analyzer\Issue;

class JunitReporterTest extends TestCase
{
    /**
     * @var JunitReporter
     */
    private $reporter;

    protected function setUp(): void
    {
        $this->reporter = new JunitReporter();
    }

    public function testGetName()
    {
        $this->assertEquals('junit', $this->reporter->getName());
    }

    public function testGetFileExtension()
    {
        $this->assertEquals('xml', $this->reporter->getFileExtension());
    }

    public function testEmptyIssues()
    {
        $output = $this->reporter->report(array());

        $this->assertContains('<?xml version="1.0" encoding="UTF-8"?>', $output);
        $this->assertContains('<testsuites name="php-knip"', $output);
        $this->assertContains('tests="0"', $output);
        $this->assertContains('failures="0"', $output);
        $this->assertContains('errors="0"', $output);
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

        $this->assertContains('tests="1"', $output);
        $this->assertContains('failures="1"', $output);
        $this->assertContains('<testsuite name="Unused classes"', $output);
        $this->assertContains('<testcase name="UnusedClass"', $output);
        $this->assertContains('<failure type="unused-classes"', $output);
    }

    public function testErrorSeverityCreatesErrorElement()
    {
        $issue = new Issue(
            Issue::TYPE_UNUSED_CLASS,
            'Critical error',
            Issue::SEVERITY_ERROR
        );
        $issue->setSymbolName('ErrorClass');

        $output = $this->reporter->report(array($issue));

        $this->assertContains('errors="1"', $output);
        $this->assertContains('failures="0"', $output);
        $this->assertContains('<error type="unused-classes"', $output);
    }

    public function testWarningSeverityCreatesFailureElement()
    {
        $issue = new Issue(
            Issue::TYPE_UNUSED_CLASS,
            'Warning issue',
            Issue::SEVERITY_WARNING
        );
        $issue->setSymbolName('WarningClass');

        $output = $this->reporter->report(array($issue));

        $this->assertContains('failures="1"', $output);
        $this->assertContains('<failure type="unused-classes"', $output);
    }

    public function testInfoSeverityCreatesFailureElement()
    {
        $issue = new Issue(
            Issue::TYPE_UNUSED_USE,
            'Info issue',
            Issue::SEVERITY_INFO
        );
        $issue->setSymbolName('InfoImport');

        $output = $this->reporter->report(array($issue));

        $this->assertContains('failures="1"', $output);
        $this->assertContains('<failure type="unused-use-statements"', $output);
    }

    public function testMultipleIssuesGroupedByType()
    {
        $issues = array(
            new Issue(Issue::TYPE_UNUSED_CLASS, 'Class A unused', Issue::SEVERITY_WARNING),
            new Issue(Issue::TYPE_UNUSED_CLASS, 'Class B unused', Issue::SEVERITY_WARNING),
            new Issue(Issue::TYPE_UNUSED_FUNCTION, 'Function C unused', Issue::SEVERITY_WARNING),
        );

        $output = $this->reporter->report($issues);

        $this->assertContains('tests="3"', $output);
        $this->assertContains('<testsuite name="Unused classes"', $output);
        $this->assertContains('<testsuite name="Unused functions"', $output);
    }

    public function testTestsuiteHasCorrectCounts()
    {
        $issues = array(
            new Issue(Issue::TYPE_UNUSED_CLASS, 'Error 1', Issue::SEVERITY_ERROR),
            new Issue(Issue::TYPE_UNUSED_CLASS, 'Warning 1', Issue::SEVERITY_WARNING),
        );

        $output = $this->reporter->report($issues);

        // Parse XML to check testsuite counts
        $dom = new \DOMDocument();
        $dom->loadXML($output);

        $testsuites = $dom->getElementsByTagName('testsuite');
        $testsuite = $testsuites->item(0);

        $this->assertEquals('2', $testsuite->getAttribute('tests'));
        $this->assertEquals('1', $testsuite->getAttribute('failures'));
        $this->assertEquals('1', $testsuite->getAttribute('errors'));
    }

    public function testTestcaseHasFileAndLine()
    {
        $issue = new Issue(Issue::TYPE_UNUSED_CLASS, 'Test', Issue::SEVERITY_WARNING);
        $issue->setFilePath('/path/to/Test.php');
        $issue->setLine(42);
        $issue->setSymbolName('TestClass');

        $output = $this->reporter->report(array($issue));

        $this->assertContains('file="/path/to/Test.php"', $output);
        $this->assertContains('line="42"', $output);
    }

    public function testTestcaseClassnameFromFilePath()
    {
        $issue = new Issue(Issue::TYPE_UNUSED_CLASS, 'Test', Issue::SEVERITY_WARNING);
        $issue->setFilePath('src/Service/UserService.php');
        $issue->setSymbolName('UserService');

        $output = $this->reporter->report(array($issue));

        $this->assertContains('classname="src.Service.UserService"', $output);
    }

    public function testOutputIsValidXml()
    {
        $issues = array(
            new Issue(Issue::TYPE_UNUSED_CLASS, 'Class A unused', Issue::SEVERITY_WARNING),
            new Issue(Issue::TYPE_UNUSED_FUNCTION, 'Function B unused', Issue::SEVERITY_ERROR),
        );

        $output = $this->reporter->report($issues);

        $dom = new \DOMDocument();
        $result = $dom->loadXML($output);

        $this->assertTrue($result, 'Output should be valid XML');
    }

    public function testDetailedContentInFailure()
    {
        $issue = new Issue(Issue::TYPE_UNUSED_DEPENDENCY, 'Package unused', Issue::SEVERITY_WARNING);
        $issue->setFilePath('/path/to/composer.json');
        $issue->setSymbolName('vendor/package');
        $issue->setSymbolType('dependency');
        $issue->setMetadata('isDev', true);

        $output = $this->reporter->report(array($issue));

        // Content should include detailed information
        $this->assertContains('Type: unused-dependencies', $output);
        $this->assertContains('Severity: warning', $output);
        $this->assertContains('Symbol: vendor/package (dependency)', $output);
        $this->assertContains('isDev: true', $output);
    }

    public function testIssueWithoutSymbolNameGeneratesName()
    {
        $issue = new Issue(Issue::TYPE_UNUSED_USE, 'Unused import', Issue::SEVERITY_WARNING);
        $issue->setFilePath('/path/to/File.php');
        $issue->setLine(5);

        $output = $this->reporter->report(array($issue));

        // Should generate a name from type, file, line
        $this->assertContains('name="unused-use-statements - File.php - line 5"', $output);
    }

    public function testMessageWithSpecialCharacters()
    {
        $issue = new Issue(
            Issue::TYPE_UNUSED_CLASS,
            "Class 'Test<Foo>&Bar' is unused",
            Issue::SEVERITY_WARNING
        );
        $issue->setSymbolName('Test');

        $output = $this->reporter->report(array($issue));

        // Output should still be valid XML
        $dom = new \DOMDocument();
        $result = $dom->loadXML($output);
        $this->assertTrue($result);

        // Should be in CDATA
        $this->assertContains('<![CDATA[', $output);
    }

    public function testPrettyPrintOption()
    {
        $issue = new Issue(Issue::TYPE_UNUSED_CLASS, 'Test', Issue::SEVERITY_WARNING);
        $issue->setSymbolName('TestClass');

        $prettyOutput = $this->reporter->report(array($issue), array('pretty' => true));
        $compactOutput = $this->reporter->report(array($issue), array('pretty' => false));

        // Pretty output should have more newlines
        $this->assertGreaterThan(
            substr_count($compactOutput, "\n"),
            substr_count($prettyOutput, "\n")
        );
    }

    public function testRootElementHasTotalCounts()
    {
        $issues = array(
            new Issue(Issue::TYPE_UNUSED_CLASS, 'Error 1', Issue::SEVERITY_ERROR),
            new Issue(Issue::TYPE_UNUSED_CLASS, 'Warning 1', Issue::SEVERITY_WARNING),
            new Issue(Issue::TYPE_UNUSED_FUNCTION, 'Warning 2', Issue::SEVERITY_WARNING),
        );

        $output = $this->reporter->report($issues);

        // Parse XML to check root attributes
        $dom = new \DOMDocument();
        $dom->loadXML($output);

        $testsuites = $dom->getElementsByTagName('testsuites')->item(0);

        $this->assertEquals('3', $testsuites->getAttribute('tests'));
        $this->assertEquals('2', $testsuites->getAttribute('failures'));
        $this->assertEquals('1', $testsuites->getAttribute('errors'));
    }

    public function testTestcaseWithoutFileHasTypeAsClassname()
    {
        $issue = new Issue(Issue::TYPE_UNUSED_DEPENDENCY, 'Test', Issue::SEVERITY_WARNING);
        $issue->setSymbolName('vendor/package');

        $output = $this->reporter->report(array($issue));

        $this->assertContains('classname="unused-dependencies"', $output);
    }

    public function testTypeNameIsFormatted()
    {
        $issue = new Issue(Issue::TYPE_UNUSED_USE, 'Test', Issue::SEVERITY_WARNING);
        $issue->setSymbolName('TestImport');

        $output = $this->reporter->report(array($issue));

        // "unused-use-statements" should become "Unused use statements"
        $this->assertContains('name="Unused use statements"', $output);
    }
}
