<?php
/**
 * GithubReporter Tests
 */

namespace PhpKnip\Tests\Unit\Reporter;

use PhpKnip\Tests\TestCase;
use PhpKnip\Reporter\GithubReporter;
use PhpKnip\Analyzer\Issue;

class GithubReporterTest extends TestCase
{
    /**
     * @var GithubReporter
     */
    private $reporter;

    protected function setUp(): void
    {
        $this->reporter = new GithubReporter();
    }

    public function testGetName()
    {
        $this->assertEquals('github', $this->reporter->getName());
    }

    public function testGetFileExtension()
    {
        $this->assertNull($this->reporter->getFileExtension());
    }

    public function testEmptyIssuesReturnsNewline()
    {
        $output = $this->reporter->report(array());

        $this->assertEquals("\n", $output);
    }

    public function testSingleIssueOutputsAnnotation()
    {
        $issue = Issue::unusedClass('App\\UnusedClass', '/src/UnusedClass.php', 10);

        $output = $this->reporter->report(array($issue));

        $this->assertStringContains('::error', $output);
        $this->assertStringContains('file=/src/UnusedClass.php', $output);
        $this->assertStringContains('line=10', $output);
        $this->assertStringContains('App\\UnusedClass', $output);
    }

    public function testErrorSeverityMapsToError()
    {
        $issue = Issue::unusedClass('App\\Test', '/src/Test.php', 5);

        $output = $this->reporter->report(array($issue));

        $this->assertStringContains('::error', $output);
    }

    public function testWarningSeverityMapsToWarning()
    {
        $issue = Issue::unusedUseStatement('App\\Unused', '/src/File.php', 3);

        $output = $this->reporter->report(array($issue));

        $this->assertStringContains('::warning', $output);
    }

    public function testInfoSeverityMapsToNotice()
    {
        $issue = new Issue(Issue::TYPE_UNUSED_VARIABLE, 'test', Issue::SEVERITY_INFO);
        $issue->setSymbolName('$unused');
        $issue->setFilePath('/src/test.php');
        $issue->setLine(15);

        $output = $this->reporter->report(array($issue));

        $this->assertStringContains('::notice', $output);
    }

    public function testAnnotationContainsTitle()
    {
        $issue = Issue::unusedClass('App\\Test', '/src/Test.php', 10);

        $output = $this->reporter->report(array($issue));

        $this->assertStringContains('title=', $output);
        $this->assertStringContains('Unused Classes', $output);
    }

    public function testRelativePathsWithBasePath()
    {
        $issue = Issue::unusedClass('App\\Test', '/var/www/project/src/Test.php', 10);

        $output = $this->reporter->report(
            array($issue),
            array('basePath' => '/var/www/project')
        );

        $this->assertStringContains('file=src/Test.php', $output);
        $this->assertStringNotContains('/var/www/project', $output);
    }

    public function testSummaryIsIncluded()
    {
        $issues = array(
            Issue::unusedClass('App\\A', '/src/A.php', 10),
            Issue::unusedFunction('App\\b', '/src/b.php', 5),
        );

        $output = $this->reporter->report($issues);

        $this->assertStringContains('::notice::', $output);
        $this->assertStringContains('PHP-Knip found 2 issues', $output);
    }

    public function testSummaryShowsErrorAndWarningCounts()
    {
        $issues = array(
            Issue::unusedClass('App\\Error', '/src/Error.php', 10),  // error
            Issue::unusedUseStatement('App\\Warn', '/src/Warn.php', 5),  // warning
        );

        $output = $this->reporter->report($issues);

        $this->assertStringContains('1 error', $output);
        $this->assertStringContains('1 warning', $output);
    }

    public function testMultipleIssuesGenerateMultipleAnnotations()
    {
        $issues = array(
            Issue::unusedClass('App\\A', '/src/A.php', 10),
            Issue::unusedClass('App\\B', '/src/B.php', 20),
            Issue::unusedFunction('App\\func', '/src/helpers.php', 5),
        );

        $output = $this->reporter->report($issues);

        $lines = explode("\n", trim($output));
        // 3 issue annotations + blank line + summary
        $this->assertGreaterThanOrEqual(4, count($lines));
    }

    public function testNewlinesInMessageAreEscaped()
    {
        $issue = new Issue(Issue::TYPE_UNUSED_CLASS, "Line1\nLine2", Issue::SEVERITY_ERROR);
        $issue->setSymbolName('Test');
        $issue->setFilePath('/test.php');

        $output = $this->reporter->report(array($issue));

        // Newlines in the message should be escaped as %0A
        $this->assertStringContains('%0A', $output);
        // The first line (the annotation) should not contain a literal newline within the message
        $lines = explode("\n", $output);
        $this->assertStringContains('Line1%0ALine2', $lines[0]);
    }

    public function testPercentSignsInMessageAreEscaped()
    {
        $issue = new Issue(Issue::TYPE_UNUSED_CLASS, 'Test 100% complete', Issue::SEVERITY_ERROR);
        $issue->setSymbolName('Test');
        $issue->setFilePath('/test.php');

        $output = $this->reporter->report(array($issue));

        $this->assertStringContains('%25', $output);
    }

    public function testAnnotationFormatIsCorrect()
    {
        $issue = Issue::unusedClass('App\\Test', '/src/Test.php', 42);

        $output = $this->reporter->report(array($issue));

        // Should follow format: ::{level} file={file},line={line},title={title}::{message}
        $this->assertMatchesRegex('/^::error file=.*,line=42,title=.*::.*$/m', $output);
    }

    public function testIssueWithoutFilePathStillWorks()
    {
        $issue = new Issue(Issue::TYPE_UNUSED_DEPENDENCY, 'Unused dependency', Issue::SEVERITY_WARNING);
        $issue->setSymbolName('some/package');

        $output = $this->reporter->report(array($issue));

        $this->assertStringContains('::warning', $output);
        $this->assertStringNotContains('file=,', $output);
    }

    public function testIssueWithoutLineNumberStillWorks()
    {
        $issue = Issue::unusedFile('/src/orphan.php');

        $output = $this->reporter->report(array($issue));

        $this->assertStringContains('::warning', $output);
        $this->assertStringNotContains('line=,', $output);
    }
}
