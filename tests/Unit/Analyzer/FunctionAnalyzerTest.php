<?php
/**
 * FunctionAnalyzer Tests
 */

namespace PhpKnip\Tests\Unit\Analyzer;

use PhpKnip\Tests\TestCase;
use PhpKnip\Analyzer\FunctionAnalyzer;
use PhpKnip\Analyzer\AnalysisContext;
use PhpKnip\Analyzer\Issue;
use PhpKnip\Resolver\Symbol;
use PhpKnip\Resolver\SymbolTable;
use PhpKnip\Resolver\Reference;

class FunctionAnalyzerTest extends TestCase
{
    /**
     * @var FunctionAnalyzer
     */
    private $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new FunctionAnalyzer();
    }

    public function testGetName()
    {
        $this->assertEquals('function-analyzer', $this->analyzer->getName());
    }

    public function testUnusedFunctionIsDetected()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createFunction(
            'unused_helper',
            'App',
            '/src/helpers.php',
            10
        ));

        $context = new AnalysisContext($symbolTable, array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(1, $issues);
        $this->assertEquals(Issue::TYPE_UNUSED_FUNCTION, $issues[0]->getType());
        $this->assertEquals('App\\unused_helper', $issues[0]->getSymbolName());
    }

    public function testCalledFunctionIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createFunction(
            'format_date',
            'App',
            '/src/helpers.php',
            10
        ));

        $references = array(
            Reference::createFunctionCall('App\\format_date', '/src/Consumer.php', 20),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testFunctionCalledByShortNameIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createFunction(
            'my_helper',
            'App\\Utils',
            '/src/utils.php',
            10
        ));

        // Called without namespace (after use function statement)
        $references = array(
            Reference::createFunctionCall('my_helper', '/src/Consumer.php', 20),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testIgnoredFunctionIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createFunction(
            'test_helper',
            'App\\Testing',
            '/src/testing.php',
            10
        ));

        $config = array(
            'ignore' => array(
                'symbols' => array('App\\Testing\\*'),
            ),
        );

        $context = new AnalysisContext($symbolTable, array(), $config);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testUnderscorePrefixedFunctionIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createFunction(
            '_internal_helper',
            'App',
            '/src/helpers.php',
            10
        ));

        $context = new AnalysisContext($symbolTable, array());

        $issues = $this->analyzer->analyze($context);

        // Functions starting with underscore are often intentionally unused
        $this->assertCount(0, $issues);
    }

    public function testMultipleUnusedFunctionsAreAllDetected()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createFunction(
            'unused_a',
            'App',
            '/src/helpers.php',
            10
        ));
        $symbolTable->add(Symbol::createFunction(
            'unused_b',
            'App',
            '/src/helpers.php',
            20
        ));
        $symbolTable->add(Symbol::createFunction(
            'used_c',
            'App',
            '/src/helpers.php',
            30
        ));

        $references = array(
            Reference::createFunctionCall('App\\used_c', '/src/Consumer.php', 15),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(2, $issues);

        $symbolNames = array_map(function ($issue) {
            return $issue->getSymbolName();
        }, $issues);

        $this->assertContains('App\\unused_a', $symbolNames);
        $this->assertContains('App\\unused_b', $symbolNames);
    }

    public function testCallbackReferencedFunctionIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createFunction(
            'my_callback',
            'App',
            '/src/helpers.php',
            10
        ));

        // Function used as callback in array_map
        $ref = Reference::createFunctionCall('array_map', '/src/Consumer.php', 20);
        $ref->setMetadata('stringLiterals', array('App\\my_callback'));

        $references = array($ref);

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testCallbackReferencedByShortNameIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createFunction(
            'my_callback',
            'App',
            '/src/helpers.php',
            10
        ));

        // Function used as callback by short name
        $ref = Reference::createFunctionCall('call_user_func', '/src/Consumer.php', 20);
        $ref->setMetadata('stringLiterals', array('my_callback'));

        $references = array($ref);

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testGlobalFunctionIsDetectedAsUnused()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createFunction(
            'global_helper',
            null,  // No namespace
            '/src/helpers.php',
            10
        ));

        $context = new AnalysisContext($symbolTable, array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(1, $issues);
        $this->assertEquals('global_helper', $issues[0]->getSymbolName());
    }

    public function testPatternMatchingIgnore()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createFunction(
            'helper_for_tests',
            'App',
            '/src/helpers.php',
            10
        ));

        $config = array(
            'ignore' => array(
                'symbols' => array('*_for_tests'),
            ),
        );

        $context = new AnalysisContext($symbolTable, array(), $config);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testIssueSeverityIsError()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createFunction(
            'unused_func',
            'App',
            '/src/helpers.php',
            10
        ));

        $context = new AnalysisContext($symbolTable, array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(1, $issues);
        $this->assertEquals(Issue::SEVERITY_ERROR, $issues[0]->getSeverity());
    }

    public function testIssueContainsFileAndLineInfo()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createFunction(
            'unused_func',
            'App',
            '/src/helpers.php',
            42
        ));

        $context = new AnalysisContext($symbolTable, array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(1, $issues);
        $this->assertEquals('/src/helpers.php', $issues[0]->getFilePath());
        $this->assertEquals(42, $issues[0]->getLine());
    }
}
