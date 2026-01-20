<?php
/**
 * UseStatementFixer Tests
 */

namespace PhpKnip\Tests\Unit\Fixer;

use PhpKnip\Tests\TestCase;
use PhpKnip\Fixer\UseStatementFixer;
use PhpKnip\Analyzer\Issue;

class UseStatementFixerTest extends TestCase
{
    /**
     * @var UseStatementFixer
     */
    private $fixer;

    protected function setUp(): void
    {
        $this->fixer = new UseStatementFixer();
    }

    public function testGetName()
    {
        $this->assertEquals('use-statement', $this->fixer->getName());
    }

    public function testGetDescription()
    {
        $this->assertNotEmpty($this->fixer->getDescription());
    }

    public function testGetPriority()
    {
        $this->assertEquals(100, $this->fixer->getPriority());
    }

    public function testGetSupportedTypes()
    {
        $types = $this->fixer->getSupportedTypes();

        $this->assertContains(Issue::TYPE_UNUSED_USE, $types);
    }

    public function testCanFixUnusedUseStatement()
    {
        $issue = Issue::unusedUseStatement('App\\Service\\Unused', '/test.php', 5);

        $this->assertTrue($this->fixer->canFix($issue));
    }

    public function testCannotFixOtherIssueTypes()
    {
        $issue = Issue::unusedClass('App\\UnusedClass', '/test.php', 10);

        $this->assertFalse($this->fixer->canFix($issue));
    }

    public function testCannotFixIssueWithoutLine()
    {
        $issue = new Issue(Issue::TYPE_UNUSED_USE, 'Unused use', Issue::SEVERITY_WARNING);
        $issue->setFilePath('/test.php');
        $issue->setSymbolName('App\\Unused');
        // No line set

        $this->assertFalse($this->fixer->canFix($issue));
    }

    public function testFixRemovesSimpleUseStatement()
    {
        $content = <<<'PHP'
<?php
namespace App;

use App\Service\UsedService;
use App\Service\UnusedService;
use App\Util\Helper;

class MyClass {}
PHP;

        $issue = Issue::unusedUseStatement('App\\Service\\UnusedService', '/test.php', 5);

        $result = $this->fixer->fix($issue, $content);

        $this->assertTrue($result->isSuccess());
        $this->assertStringNotContainsString('UnusedService', $result->getNewContent());
        $this->assertStringContainsString('UsedService', $result->getNewContent());
        $this->assertStringContainsString('Helper', $result->getNewContent());
    }

    public function testFixRemovesUseStatementWithAlias()
    {
        $content = <<<'PHP'
<?php
namespace App;

use App\Service\LongServiceName as Service;
use App\Util\Helper;

class MyClass {}
PHP;

        $issue = Issue::unusedUseStatement('Service', '/test.php', 4);

        $result = $this->fixer->fix($issue, $content);

        $this->assertTrue($result->isSuccess());
        $this->assertStringNotContainsString('LongServiceName', $result->getNewContent());
        $this->assertStringContainsString('Helper', $result->getNewContent());
    }

    public function testFixRemovesUseFunctionStatement()
    {
        $content = <<<'PHP'
<?php
namespace App;

use function App\Util\helper_function;
use App\Service\MyService;

class MyClass {}
PHP;

        $issue = Issue::unusedUseStatement('helper_function', '/test.php', 4);

        $result = $this->fixer->fix($issue, $content);

        $this->assertTrue($result->isSuccess());
        $this->assertStringNotContainsString('helper_function', $result->getNewContent());
        $this->assertStringContainsString('MyService', $result->getNewContent());
    }

    public function testFixRemovesUseConstStatement()
    {
        $content = <<<'PHP'
<?php
namespace App;

use const App\Config\DEBUG_MODE;
use App\Service\MyService;

class MyClass {}
PHP;

        $issue = Issue::unusedUseStatement('DEBUG_MODE', '/test.php', 4);

        $result = $this->fixer->fix($issue, $content);

        $this->assertTrue($result->isSuccess());
        $this->assertStringNotContainsString('DEBUG_MODE', $result->getNewContent());
        $this->assertStringContainsString('MyService', $result->getNewContent());
    }

