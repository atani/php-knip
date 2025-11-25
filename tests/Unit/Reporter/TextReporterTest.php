<?php
/**
 * TextReporter Tests
 */

namespace PhpKnip\Tests\Unit\Reporter;

use PHPUnit\Framework\TestCase;
use PhpKnip\Reporter\TextReporter;
use PhpKnip\Analyzer\Issue;

class TextReporterTest extends TestCase
{
    /**
     * @var TextReporter
     */
    private $reporter;

    protected function setUp()
    {
        $this->reporter = new TextReporter();
    }

    public function testGetName()
    {
        $this->assertEquals('text', $this->reporter->getName());
    }

    public function testGetFileExtension()
    {
        $this->assertEquals('txt', $this->reporter->getFileExtension());
    }

    public function testEmptyIssuesReturnsNoIssuesMessage()
    {
        $output = $this->reporter->report(array());

        $this->assertContains('No issues found', $output);
    }

    public function testSingleIssueIsReported()
    {
        $issue = Issue::unusedClass('App\\UnusedClass', '/src/UnusedClass.php', 10);

        $output = $this->reporter->report(array($issue), array('colors' => false));

        $this->assertContains('App\\UnusedClass', $output);
        $this->assertContains('UnusedClass.php', $output);
    }

    public function testMultipleIssuesGroupedByType()
    {
        $issues = array(
            Issue::unusedClass('App\\ClassA', '/src/ClassA.php', 10),
            Issue::unusedClass('App\\ClassB', '/src/ClassB.php', 20),
            Issue::unusedFunction('App\\func', '/src/helpers.php', 5),
        );

        $output = $this->reporter->report($issues, array('colors' => false, 'groupBy' => 'type'));

        $this->assertContains('Unused Classes', $output);
        $this->assertContains('Unused Functions', $output);
        $this->assertContains('App\\ClassA', $output);
        $this->assertContains('App\\func', $output);
    }

    public function testIssuesGroupedByFile()
    {
        $issues = array(
            Issue::unusedClass('App\\ClassA', '/src/File.php', 10),
            Issue::unusedFunction('App\\func', '/src/File.php', 20),
            Issue::unusedClass('App\\ClassB', '/src/Other.php', 5),
        );

        $output = $this->reporter->report($issues, array('colors' => false, 'groupBy' => 'file'));

        $this->assertContains('File.php', $output);
        $this->assertContains('Other.php', $output);
    }

    public function testRelativePathsWithBasePath()
    {
        $issue = Issue::unusedClass('App\\Test', '/var/www/project/src/Test.php', 10);

        $output = $this->reporter->report(
            array($issue),
            array('colors' => false, 'basePath' => '/var/www/project')
        );

        $this->assertContains('src/Test.php', $output);
        $this->assertNotContains('/var/www/project', $output);
    }

    public function testSummaryShowsTotals()
    {
        $issues = array(
            Issue::unusedClass('App\\A', '/src/A.php', 10),
            Issue::unusedFunction('App\\b', '/src/b.php', 5),
        );

        $output = $this->reporter->report($issues, array('colors' => false));

        $this->assertContains('2 issues', $output);
    }

    public function testSummaryShowsErrorAndWarningCounts()
    {
        $issues = array(
            Issue::unusedClass('App\\A', '/src/A.php', 10), // error
            Issue::unusedUseStatement('App\\B', '/src/B.php', 5), // warning
        );

        $output = $this->reporter->report($issues, array('colors' => false));

        $this->assertContains('1 error', $output);
        $this->assertContains('1 warning', $output);
    }

    public function testSeverityIconsAreShown()
    {
        $issues = array(
            Issue::unusedClass('App\\Error', '/src/Error.php', 10),
        );

        $output = $this->reporter->report($issues, array('colors' => false));

        // Should contain error icon (without colors)
        $this->assertContains('✖', $output);
    }

    public function testLineNumbersAreIncluded()
    {
        $issue = Issue::unusedClass('App\\Test', '/src/Test.php', 42);

        $output = $this->reporter->report(array($issue), array('colors' => false));

        $this->assertContains(':42', $output);
    }

    public function testColorsCanBeDisabled()
    {
        $issue = Issue::unusedClass('App\\Test', '/src/Test.php', 10);

        $output = $this->reporter->report(array($issue), array('colors' => false));

        // Should not contain ANSI escape codes
        $this->assertNotContains("\033[", $output);
    }

    public function testTypeLabelMapping()
    {
        $types = array(
            Issue::TYPE_UNUSED_CLASS => 'Unused Classes',
            Issue::TYPE_UNUSED_INTERFACE => 'Unused Interfaces',
            Issue::TYPE_UNUSED_TRAIT => 'Unused Traits',
            Issue::TYPE_UNUSED_FUNCTION => 'Unused Functions',
            Issue::TYPE_UNUSED_USE => 'Unused Use Statements',
        );

        foreach ($types as $type => $expectedLabel) {
            $issue = new Issue($type, 'test', Issue::SEVERITY_WARNING);
            $issue->setSymbolName('Test');
            $issue->setFilePath('/test.php');

            $output = $this->reporter->report(array($issue), array('colors' => false));

            $this->assertContains($expectedLabel, $output, "Type $type should have label $expectedLabel");
        }
    }

    public function testMixedSeveritiesInOutput()
    {
        $issues = array(
            Issue::unusedClass('App\\Error', '/src/Error.php', 10),      // error
            Issue::unusedInterface('App\\Warn', '/src/Warn.php', 20),    // warning
        );

        $output = $this->reporter->report($issues, array('colors' => false));

        // Both should be present
        $this->assertContains('App\\Error', $output);
        $this->assertContains('App\\Warn', $output);
    }
}
