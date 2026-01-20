<?php
/**
 * MethodAnalyzer Tests
 */

namespace PhpKnip\Tests\Unit\Analyzer;

use PhpKnip\Tests\TestCase;
use PhpKnip\Analyzer\MethodAnalyzer;
use PhpKnip\Analyzer\AnalysisContext;
use PhpKnip\Analyzer\Issue;
use PhpKnip\Resolver\Symbol;
use PhpKnip\Resolver\SymbolTable;
use PhpKnip\Resolver\Reference;

class MethodAnalyzerTest extends TestCase
{
    /**
     * @var MethodAnalyzer
     */
    private $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new MethodAnalyzer();
    }

    public function testGetName()
    {
        $this->assertEquals('method-analyzer', $this->analyzer->getName());
    }

    public function testUnusedPrivateMethodIsDetected()
    {
        $symbolTable = new SymbolTable();
        $method = Symbol::createMethod('unusedMethod', 'App\\Service', Symbol::VISIBILITY_PRIVATE);
        $method->setFilePath('/src/Service.php');
        $method->setStartLine(20);
        $symbolTable->add($method);

        $context = new AnalysisContext($symbolTable, array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(1, $issues);
        $this->assertEquals(Issue::TYPE_UNUSED_METHOD, $issues[0]->getType());
        $this->assertEquals('App\\Service::unusedMethod', $issues[0]->getSymbolName());
    }

    public function testUsedPrivateMethodIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $method = Symbol::createMethod('usedMethod', 'App\\Service', Symbol::VISIBILITY_PRIVATE);
        $method->setFilePath('/src/Service.php');
        $method->setStartLine(20);
        $symbolTable->add($method);

        $references = array(
            Reference::createMethodCall('usedMethod', 'App\\Service'),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testPublicMethodIsNotAnalyzed()
    {
        $symbolTable = new SymbolTable();
        $method = Symbol::createMethod('publicMethod', 'App\\Service', Symbol::VISIBILITY_PUBLIC);
        $method->setFilePath('/src/Service.php');
        $method->setStartLine(20);
        $symbolTable->add($method);

        $context = new AnalysisContext($symbolTable, array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testProtectedMethodIsNotAnalyzed()
    {
        $symbolTable = new SymbolTable();
        $method = Symbol::createMethod('protectedMethod', 'App\\Service', Symbol::VISIBILITY_PROTECTED);
        $method->setFilePath('/src/Service.php');
        $method->setStartLine(20);
        $symbolTable->add($method);

        $context = new AnalysisContext($symbolTable, array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testMagicMethodConstructIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $method = Symbol::createMethod('__construct', 'App\\Service', Symbol::VISIBILITY_PRIVATE);
        $method->setFilePath('/src/Service.php');
        $method->setStartLine(10);
        $symbolTable->add($method);

        $context = new AnalysisContext($symbolTable, array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testMagicMethodToStringIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $method = Symbol::createMethod('__toString', 'App\\Service', Symbol::VISIBILITY_PRIVATE);
        $method->setFilePath('/src/Service.php');
        $method->setStartLine(10);
        $symbolTable->add($method);

        $context = new AnalysisContext($symbolTable, array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testMagicMethodInvokeIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $method = Symbol::createMethod('__invoke', 'App\\Handler', Symbol::VISIBILITY_PRIVATE);
        $method->setFilePath('/src/Handler.php');
        $method->setStartLine(10);
        $symbolTable->add($method);

        $context = new AnalysisContext($symbolTable, array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testStaticCallReferenceMakesMethodUsed()
    {
        $symbolTable = new SymbolTable();
        $method = Symbol::createMethod('helper', 'App\\Utility', Symbol::VISIBILITY_PRIVATE);
        $method->setFilePath('/src/Utility.php');
        $method->setStartLine(20);
        $method->setStatic(true);
        $symbolTable->add($method);

        $references = array(
            Reference::createStaticCall('App\\Utility', 'helper', '/src/Consumer.php', 15),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testIgnoredMethodIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $method = Symbol::createMethod('ignoredMethod', 'App\\Service', Symbol::VISIBILITY_PRIVATE);
        $method->setFilePath('/src/Service.php');
        $method->setStartLine(20);
        $symbolTable->add($method);

        $config = array(
            'ignore' => array(
                'symbols' => array('App\\Service::ignoredMethod'),
            ),
        );

        $context = new AnalysisContext($symbolTable, array(), $config);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testWildcardPatternIgnoresMethod()
    {
        $symbolTable = new SymbolTable();
        $method = Symbol::createMethod('testMethod', 'App\\Testing\\TestCase', Symbol::VISIBILITY_PRIVATE);
        $method->setFilePath('/src/Testing/TestCase.php');
        $method->setStartLine(20);
        $symbolTable->add($method);

        $config = array(
            'ignore' => array(
                'symbols' => array('App\\Testing\\*'),
            ),
        );

        $context = new AnalysisContext($symbolTable, array(), $config);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testMultipleUnusedMethodsAreAllDetected()
    {
        $symbolTable = new SymbolTable();

        $method1 = Symbol::createMethod('unusedA', 'App\\Service', Symbol::VISIBILITY_PRIVATE);
        $method1->setFilePath('/src/Service.php');
        $method1->setStartLine(20);
        $symbolTable->add($method1);

        $method2 = Symbol::createMethod('unusedB', 'App\\Service', Symbol::VISIBILITY_PRIVATE);
        $method2->setFilePath('/src/Service.php');
        $method2->setStartLine(30);
        $symbolTable->add($method2);

        $method3 = Symbol::createMethod('usedC', 'App\\Service', Symbol::VISIBILITY_PRIVATE);
        $method3->setFilePath('/src/Service.php');
        $method3->setStartLine(40);
        $symbolTable->add($method3);

        $references = array(
            Reference::createMethodCall('usedC', 'App\\Service'),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(2, $issues);

        $methodNames = array_map(function ($issue) {
            return $issue->getSymbolName();
        }, $issues);

        $this->assertContains('App\\Service::unusedA', $methodNames);
        $this->assertContains('App\\Service::unusedB', $methodNames);
    }

    public function testMethodWithoutParentIsSkipped()
    {
        $symbolTable = new SymbolTable();

        // Create a method without parent (edge case)
        $method = new Symbol(Symbol::TYPE_METHOD, 'orphanMethod');
        $method->setVisibility(Symbol::VISIBILITY_PRIVATE);
        $method->setFilePath('/src/orphan.php');
        $method->setStartLine(10);
        $symbolTable->add($method);

        $context = new AnalysisContext($symbolTable, array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }
}
