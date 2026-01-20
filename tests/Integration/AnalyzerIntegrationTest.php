<?php
/**
 * Analyzer Integration Tests
 *
 * Tests full analysis flow from parsing to issue detection
 */

namespace PhpKnip\Tests\Integration;

use PhpKnip\Tests\TestCase;
use PhpKnip\Parser\AstBuilder;
use PhpKnip\Resolver\SymbolCollector;
use PhpKnip\Resolver\ReferenceCollector;
use PhpKnip\Resolver\SymbolTable;
use PhpKnip\Analyzer\AnalysisContext;
use PhpKnip\Analyzer\ClassAnalyzer;
use PhpKnip\Analyzer\FunctionAnalyzer;
use PhpKnip\Analyzer\UseStatementAnalyzer;
use PhpKnip\Analyzer\Issue;
use PhpParser\NodeTraverser;

class AnalyzerIntegrationTest extends TestCase
{
    /**
     * @var AstBuilder
     */
    private $astBuilder;

    protected function setUp(): void
    {
        $this->astBuilder = new AstBuilder('auto');
    }

    /**
     * Test detection of unused class in real PHP code
     */
    public function testUnusedClassDetection()
    {
        $code = <<<'PHP'
<?php
namespace App;

class UsedClass {
    public function doSomething() {}
}

class UnusedClass {
    public function neverCalled() {}
}

$obj = new UsedClass();
$obj->doSomething();
PHP;

        $issues = $this->analyzeCode($code, '/test/sample.php');

        $unusedClasses = $this->filterByType($issues, Issue::TYPE_UNUSED_CLASS);
        $this->assertCount(1, $unusedClasses);
        $this->assertEquals('App\\UnusedClass', $unusedClasses[0]->getSymbolName());
    }

    /**
     * Test detection of unused function in real PHP code
     */
    public function testUnusedFunctionDetection()
    {
        $code = <<<'PHP'
<?php
namespace App;

function usedFunction() {
    return 'used';
}

function unusedFunction() {
    return 'unused';
}

echo usedFunction();
PHP;

        $issues = $this->analyzeCode($code, '/test/sample.php');

        $unusedFunctions = $this->filterByType($issues, Issue::TYPE_UNUSED_FUNCTION);
        $this->assertCount(1, $unusedFunctions);
        $this->assertEquals('App\\unusedFunction', $unusedFunctions[0]->getSymbolName());
    }

    /**
     * Test detection of unused use statements
     */
    public function testUnusedUseStatementDetection()
    {
        $code = <<<'PHP'
<?php
namespace App;

use DateTime;
use DateTimeZone;
use Exception;

$date = new DateTime();
PHP;

        $issues = $this->analyzeCode($code, '/test/sample.php');

        $unusedUses = $this->filterByType($issues, Issue::TYPE_UNUSED_USE);
        $this->assertCount(2, $unusedUses);

        $symbols = array_map(function ($issue) {
            return $issue->getSymbolName();
        }, $unusedUses);

        $this->assertContains('DateTimeZone', $symbols);
        $this->assertContains('Exception', $symbols);
    }

    /**
     * Test that extended class is not flagged as unused
     */
    public function testExtendedClassNotFlagged()
    {
        $code = <<<'PHP'
<?php
namespace App;

class BaseClass {
    protected function helper() {}
}

class ChildClass extends BaseClass {
    public function doWork() {
        $this->helper();
    }
}

$child = new ChildClass();
PHP;

        $issues = $this->analyzeCode($code, '/test/sample.php');

        $unusedClasses = $this->filterByType($issues, Issue::TYPE_UNUSED_CLASS);
        $this->assertCount(0, $unusedClasses);
    }

    /**
     * Test that implemented interface is not flagged as unused
     */
    public function testImplementedInterfaceNotFlagged()
    {
        $code = <<<'PHP'
<?php
namespace App;

interface ServiceInterface {
    public function execute();
}

class Service implements ServiceInterface {
    public function execute() {
        return 'done';
    }
}

$service = new Service();
PHP;

        $issues = $this->analyzeCode($code, '/test/sample.php');

        $unusedInterfaces = $this->filterByType($issues, Issue::TYPE_UNUSED_INTERFACE);
        $this->assertCount(0, $unusedInterfaces);
    }

    /**
     * Test that used trait is not flagged as unused
     */
    public function testUsedTraitNotFlagged()
    {
        $code = <<<'PHP'
<?php
namespace App;

trait LoggableTrait {
    public function log($message) {
        echo $message;
    }
}

class Service {
    use LoggableTrait;

    public function run() {
        $this->log('running');
    }
}

$service = new Service();
PHP;

        $issues = $this->analyzeCode($code, '/test/sample.php');

        $unusedTraits = $this->filterByType($issues, Issue::TYPE_UNUSED_TRAIT);
        $this->assertCount(0, $unusedTraits);
    }

    /**
     * Test static method call marks class as used
     */
    public function testStaticCallMarksClassAsUsed()
    {
        $code = <<<'PHP'
<?php
namespace App;

class Utility {
    public static function format($value) {
        return trim($value);
    }
}

$result = Utility::format('  hello  ');
PHP;

        $issues = $this->analyzeCode($code, '/test/sample.php');

        $unusedClasses = $this->filterByType($issues, Issue::TYPE_UNUSED_CLASS);
        $this->assertCount(0, $unusedClasses);
    }

