<?php
/**
 * HtmlReporter Tests
 */

namespace PhpKnip\Tests\Unit\Reporter;

use PhpKnip\Tests\TestCase;
use PhpKnip\Reporter\HtmlReporter;
use PhpKnip\Analyzer\Issue;

class HtmlReporterTest extends TestCase
{
    /**
     * @var HtmlReporter
     */
    private $reporter;

    protected function setUp(): void
    {
        $this->reporter = new HtmlReporter();
    }

    public function testGetName()
    {
        $this->assertEquals('html', $this->reporter->getName());
    }

    public function testGetFileExtension()
    {
        $this->assertEquals('html', $this->reporter->getFileExtension());
    }

    public function testEmptyIssuesShowsNoIssuesMessage()
    {
        $output = $this->reporter->report(array());

        $this->assertStringContains('No issues found', $output);
        $this->assertStringContains('<!DOCTYPE html>', $output);
    }

    public function testOutputIsValidHtmlStructure()
    {
        $output = $this->reporter->report(array());

        $this->assertStringContains('<!DOCTYPE html>', $output);
        $this->assertStringContains('<html', $output);
        $this->assertStringContains('<head>', $output);
        $this->assertStringContains('<body>', $output);
        $this->assertStringContains('</html>', $output);
    }

    public function testDefaultTitle()
    {
        $output = $this->reporter->report(array());

        $this->assertStringContains('<title>PHP-Knip Analysis Report</title>', $output);
        $this->assertStringContains('<h1>PHP-Knip Analysis Report</h1>', $output);
    }

    public function testCustomTitle()
    {
        $output = $this->reporter->report(array(), array('title' => 'My Custom Report'));

        $this->assertStringContains('<title>My Custom Report</title>', $output);
        $this->assertStringContains('<h1>My Custom Report</h1>', $output);
    }

    public function testTimestampIsIncluded()
    {
        $output = $this->reporter->report(array());

        $this->assertStringContains('Generated:', $output);
        $this->assertMatchesRegex('/\d{4}-\d{2}-\d{2}/', $output);
    }

    public function testSingleIssueIsRendered()
    {
        $issue = Issue::unusedClass('App\\UnusedClass', '/src/UnusedClass.php', 10);

        $output = $this->reporter->report(array($issue));

        $this->assertStringContains('App\\UnusedClass', $output);
        $this->assertStringContains('/src/UnusedClass.php', $output);
        $this->assertStringContains('10', $output);
    }

    public function testSummaryShowsTotalCount()
    {
        $issues = array(
            Issue::unusedClass('App\\A', '/src/A.php', 10),
            Issue::unusedFunction('App\\b', '/src/b.php', 5),
        );

        $output = $this->reporter->report($issues);

        $this->assertStringContains('>2</div>', $output); // Total count in summary card
        $this->assertStringContains('Total Issues', $output);
    }

    public function testSummaryShowsErrorCount()
    {
        $issues = array(
            Issue::unusedClass('App\\A', '/src/A.php', 10),  // error
            Issue::unusedClass('App\\B', '/src/B.php', 20),  // error
        );

        $output = $this->reporter->report($issues);

        $this->assertStringContains('summary-card error', $output);
        $this->assertStringContains('Errors', $output);
    }

    public function testSummaryShowsWarningCount()
    {
        $issues = array(
            Issue::unusedUseStatement('App\\A', '/src/A.php', 10),  // warning
        );

        $output = $this->reporter->report($issues);

        $this->assertStringContains('summary-card warning', $output);
        $this->assertStringContains('Warnings', $output);
    }

    public function testIssuesGroupedByType()
    {
        $issues = array(
            Issue::unusedClass('App\\ClassA', '/src/ClassA.php', 10),
            Issue::unusedClass('App\\ClassB', '/src/ClassB.php', 20),
            Issue::unusedFunction('App\\func', '/src/helpers.php', 5),
        );

        $output = $this->reporter->report($issues);

        $this->assertStringContains('Unused Classes (2)', $output);
        $this->assertStringContains('Unused Functions (1)', $output);
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

    public function testHtmlEntitiesAreEscaped()
    {
        $issue = new Issue(Issue::TYPE_UNUSED_CLASS, 'Test <script>alert(1)</script>', Issue::SEVERITY_ERROR);
        $issue->setSymbolName('Test<>&"');
        $issue->setFilePath('/test.php');
        $issue->setLine(1);

        $output = $this->reporter->report(array($issue));

        $this->assertStringNotContains('<script>', $output);
        $this->assertStringContains('&lt;script&gt;', $output);
        $this->assertStringContains('&amp;', $output);
    }

    public function testSeverityClassesAreApplied()
    {
        $issues = array(
            Issue::unusedClass('App\\Error', '/src/Error.php', 10),  // error
            Issue::unusedUseStatement('App\\Warn', '/src/Warn.php', 5),  // warning
        );

        $output = $this->reporter->report($issues);

        $this->assertStringContains('severity-error', $output);
        $this->assertStringContains('severity-warning', $output);
    }

    public function testTableStructureIsPresent()
    {
        $issue = Issue::unusedClass('App\\Test', '/src/Test.php', 10);

        $output = $this->reporter->report(array($issue));

        $this->assertStringContains('<table>', $output);
        $this->assertStringContains('<thead>', $output);
        $this->assertStringContains('<tbody>', $output);
        $this->assertStringContains('<th>Severity</th>', $output);
        $this->assertStringContains('<th>File</th>', $output);
        $this->assertStringContains('<th>Line</th>', $output);
        $this->assertStringContains('<th>Symbol</th>', $output);
        $this->assertStringContains('<th>Message</th>', $output);
    }

    public function testStylesAreIncluded()
    {
        $output = $this->reporter->report(array());

        $this->assertStringContains('<style>', $output);
        $this->assertStringContains('</style>', $output);
        $this->assertStringContains('.container', $output);
    }

    public function testMetaTagsArePresent()
    {
        $output = $this->reporter->report(array());

        $this->assertStringContains('<meta charset="UTF-8">', $output);
        $this->assertStringContains('viewport', $output);
    }

    public function testNullValuesShowDash()
    {
        $issue = Issue::unusedFile('/src/orphan.php');
        // unusedFile has no line number

        $output = $this->reporter->report(array($issue));

        // Line number should show "-" when null
        $this->assertStringContains('>-</td>', $output);
    }

    public function testTypeTitleFormatting()
    {
        $issue = Issue::unusedClass('App\\Test', '/src/Test.php', 10);

        $output = $this->reporter->report(array($issue));

        // "unused-classes" should become "Unused Classes"
        $this->assertStringContains('Unused Classes', $output);
    }

    public function testMultipleTypeGroups()
    {
        $issues = array(
            Issue::unusedClass('App\\A', '/src/A.php', 10),
            Issue::unusedInterface('App\\B', '/src/B.php', 20),
            Issue::unusedTrait('App\\C', '/src/C.php', 30),
        );

        $output = $this->reporter->report($issues);

        // Should have h2 headers for each type
        $this->assertGreaterThanOrEqual(3, substr_count($output, '<h2>'));
    }
}
