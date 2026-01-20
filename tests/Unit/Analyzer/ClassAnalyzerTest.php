<?php
/**
 * ClassAnalyzer Tests
 */

namespace PhpKnip\Tests\Unit\Analyzer;

use PhpKnip\Tests\TestCase;
use PhpKnip\Analyzer\ClassAnalyzer;
use PhpKnip\Analyzer\AnalysisContext;
use PhpKnip\Analyzer\Issue;
use PhpKnip\Resolver\Symbol;
use PhpKnip\Resolver\SymbolTable;
use PhpKnip\Resolver\Reference;

class ClassAnalyzerTest extends TestCase
{
    /**
     * @var ClassAnalyzer
     */
    private $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new ClassAnalyzer();
    }

    public function testGetName()
    {
        $this->assertEquals('class-analyzer', $this->analyzer->getName());
    }

    public function testUnusedClassIsDetected()
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
        $this->assertEquals(Issue::TYPE_UNUSED_CLASS, $issues[0]->getType());
        $this->assertEquals('App\\UnusedClass', $issues[0]->getSymbolName());
    }

    public function testUsedClassIsNotFlagged()
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

    public function testClassUsedViaExtendsIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createClass(
            'BaseClass',
            'App',
            '/src/BaseClass.php',
            10
        ));

        $references = array(
            Reference::createExtends('App\\BaseClass', '/src/ChildClass.php', 5),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testClassUsedViaStaticCallIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createClass(
            'Utility',
            'App',
            '/src/Utility.php',
            10
        ));

        $references = array(
            Reference::createStaticCall('App\\Utility', 'helper', '/src/Consumer.php', 15),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testClassUsedViaTypeHintIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createClass(
            'Service',
            'App',
            '/src/Service.php',
            10
        ));

        $references = array(
            Reference::createTypeHint('App\\Service', '/src/Consumer.php', 20),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testAbstractClassWithSubclassIsNotFlagged()
    {
        $class = Symbol::createClass(
            'AbstractService',
            'App',
            '/src/AbstractService.php',
            10
        );
        $class->setAbstract(true);

        $symbolTable = new SymbolTable();
        $symbolTable->add($class);

        $references = array(
            Reference::createExtends('App\\AbstractService', '/src/ConcreteService.php', 5),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testUnusedInterfaceIsDetected()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createInterface(
            'UnusedInterface',
            'App',
            '/src/UnusedInterface.php',
            5
        ));

        $context = new AnalysisContext($symbolTable, array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(1, $issues);
        $this->assertEquals(Issue::TYPE_UNUSED_INTERFACE, $issues[0]->getType());
    }

    public function testImplementedInterfaceIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createInterface(
            'ServiceInterface',
            'App',
            '/src/ServiceInterface.php',
            5
        ));

        $references = array(
            Reference::createImplements('App\\ServiceInterface', '/src/Service.php', 10),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testInterfaceUsedAsTypeHintIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createInterface(
            'LoggerInterface',
            'App',
            '/src/LoggerInterface.php',
            5
        ));

        $references = array(
            Reference::createTypeHint('App\\LoggerInterface', '/src/Service.php', 15),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testUnusedTraitIsDetected()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createTrait(
            'UnusedTrait',
            'App',
            '/src/UnusedTrait.php',
            5
        ));

        $context = new AnalysisContext($symbolTable, array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(1, $issues);
        $this->assertEquals(Issue::TYPE_UNUSED_TRAIT, $issues[0]->getType());
    }

    public function testUsedTraitIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createTrait(
            'LoggableTrait',
            'App',
            '/src/LoggableTrait.php',
            5
        ));

        $references = array(
            Reference::createUseTrait('App\\LoggableTrait', '/src/Service.php', 15),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testIgnoredClassIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createClass(
            'TestHelper',
            'App\\Testing',
            '/src/Testing/TestHelper.php',
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

    public function testClassUsedViaShortNameIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createClass(
            'Helper',
            'App\\Utils',
            '/src/Utils/Helper.php',
            10
        ));

        // Reference using short name (happens when use statement imports it)
        $references = array(
            Reference::createNew('Helper', '/src/Consumer.php', 20),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testMultipleUnusedClassesAreAllDetected()
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

        $symbolNames = array_map(function ($issue) {
            return $issue->getSymbolName();
        }, $issues);

        $this->assertContains('App\\UnusedA', $symbolNames);
        $this->assertContains('App\\UnusedB', $symbolNames);
    }

    public function testClassUsedViaInstanceofIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createClass(
            'Exception',
            'App',
            '/src/Exception.php',
            10
        ));

        $references = array(
            Reference::createInstanceof('App\\Exception', '/src/Handler.php', 25),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testClassUsedViaCatchIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $symbolTable->add(Symbol::createClass(
            'CustomException',
            'App',
            '/src/CustomException.php',
            10
        ));

        $references = array(
            Reference::createCatch('App\\CustomException', '/src/Handler.php', 30),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }
}