    /**
     * Test type hint marks class as used
     */
    public function testTypeHintMarksClassAsUsed()
    {
        $code = <<<'PHP'
<?php
namespace App;

class Request {
    public $data = [];
}

class Controller {
    public function handle(Request $request) {
        return $request->data;
    }
}

$controller = new Controller();
PHP;

        $issues = $this->analyzeCode($code, '/test/sample.php');

        $unusedClasses = $this->filterByType($issues, Issue::TYPE_UNUSED_CLASS);
        // Request is used via type hint
        $this->assertCount(0, $unusedClasses);
    }

    /**
     * Test complex scenario with multiple files simulated
     */
    public function testComplexScenario()
    {
        // Simulate multiple file content in one analysis
        $code = <<<'PHP'
<?php
namespace App;

use App\Services\UserService;
use App\Services\UnusedService;
use DateTime;

interface RepositoryInterface {
    public function find($id);
}

class UserRepository implements RepositoryInterface {
    public function find($id) {
        return ['id' => $id];
    }
}

class UnusedRepository {
    public function nothing() {}
}

function helper() {
    return new DateTime();
}

function unusedHelper() {
    return 'never called';
}

$repo = new UserRepository();
$data = $repo->find(1);
helper();
PHP;

        $issues = $this->analyzeCode($code, '/test/complex.php');

        // Check unused class
        $unusedClasses = $this->filterByType($issues, Issue::TYPE_UNUSED_CLASS);
        $classNames = array_map(function ($i) {
            return $i->getSymbolName();
        }, $unusedClasses);
        $this->assertContains('App\\UnusedRepository', $classNames);

        // Check unused function
        $unusedFunctions = $this->filterByType($issues, Issue::TYPE_UNUSED_FUNCTION);
        $funcNames = array_map(function ($i) {
            return $i->getSymbolName();
        }, $unusedFunctions);
        $this->assertContains('App\\unusedHelper', $funcNames);

        // Check unused use statements
        $unusedUses = $this->filterByType($issues, Issue::TYPE_UNUSED_USE);
        $useNames = array_map(function ($i) {
            return $i->getSymbolName();
        }, $unusedUses);
        $this->assertContains('App\\Services\\UserService', $useNames);
        $this->assertContains('App\\Services\\UnusedService', $useNames);
    }

    /**
     * Test PHP 7+ features (if parser supports)
     */
    public function testPhp7Features()
    {
        $code = <<<'PHP'
<?php
declare(strict_types=1);

namespace App;

class Calculator {
    public function add(int $a, int $b): int {
        return $a + $b;
    }
}

$calc = new Calculator();
echo $calc->add(1, 2);
PHP;

        $issues = $this->analyzeCode($code, '/test/php7.php');

        $unusedClasses = $this->filterByType($issues, Issue::TYPE_UNUSED_CLASS);
        $this->assertCount(0, $unusedClasses);
    }

    /**
     * Test callback function reference detection
     *
     * Note: String-based callback detection (e.g., array_map('callback', $arr))
     * is a known limitation. Currently, only direct function calls are detected.
     * This test verifies that both functions are detected as unused when used
     * only via string callbacks.
     */
    public function testCallbackFunctionReference()
    {
        $code = <<<'PHP'
<?php
namespace App;

function myCallback($item) {
    return $item * 2;
}

function unusedCallback($item) {
    return $item;
}

$arr = [1, 2, 3];
$result = array_map('App\myCallback', $arr);
PHP;

        $issues = $this->analyzeCode($code, '/test/callback.php');

        $unusedFunctions = $this->filterByType($issues, Issue::TYPE_UNUSED_FUNCTION);
        // Note: String-based callbacks are not currently detected as usage
        // Both functions will be reported as unused
        $this->assertCount(2, $unusedFunctions);
    }

    /**
     * Analyze PHP code and return issues
     *
     * @param string $code PHP code
     * @param string $filePath File path
     *
     * @return array<Issue>
     */
    private function analyzeCode($code, $filePath)
    {
        // Build AST
        $ast = $this->astBuilder->buildFromString($code);

        // Collect symbols
        $symbolTable = new SymbolTable();
        $symbolCollector = new SymbolCollector($symbolTable);
        $symbolCollector->setCurrentFile($filePath);

        $traverser = new NodeTraverser();
        $traverser->addVisitor($symbolCollector);
        $traverser->traverse($ast);

        // Collect references
        $referenceCollector = new ReferenceCollector($filePath);

        $traverser2 = new NodeTraverser();
        $traverser2->addVisitor($referenceCollector);
        $traverser2->traverse($ast);

        $references = $referenceCollector->getReferences();

        // Create analysis context
        $context = new AnalysisContext($symbolTable, $references);

        // Set use statements for UseStatementAnalyzer
        $useStatements = $referenceCollector->getUseStatements();
        $context->setUseStatements($filePath, $useStatements);

        // Run analyzers
        $issues = array();

        $classAnalyzer = new ClassAnalyzer();
        $issues = array_merge($issues, $classAnalyzer->analyze($context));

        $functionAnalyzer = new FunctionAnalyzer();
        $issues = array_merge($issues, $functionAnalyzer->analyze($context));

        $useStatementAnalyzer = new UseStatementAnalyzer();
        $issues = array_merge($issues, $useStatementAnalyzer->analyze($context));

        return $issues;
    }

    /**
     * Filter issues by type
     *
     * @param array $issues Issues
     * @param string $type Issue type
     *
     * @return array
     */
    private function filterByType(array $issues, $type)
    {
        return array_values(array_filter($issues, function ($issue) use ($type) {
            return $issue->getType() === $type;
        }));
    }
}
