<?php
/**
 * PropertyAnalyzer Tests
 */

namespace PhpKnip\Tests\Unit\Analyzer;

use PhpKnip\Tests\TestCase;
use PhpKnip\Analyzer\PropertyAnalyzer;
use PhpKnip\Analyzer\AnalysisContext;
use PhpKnip\Analyzer\Issue;
use PhpKnip\Resolver\Symbol;
use PhpKnip\Resolver\SymbolTable;
use PhpKnip\Resolver\Reference;

class PropertyAnalyzerTest extends TestCase
{
    /**
     * @var PropertyAnalyzer
     */
    private $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new PropertyAnalyzer();
    }

    public function testGetName()
    {
        $this->assertEquals('property-analyzer', $this->analyzer->getName());
    }

    public function testUnusedPrivatePropertyIsDetected()
    {
        $symbolTable = new SymbolTable();
        $property = Symbol::createProperty('unusedProperty', 'App\\Service', Symbol::VISIBILITY_PRIVATE);
        $property->setFilePath('/src/Service.php');
        $property->setStartLine(15);
        $symbolTable->add($property);

        $context = new AnalysisContext($symbolTable, array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(1, $issues);
        $this->assertEquals(Issue::TYPE_UNUSED_PROPERTY, $issues[0]->getType());
        $this->assertEquals('App\\Service::$unusedProperty', $issues[0]->getSymbolName());
    }

    public function testUsedPrivatePropertyIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $property = Symbol::createProperty('usedProperty', 'App\\Service', Symbol::VISIBILITY_PRIVATE);
        $property->setFilePath('/src/Service.php');
        $property->setStartLine(15);
        $symbolTable->add($property);

        $ref = new Reference(Reference::TYPE_PROPERTY_ACCESS, 'usedProperty');
        $ref->setFilePath('/src/Service.php');
        $ref->setLine(30);

        $context = new AnalysisContext($symbolTable, array($ref));

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testPublicPropertyIsNotAnalyzed()
    {
        $symbolTable = new SymbolTable();
        $property = Symbol::createProperty('publicProperty', 'App\\Service', Symbol::VISIBILITY_PUBLIC);
        $property->setFilePath('/src/Service.php');
        $property->setStartLine(15);
        $symbolTable->add($property);

        $context = new AnalysisContext($symbolTable, array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testProtectedPropertyIsNotAnalyzed()
    {
        $symbolTable = new SymbolTable();
        $property = Symbol::createProperty('protectedProperty', 'App\\Service', Symbol::VISIBILITY_PROTECTED);
        $property->setFilePath('/src/Service.php');
        $property->setStartLine(15);
        $symbolTable->add($property);

        $context = new AnalysisContext($symbolTable, array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testStaticPropertyAccessMakesPropertyUsed()
    {
        $symbolTable = new SymbolTable();
        $property = Symbol::createProperty('staticProperty', 'App\\Utility', Symbol::VISIBILITY_PRIVATE);
        $property->setFilePath('/src/Utility.php');
        $property->setStartLine(15);
        $property->setStatic(true);
        $symbolTable->add($property);

        $ref = new Reference(Reference::TYPE_STATIC_PROPERTY, 'staticProperty');
        $ref->setSymbolParent('App\\Utility');
        $ref->setFilePath('/src/Consumer.php');
        $ref->setLine(25);

        $context = new AnalysisContext($symbolTable, array($ref));

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testIgnoredPropertyIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $property = Symbol::createProperty('ignoredProperty', 'App\\Service', Symbol::VISIBILITY_PRIVATE);
        $property->setFilePath('/src/Service.php');
        $property->setStartLine(15);
        $symbolTable->add($property);

        $config = array(
            'ignore' => array(
                'symbols' => array('App\\Service::$ignoredProperty'),
            ),
        );

        $context = new AnalysisContext($symbolTable, array(), $config);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testWildcardPatternIgnoresProperty()
    {
        $symbolTable = new SymbolTable();
        $property = Symbol::createProperty('testProperty', 'App\\Testing\\Mock', Symbol::VISIBILITY_PRIVATE);
        $property->setFilePath('/src/Testing/Mock.php');
        $property->setStartLine(15);
        $symbolTable->add($property);

        $config = array(
            'ignore' => array(
                'symbols' => array('App\\Testing\\*'),
            ),
        );

        $context = new AnalysisContext($symbolTable, array(), $config);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testMultipleUnusedPropertiesAreAllDetected()
    {
        $symbolTable = new SymbolTable();

        $prop1 = Symbol::createProperty('unusedA', 'App\\Service', Symbol::VISIBILITY_PRIVATE);
        $prop1->setFilePath('/src/Service.php');
        $prop1->setStartLine(10);
        $symbolTable->add($prop1);

        $prop2 = Symbol::createProperty('unusedB', 'App\\Service', Symbol::VISIBILITY_PRIVATE);
        $prop2->setFilePath('/src/Service.php');
        $prop2->setStartLine(11);
        $symbolTable->add($prop2);

        $prop3 = Symbol::createProperty('usedC', 'App\\Service', Symbol::VISIBILITY_PRIVATE);
        $prop3->setFilePath('/src/Service.php');
        $prop3->setStartLine(12);
        $symbolTable->add($prop3);

        $ref = new Reference(Reference::TYPE_PROPERTY_ACCESS, 'usedC');
        $ref->setFilePath('/src/Service.php');
        $ref->setLine(50);

        $context = new AnalysisContext($symbolTable, array($ref));

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(2, $issues);

        $propertyNames = array_map(function ($issue) {
            return $issue->getSymbolName();
        }, $issues);

        $this->assertContains('App\\Service::$unusedA', $propertyNames);
        $this->assertContains('App\\Service::$unusedB', $propertyNames);
    }

    public function testPropertyWithoutParentIsSkipped()
    {
        $symbolTable = new SymbolTable();

        // Create a property without parent (edge case)
        $property = new Symbol(Symbol::TYPE_PROPERTY, 'orphanProperty');
        $property->setVisibility(Symbol::VISIBILITY_PRIVATE);
        $property->setFilePath('/src/orphan.php');
        $property->setStartLine(10);
        $symbolTable->add($property);

        $context = new AnalysisContext($symbolTable, array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testPropertyUsedViaThisIsNotFlagged()
    {
        $symbolTable = new SymbolTable();
        $property = Symbol::createProperty('data', 'App\\Model', Symbol::VISIBILITY_PRIVATE);
        $property->setFilePath('/src/Model.php');
        $property->setStartLine(10);
        $symbolTable->add($property);

        // $this->data access within same class
        $ref = new Reference(Reference::TYPE_PROPERTY_ACCESS, 'data');
        $ref->setFilePath('/src/Model.php');
        $ref->setLine(25);
        $ref->setContext('App\\Model::getData');

        $context = new AnalysisContext($symbolTable, array($ref));

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }
}
