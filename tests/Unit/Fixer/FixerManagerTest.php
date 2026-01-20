<?php
/**
 * FixerManager Tests
 */

namespace PhpKnip\Tests\Unit\Fixer;

use PhpKnip\Tests\TestCase;
use PhpKnip\Fixer\FixerManager;
use PhpKnip\Fixer\UseStatementFixer;
use PhpKnip\Fixer\FixerInterface;
use PhpKnip\Fixer\FixResult;
use PhpKnip\Analyzer\Issue;

class FixerManagerTest extends TestCase
{
    /**
     * @var FixerManager
     */
    private $manager;

    /**
     * @var string
     */
    private $tempDir;

    protected function setUp(): void
    {
        $this->manager = new FixerManager();
        $this->tempDir = sys_get_temp_dir() . '/php-knip-fixer-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testRegisterFixer()
    {
        $fixer = new UseStatementFixer();

        $this->manager->registerFixer($fixer);

        $this->assertContains($fixer, $this->manager->getFixers());
    }

    public function testRegisterBuiltinFixers()
    {
        $this->manager->registerBuiltinFixers();

        $fixers = $this->manager->getFixers();

        $this->assertNotEmpty($fixers);
        $this->assertNotNull($this->manager->getFixer('use-statement'));
    }

    public function testGetFixerByName()
    {
        $fixer = new UseStatementFixer();
        $this->manager->registerFixer($fixer);

        $retrieved = $this->manager->getFixer('use-statement');

        $this->assertSame($fixer, $retrieved);
    }

    public function testGetFixerReturnsNullForUnknown()
    {
        $this->assertNull($this->manager->getFixer('unknown'));
    }

    public function testIsDryRunDefaultsToTrue()
    {
        $this->assertTrue($this->manager->isDryRun());
    }

    public function testSetDryRun()
    {
        $this->manager->setDryRun(false);

        $this->assertFalse($this->manager->isDryRun());
    }

    public function testFixIssueWithNoFixer()
    {
        $issue = Issue::unusedClass('App\\Unused', '/test.php', 10);

        $result = $this->manager->fixIssue($issue);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContains('No fixer available', $result->getDescription());
    }

    public function testFixIssueWithNoFilePath()
    {
        $this->manager->registerBuiltinFixers();

        $issue = new Issue(Issue::TYPE_UNUSED_USE, 'Unused', Issue::SEVERITY_WARNING);
        $issue->setSymbolName('App\\Unused');
        $issue->setLine(5);
        // No file path

        $result = $this->manager->fixIssue($issue);

        $this->assertFalse($result->isSuccess());
    }

    public function testFixIssueWithNonexistentFile()
    {
        $this->manager->registerBuiltinFixers();

        $issue = Issue::unusedUseStatement('App\\Unused', '/nonexistent/file.php', 5);

        $result = $this->manager->fixIssue($issue);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContains('Could not read', $result->getDescription());
    }

    public function testFixIssueDryRun()
    {
        $this->manager->registerBuiltinFixers();

        // Create test file
        $content = "<?php\nuse App\\Unused;\nclass Test {}";
        $filePath = $this->tempDir . '/test.php';
        file_put_contents($filePath, $content);

        $issue = Issue::unusedUseStatement('App\\Unused', $filePath, 2);

        $this->manager->setDryRun(true);
        $result = $this->manager->fixIssue($issue);

        $this->assertTrue($result->isSuccess());
        // File should not be modified
        $this->assertEquals($content, file_get_contents($filePath));
    }

    public function testFixIssuesProcessesMultipleIssues()
    {
        $this->manager->registerBuiltinFixers();

        // Create test file with multiple unused use statements
        $content = <<<'PHP'
<?php
use App\Service1;
use App\Service2;
use App\Service3;

class Test {}
PHP;
        $filePath = $this->tempDir . '/test.php';
        file_put_contents($filePath, $content);

        $issues = array(
            Issue::unusedUseStatement('App\\Service1', $filePath, 2),
            Issue::unusedUseStatement('App\\Service2', $filePath, 3),
            Issue::unusedUseStatement('App\\Service3', $filePath, 4),
        );

        $results = $this->manager->fixIssues($issues);

        $this->assertCount(3, $results);

        $successful = $this->manager->getSuccessfulResults();
        $this->assertCount(3, $successful);
    }

    public function testApplyFixesWritesToFile()
    {
        $this->manager->registerBuiltinFixers();

        // Create test file
        $content = "<?php\nuse App\\Unused;\nclass Test {}";
        $filePath = $this->tempDir . '/test.php';
        file_put_contents($filePath, $content);

        $issue = Issue::unusedUseStatement('App\\Unused', $filePath, 2);

        $this->manager->setDryRun(false);
        $this->manager->fixIssue($issue);
        $written = $this->manager->applyFixes();

        $this->assertArrayHasKey($filePath, $written);
        $this->assertTrue($written[$filePath]);

        // Verify file was modified
        $newContent = file_get_contents($filePath);
        $this->assertStringNotContainsString('App\\Unused', $newContent);
    }

    public function testApplyFixesDoesNothingInDryRun()
    {
        $this->manager->registerBuiltinFixers();

        // Create test file
        $content = "<?php\nuse App\\Unused;\nclass Test {}";
        $filePath = $this->tempDir . '/test.php';
        file_put_contents($filePath, $content);

        $issue = Issue::unusedUseStatement('App\\Unused', $filePath, 2);

        $this->manager->setDryRun(true);
        $this->manager->fixIssue($issue);
        $written = $this->manager->applyFixes();

        $this->assertEmpty($written);

        // File should be unchanged
        $this->assertEquals($content, file_get_contents($filePath));
    }

    public function testGetModifiedFilePaths()
    {
        $this->manager->registerBuiltinFixers();

        // Create test file
        $content = "<?php\nuse App\\Unused;\nclass Test {}";
        $filePath = $this->tempDir . '/test.php';
        file_put_contents($filePath, $content);

        $issue = Issue::unusedUseStatement('App\\Unused', $filePath, 2);

        $this->manager->fixIssue($issue);

        $paths = $this->manager->getModifiedFilePaths();

        $this->assertContains($filePath, $paths);
    }

    public function testGetModifiedContent()
    {
        $this->manager->registerBuiltinFixers();

        // Create test file
        $content = "<?php\nuse App\\Unused;\nclass Test {}";
        $filePath = $this->tempDir . '/test.php';
        file_put_contents($filePath, $content);

        $issue = Issue::unusedUseStatement('App\\Unused', $filePath, 2);

        $this->manager->fixIssue($issue);

        $modified = $this->manager->getModifiedContent($filePath);

        $this->assertNotNull($modified);
        $this->assertStringNotContainsString('App\\Unused', $modified);
    }

    public function testClearResetsState()
    {
        $this->manager->registerBuiltinFixers();

        // Create test file
        $content = "<?php\nuse App\\Unused;\nclass Test {}";
        $filePath = $this->tempDir . '/test.php';
        file_put_contents($filePath, $content);

        $issue = Issue::unusedUseStatement('App\\Unused', $filePath, 2);

        $this->manager->fixIssue($issue);
        $this->assertNotEmpty($this->manager->getResults());

        $this->manager->clear();

        $this->assertEmpty($this->manager->getResults());
        $this->assertEmpty($this->manager->getModifiedFilePaths());
    }

    public function testGetSummary()
    {
        $this->manager->registerBuiltinFixers();

        // Create test file
        $content = "<?php\nuse App\\Unused;\nclass Test {}";
        $filePath = $this->tempDir . '/test.php';
        file_put_contents($filePath, $content);

        $issue = Issue::unusedUseStatement('App\\Unused', $filePath, 2);

        $this->manager->fixIssue($issue);

        $summary = $this->manager->getSummary();

        $this->assertEquals(1, $summary['total']);
        $this->assertEquals(1, $summary['successful']);
        $this->assertEquals(0, $summary['failed']);
        $this->assertEquals(0, $summary['skipped']);
        $this->assertEquals(1, $summary['files_modified']);
    }

    public function testFixIssuesInDescendingLineOrder()
    {
        $this->manager->registerBuiltinFixers();

        // Create test file with multiple use statements
        $content = <<<'PHP'
<?php
use App\A;
use App\B;
use App\C;

class Test {}
PHP;
        $filePath = $this->tempDir . '/test.php';
        file_put_contents($filePath, $content);

        // Issues in ascending order
        $issues = array(
            Issue::unusedUseStatement('App\\A', $filePath, 2),
            Issue::unusedUseStatement('App\\C', $filePath, 4),
        );

        $this->manager->setDryRun(false);
        $this->manager->fixIssues($issues);
        $this->manager->applyFixes();

        $newContent = file_get_contents($filePath);

        // Both should be removed
        $this->assertStringNotContainsString('App\\A', $newContent);
        $this->assertStringNotContainsString('App\\C', $newContent);
        // B should remain
        $this->assertStringContainsString('App\\B', $newContent);
    }

    /**
     * Remove directory recursively
     *
     * @param string $dir Directory path
     */
    private function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
