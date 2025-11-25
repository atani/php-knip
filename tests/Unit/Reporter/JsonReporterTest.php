<?php
/**
 * JsonReporter Tests
 */

namespace PhpKnip\Tests\Unit\Reporter;

use PHPUnit\Framework\TestCase;
use PhpKnip\Reporter\JsonReporter;
use PhpKnip\Analyzer\Issue;

class JsonReporterTest extends TestCase
{
    /**
     * @var JsonReporter
     */
    private $reporter;

    protected function setUp()
    {
        $this->reporter = new JsonReporter();
    }

    public function testGetName()
    {
        $this->assertEquals('json', $this->reporter->getName());
    }

    public function testGetFileExtension()
    {
        $this->assertEquals('json', $this->reporter->getFileExtension());
    }

    public function testOutputIsValidJson()
    {
        $issues = array(
            Issue::unusedClass('App\\Test', '/src/Test.php', 10),
        );

        $output = $this->reporter->report($issues);
        $decoded = json_decode($output, true);

        $this->assertNotNull($decoded, 'Output should be valid JSON');
        $this->assertInternalType('array', $decoded);
    }

    public function testEmptyIssuesProducesValidJson()
    {
        $output = $this->reporter->report(array());
        $decoded = json_decode($output, true);

        $this->assertNotNull($decoded);
        $this->assertEquals(0, $decoded['summary']['total']);
        $this->assertEmpty($decoded['issues']);
    }

    public function testSummaryContainsTotalCount()
    {
        $issues = array(
            Issue::unusedClass('App\\A', '/src/A.php', 10),
            Issue::unusedClass('App\\B', '/src/B.php', 20),
        );

        $output = $this->reporter->report($issues);
        $decoded = json_decode($output, true);

        $this->assertEquals(2, $decoded['summary']['total']);
    }

    public function testSummaryContainsByTypeBreakdown()
    {
        $issues = array(
            Issue::unusedClass('App\\A', '/src/A.php', 10),
            Issue::unusedClass('App\\B', '/src/B.php', 20),
            Issue::unusedFunction('App\\func', '/src/func.php', 5),
        );

        $output = $this->reporter->report($issues);
        $decoded = json_decode($output, true);

        $this->assertEquals(2, $decoded['summary']['byType'][Issue::TYPE_UNUSED_CLASS]);
        $this->assertEquals(1, $decoded['summary']['byType'][Issue::TYPE_UNUSED_FUNCTION]);
    }

    public function testSummaryContainsBySeverityBreakdown()
    {
        $issues = array(
            Issue::unusedClass('App\\A', '/src/A.php', 10),        // error
            Issue::unusedUseStatement('App\\B', '/src/B.php', 5),  // warning
        );

        $output = $this->reporter->report($issues);
        $decoded = json_decode($output, true);

        $this->assertEquals(1, $decoded['summary']['bySeverity'][Issue::SEVERITY_ERROR]);
        $this->assertEquals(1, $decoded['summary']['bySeverity'][Issue::SEVERITY_WARNING]);
    }

    public function testIssueContainsAllFields()
    {
        $issue = Issue::unusedClass('App\\TestClass', '/src/TestClass.php', 42);

        $output = $this->reporter->report(array($issue));
        $decoded = json_decode($output, true);

        $issueData = $decoded['issues'][0];

        $this->assertEquals(Issue::TYPE_UNUSED_CLASS, $issueData['type']);
        $this->assertEquals(Issue::SEVERITY_ERROR, $issueData['severity']);
        $this->assertContains('never used', $issueData['message']);
        $this->assertEquals('App\\TestClass', $issueData['symbol']);
        $this->assertEquals('class', $issueData['symbolType']);
        $this->assertEquals('/src/TestClass.php', $issueData['file']);
        $this->assertEquals(42, $issueData['line']);
    }

    public function testRelativePathsWithBasePath()
    {
        $issue = Issue::unusedClass('App\\Test', '/var/www/project/src/Test.php', 10);

        $output = $this->reporter->report(
            array($issue),
            array('basePath' => '/var/www/project')
        );
        $decoded = json_decode($output, true);

        $this->assertEquals('src/Test.php', $decoded['issues'][0]['file']);
    }