    public function testFixMatchesFullyQualifiedName()
    {
        $content = <<<'PHP'
<?php
use App\Service\MyService;

class Test {}
PHP;

        $issue = Issue::unusedUseStatement('App\\Service\\MyService', '/test.php', 2);

        $result = $this->fixer->fix($issue, $content);

        $this->assertTrue($result->isSuccess());
        $this->assertStringNotContainsString('MyService', $result->getNewContent());
    }

    public function testFixMatchesShortName()
    {
        $content = <<<'PHP'
<?php
use App\Service\MyService;

class Test {}
PHP;

        $issue = Issue::unusedUseStatement('MyService', '/test.php', 2);

        $result = $this->fixer->fix($issue, $content);

        $this->assertTrue($result->isSuccess());
        $this->assertStringNotContainsString('MyService', $result->getNewContent());
    }

    public function testFixFailsOnNonUseStatementLine()
    {
        $content = <<<'PHP'
<?php
namespace App;

class MyClass {}
PHP;

        $issue = Issue::unusedUseStatement('App\\Service\\Unused', '/test.php', 4);

        $result = $this->fixer->fix($issue, $content);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContains('not a use statement', $result->getDescription());
    }

    public function testFixFailsOnInvalidLine()
    {
        $content = <<<'PHP'
<?php
use App\Service;
PHP;

        $issue = Issue::unusedUseStatement('App\\Service', '/test.php', 999);

        $result = $this->fixer->fix($issue, $content);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContains('does not exist', $result->getDescription());
    }

    public function testFixSkipsNonMatchingSymbol()
    {
        $content = <<<'PHP'
<?php
use App\Service\DifferentService;

class Test {}
PHP;

        $issue = Issue::unusedUseStatement('App\\Service\\MyService', '/test.php', 2);

        $result = $this->fixer->fix($issue, $content);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContains('Skipped', $result->getDescription());
    }

    public function testFixPreservesOtherContent()
    {
        $content = <<<'PHP'
<?php
/**
 * File description
 */
namespace App;

use App\Service\UsedService;
use App\Service\UnusedService;

/**
 * Class description
 */
class MyClass
{
    private $service;

    public function __construct(UsedService $service)
    {
        $this->service = $service;
    }
}
PHP;

        $issue = Issue::unusedUseStatement('UnusedService', '/test.php', 8);

        $result = $this->fixer->fix($issue, $content);

        $this->assertTrue($result->isSuccess());
        $newContent = $result->getNewContent();

        $this->assertStringContainsString('File description', $newContent);
        $this->assertStringContainsString('Class description', $newContent);
        $this->assertStringContainsString('private $service', $newContent);
        $this->assertStringContainsString('UsedService', $newContent);
    }

    public function testFixSetsRemovedLines()
    {
        $content = <<<'PHP'
<?php
use App\Service;
PHP;

        $issue = Issue::unusedUseStatement('App\\Service', '/test.php', 2);

        $result = $this->fixer->fix($issue, $content);

        $this->assertTrue($result->isSuccess());
        $this->assertContains(2, $result->getRemovedLines());
    }

    public function testFixCleansUpConsecutiveEmptyLines()
    {
        $content = "<?php\n\nuse App\\Unused;\n\nclass Test {}";

        $issue = Issue::unusedUseStatement('App\\Unused', '/test.php', 3);

        $result = $this->fixer->fix($issue, $content);

        $this->assertTrue($result->isSuccess());
        $newContent = $result->getNewContent();

        // Should not have more than 2 consecutive newlines
        $this->assertStringNotContainsString("\n\n\n", $newContent);
    }
}
