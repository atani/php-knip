<?php
/**
 * ConstantAnalyzer Tests
 */

namespace PhpKnip\Tests\Unit\Analyzer;

use PhpKnip\Tests\TestCase;
use PhpKnip\Analyzer\ConstantAnalyzer;
use PhpKnip\Analyzer\AnalysisContext;
use PhpKnip\Analyzer\Issue;
use PhpKnip\Resolver\Symbol;
use PhpKnip\Resolver\SymbolTable;
use PhpKnip\Resolver\Reference;

class ConstantAnalyzerTest extends TestCase
{
    /**
     * @var ConstantAnalyzer
     */
    private $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new ConstantAnalyzer();
    }

    public function testGetName()
    {
        $this->assertEquals('constant-analyzer', $this->analyzer->getName());
    }

    public function testUnusedGlobalConstantIsDetected()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createConstant(
            'UNUSED_CONSTANT',
            'App',
            '/src/constants.php',
            10
        ));

        $context = new AnalysisContext($symbolTable, array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(1, $issues);
        $this->assertEquals(Issue::TYPE_UNUSED_CONSTANT, $issues[0]->getType());
        $this->assertEquals('App\\UNUSED_CONSTANT', $issues[0]->getSymbolName());
    }

    public function testUsedGlobalConstantIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createConstant(
            'USED_CONSTANT',
            'App',
            '/src/constants.php',
            10
        ));

        $references = array(
            Reference::createConstant('USED_CONSTANT', null),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testUnusedClassConstantIsDetected()
    {
        $symbolTable = new SymbolTable();
        $classConstant = Symbol::createClassConstant('UNUSED_CONST', 'App\\Service');
        $classConstant->setFilePath('/src/Service.php');
        $classConstant->setStartLine(15);
        $symbolTable->add($classConstant);

        $context = new AnalysisContext($symbolTable, array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(1, $issues);
        $this->assertEquals(Issue::TYPE_UNUSED_CONSTANT, $issues[0]->getType());
        $this->assertEquals('App\\Service::UNUSED_CONST', $issues[0]->getSymbolName());
    }

    public function testUsedClassConstantIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $classConstant = Symbol::createClassConstant('STATUS_ACTIVE', 'App\\User');
        $classConstant->setFilePath('/src/User.php');
        $classConstant->setStartLine(15);
        $symbolTable->add($classConstant);

        $references = array(
            Reference::createConstant('STATUS_ACTIVE', 'App\\User'),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testClassConstantUsedViaSelfIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $classConstant = Symbol::createClassConstant('MAX_RETRIES', 'App\\Service');
        $classConstant->setFilePath('/src/Service.php');
        $classConstant->setStartLine(15);
        $symbolTable->add($classConstant);

        // When using self::MAX_RETRIES, the reference has the class as parent
        $references = array(
            Reference::createConstant('MAX_RETRIES', 'App\\Service'),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testIgnoredConstantIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createConstant(
            'DEBUG_MODE',
            'App',
            '/src/constants.php',
            10
        ));

        $config = array(
            'ignore' => array(
                'symbols' => array('App\\DEBUG_MODE'),
            ),
        );

        $context = new AnalysisContext($symbolTable, array(), $config);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testIgnoredClassConstantIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $classConstant = Symbol::createClassConstant('INTERNAL_FLAG', 'App\\Config');
        $classConstant->setFilePath('/src/Config.php');
        $classConstant->setStartLine(15);
        $symbolTable->add($classConstant);

        $config = array(
            'ignore' => array(
                'symbols' => array('App\\Config::INTERNAL_FLAG'),
            ),
        );

        $context = new AnalysisContext($symbolTable, array(), $config);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testWildcardPatternIgnoresConstants()
    {
        $symbolTable = new SymbolTable();
        $classConstant = Symbol::createClassConstant('TEST_VALUE', 'App\\Testing\\Fixtures');
        $classConstant->setFilePath('/src/Testing/Fixtures.php');
        $classConstant->setStartLine(10);
        $symbolTable->add($classConstant);

        $config = array(
            'ignore' => array(
                'symbols' => array('App\\Testing\\*'),
            ),
        );

        $context = new AnalysisContext($symbolTable, array(), $config);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testMultipleUnusedConstantsAreAllDetected()
    {
        $symbolTable = new SymbolTable();

        $const1 = Symbol::createClassConstant('UNUSED_A', 'App\\Service');
        $const1->setFilePath('/src/Service.php');
        $const1->setStartLine(10);
        $symbolTable->add($const1);

        $const2 = Symbol::createClassConstant('UNUSED_B', 'App\\Service');
        $const2->setFilePath('/src/Service.php');
        $const2->setStartLine(11);
        $symbolTable->add($const2);

        $const3 = Symbol::createClassConstant('USED_C', 'App\\Service');
        $const3->setFilePath('/src/Service.php');
        $const3->setStartLine(12);
        $symbolTable->add($const3);

        $references = array(
            Reference::createConstant('USED_C', 'App\\Service'),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(2, $issues);

        $constantNames = array_map(function ($issue) {
            return $issue->getSymbolName();
        }, $issues);

        $this->assertContains('App\\Service::UNUSED_A', $constantNames);
        $this->assertContains('App\\Service::UNUSED_B', $constantNames);
    }

    public function testClassConstantWithoutParentIsSkipped()
    {
        $symbolTable = new SymbolTable();

        // Create a class constant without parent (edge case)
        $classConstant = new Symbol(Symbol::TYPE_CLASS_CONSTANT, 'ORPHAN_CONST');
        $classConstant->setFilePath('/src/orphan.php');
        $classConstant->setStartLine(10);
        $symbolTable->add($classConstant);

        $context = new AnalysisContext($symbolTable, array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testBothGlobalAndClassConstantsAnalyzed()
    {
        $symbolTable = new SymbolTable();

        // Global constant
        $symbolTable->add(Symbol::createConstant(
            'GLOBAL_UNUSED',
            'App',
            '/src/constants.php',
            5
        ));

        // Class constant
        $classConstant = Symbol::createClassConstant('CLASS_UNUSED', 'App\\Config');
        $classConstant->setFilePath('/src/Config.php');
        $classConstant->setStartLine(10);
        $symbolTable->add($classConstant);

        $context = new AnalysisContext($symbolTable, array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(2, $issues);
    }
}