    public function testGroupByTypeOption()
    {
        $issues = array(
            Issue::unusedClass('App\\A', '/src/A.php', 10),
            Issue::unusedFunction('App\\func', '/src/func.php', 5),
        );

        $output = $this->reporter->report($issues, array('groupBy' => 'type'));
        $decoded = json_decode($output, true);

        $this->assertArrayHasKey(Issue::TYPE_UNUSED_CLASS, $decoded['issues']);
        $this->assertArrayHasKey(Issue::TYPE_UNUSED_FUNCTION, $decoded['issues']);
    }

    public function testGroupByFileOption()
    {
        $issues = array(
            Issue::unusedClass('App\\A', '/src/File.php', 10),
            Issue::unusedFunction('App\\func', '/src/File.php', 20),
            Issue::unusedClass('App\\B', '/src/Other.php', 5),
        );

        $output = $this->reporter->report($issues, array('groupBy' => 'file'));
        $decoded = json_decode($output, true);

        $this->assertArrayHasKey('/src/File.php', $decoded['issues']);
        $this->assertArrayHasKey('/src/Other.php', $decoded['issues']);
        $this->assertCount(2, $decoded['issues']['/src/File.php']);
    }

    public function testGroupByFileRemovesRedundantFilePath()
    {
        $issue = Issue::unusedClass('App\\A', '/src/File.php', 10);

        $output = $this->reporter->report(array($issue), array('groupBy' => 'file'));
        $decoded = json_decode($output, true);

        // File should not be in individual issue when grouped by file
        $this->assertArrayNotHasKey('file', $decoded['issues']['/src/File.php'][0]);
    }

    public function testPrettyPrintOption()
    {
        $issue = Issue::unusedClass('App\\Test', '/src/Test.php', 10);

        $compactOutput = $this->reporter->report(array($issue), array('pretty' => false));
        $prettyOutput = $this->reporter->report(array($issue), array('pretty' => true));

        // Pretty output should be longer due to whitespace
        $this->assertGreaterThan(strlen($compactOutput), strlen($prettyOutput));
        // Pretty output should contain newlines
        $this->assertContains("\n", $prettyOutput);
    }

    public function testMetadataIsIncluded()
    {
        $issue = Issue::unusedMethod('testMethod', 'App\\TestClass', '/src/TestClass.php', 50);

        $output = $this->reporter->report(array($issue));
        $decoded = json_decode($output, true);

        $issueData = $decoded['issues'][0];

        $this->assertArrayHasKey('metadata', $issueData);
        $this->assertEquals('App\\TestClass', $issueData['metadata']['className']);
        $this->assertEquals('testMethod', $issueData['metadata']['methodName']);
    }

    public function testNullFileIsHandled()
    {
        $issue = new Issue(Issue::TYPE_UNUSED_DEPENDENCY, 'Test dependency', Issue::SEVERITY_WARNING);
        // No file path set

        $output = $this->reporter->report(array($issue));
        $decoded = json_decode($output, true);

        $this->assertNull($decoded['issues'][0]['file']);
    }

    public function testUnicodeCharactersArePreserved()
    {
        $issue = Issue::unusedClass('App\\日本語クラス', '/src/日本語.php', 10);

        $output = $this->reporter->report(array($issue));
        $decoded = json_decode($output, true);

        $this->assertEquals('App\\日本語クラス', $decoded['issues'][0]['symbol']);
    }

    public function testMultipleIssuesInFlatArray()
    {
        $issues = array(
            Issue::unusedClass('App\\A', '/src/A.php', 10),
            Issue::unusedClass('App\\B', '/src/B.php', 20),
            Issue::unusedClass('App\\C', '/src/C.php', 30),
        );

        $output = $this->reporter->report($issues);
        $decoded = json_decode($output, true);

        $this->assertCount(3, $decoded['issues']);
        $this->assertInternalType('array', $decoded['issues']);
        $this->assertArrayHasKey(0, $decoded['issues']);
        $this->assertArrayHasKey(1, $decoded['issues']);
        $this->assertArrayHasKey(2, $decoded['issues']);
    }
}
