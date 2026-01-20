<?php
/**
 * FileAnalyzer Tests
 */

namespace PhpKnip\Tests\Unit\Analyzer;

use PhpKnip\Tests\TestCase;
use PhpKnip\Analyzer\FileAnalyzer;
use PhpKnip\Analyzer\AnalysisContext;
use PhpKnip\Analyzer\Issue;
use PhpKnip\Resolver\Symbol;
use PhpKnip\Resolver\SymbolTable;
use PhpKnip\Resolver\Reference;

class FileAnalyzerTest extends TestCase
{
    /**
     * @var FileAnalyzer
     */
    private $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new FileAnalyzer();
    }

    public function testGetName()
    {
        $this->assertEquals('file-analyzer', $this->analyzer->getName());
    }

    public function testFileWithOnlyUnusedSymbolsIsDetected()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createClass(
            'UnusedClass',
            'App',
            '/src/UnusedClass.php',
            10
        ));

        $context = new AnalysisContext($symbolTable, array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(1, $issues);
        $this->assertEquals(Issue::TYPE_UNUSED_FILE, $issues[0]->getType());
        $this->assertEquals('/src/UnusedClass.php', $issues[0]->getFilePath());
    }

    public function testFileWithUsedSymbolIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createClass(
            'UsedClass',
            'App',
            '/src/UsedClass.php',
            10
        ));

        $references = array(
            Reference::createNew('App\\UsedClass', '/src/Consumer.php', 20),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testFileWithMultipleSymbolsOneUsedIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createClass(
            'UnusedHelper',
            'App',
            '/src/Helpers.php',
            10
        ));
        $symbolTable->add(Symbol::createClass(
            'UsedHelper',
            'App',
            '/src/Helpers.php',
            50
        ));

        $references = array(
            Reference::createNew('App\\UsedHelper', '/src/Consumer.php', 20),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testEntryPointFileIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createClass(
            'Application',
            'App',
            '/project/public/index.php',
            10
        ));

        $config = array(
            'basePath' => '/project',
        );

        $context = new AnalysisContext($symbolTable, array(), $config);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testPublicIndexPhpIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createClass(
            'Application',
            'App',
            '/project/public/index.php',
            10
        ));

        $config = array(
            'basePath' => '/project',
        );

        $context = new AnalysisContext($symbolTable, array(), $config);

        $issues = $this->analyzer->analyze($context);

        // public/index.php is a default entry point pattern
        $this->assertCount(0, $issues);
    }

    public function testBootstrapFileIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createClass(
            'Bootstrap',
            'App',
            '/project/bootstrap.php',
            10
        ));

        $config = array(
            'basePath' => '/project',
        );

        $context = new AnalysisContext($symbolTable, array(), $config);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testCustomEntryPointIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createClass(
            'Worker',
            'App',
            '/project/workers/queue.php',
            10
        ));

        $config = array(
            'basePath' => '/project',
            'entry_points' => array('workers/queue.php'),
        );

        $context = new AnalysisContext($symbolTable, array(), $config);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testIgnoredPathIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createClass(
            'TestFixture',
            'App\\Testing',
            '/project/tests/fixtures/TestFixture.php',
            10
        ));

        $config = array(
            'ignore' => array(
                'paths' => array('**/tests/**'),
            ),
        );

        $context = new AnalysisContext($symbolTable, array(), $config);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testFileWithNoSymbolsIsNotFlagged()
    {
        // Files with no symbols (e.g., config files) should not be flagged
        $symbolTable = new SymbolTable();

        $context = new AnalysisContext($symbolTable, array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testOnlyTopLevelSymbolsConsideredForFileAnalysis()
    {
        $symbolTable = new SymbolTable();

        // Add a class (top-level)
        $symbolTable->add(Symbol::createClass(
            'Service',
            'App',
            '/src/Service.php',
            10
        ));

        // Add a method (not top-level, should not count)
        $method = Symbol::createMethod('doSomething', 'App\\Service', Symbol::VISIBILITY_PUBLIC);
        $method->setFilePath('/src/Service.php');
        $method->setStartLine(20);
        $symbolTable->add($method);

        // The class is not referenced, only the method
        $references = array(
            Reference::createMethodCall('doSomething', 'App\\Service'),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        // File should be flagged because the top-level class is not referenced
        $this->assertCount(1, $issues);
        $this->assertEquals(Issue::TYPE_UNUSED_FILE, $issues[0]->getType());
    }

    public function testMultipleUnusedFilesAreAllDetected()
    {
        $symbolTable = new SymbolTable();

        $symbolTable->add(Symbol::createClass(
            'UnusedA',
            'App',
            '/src/UnusedA.php',
            10
        ));

        $symbolTable->add(Symbol::createClass(
            'UnusedB',
            'App',
            '/src/UnusedB.php',
            10
        ));

        $symbolTable->add(Symbol::createClass(
            'UsedC',
            'App',
            '/src/UsedC.php',
            10
        ));

        $references = array(
            Reference::createNew('App\\UsedC', '/src/Consumer.php', 20),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(2, $issues);

        $filePaths = array_map(function ($issue) {
            return $issue->getFilePath();
        }, $issues);

        $this->assertContains('/src/UnusedA.php', $filePaths);
        $this->assertContains('/src/UnusedB.php', $filePaths);
    }

    public function testSymbolReferencedByShortNameMakesFileUsed()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createClass(
            'Helper',
            'App\\Utils',
            '/src/Utils/Helper.php',
            10
        ));

        // Reference using short name (from use statement)
        $references = array(
            Reference::createNew('Helper', '/src/Consumer.php', 20),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testFunctionFileWithUsedFunctionIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createFunction(
            'helper_function',
            'App\\Helpers',
            '/src/helpers.php',
            10
        ));

        $references = array(
            Reference::createFunctionCall('helper_function', '/src/Consumer.php', 30),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testSymbolWithNullFilePathIsSkipped()
    {
        $symbolTable = new SymbolTable();

        // Create symbol without file path
        $symbol = Symbol::createClass('NoFileClass', 'App', null, null);
        $symbolTable->add($symbol);

        $context = new AnalysisContext($symbolTable, array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }
}
