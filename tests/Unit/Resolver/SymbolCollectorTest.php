<?php
/**
 * SymbolCollector Test
 */

namespace PhpKnip\Tests\Unit\Resolver;

use PhpKnip\Tests\TestCase;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpKnip\Resolver\Symbol;
use PhpKnip\Resolver\SymbolTable;
use PhpKnip\Resolver\SymbolCollector;

class SymbolCollectorTest extends TestCase
{
    /**
     * @var \PhpParser\Parser
     */
    private $parser;

    protected function setUp(): void
    {
        $factory = new ParserFactory();
        $this->parser = $factory->create(ParserFactory::PREFER_PHP7);
    }

    /**
     * Parse code and collect symbols
     *
     * @param string $code PHP code
     * @return SymbolTable
     */
    private function collectSymbols($code)
    {
        $ast = $this->parser->parse($code);

        $collector = new SymbolCollector();
        $collector->setCurrentFile('test.php');

        $traverser = new NodeTraverser();
        $traverser->addVisitor($collector);
        $traverser->traverse($ast);

        return $collector->getSymbolTable();
    }

    public function testCollectClass()
    {
        $code = '<?php class TestClass {}';
        $table = $this->collectSymbols($code);

        $this->assertEquals(1, $table->countByType(Symbol::TYPE_CLASS));

        $classes = $table->getClasses();
        $this->assertCount(1, $classes);
        $this->assertEquals('TestClass', $classes[0]->getName());
    }

    public function testCollectClassWithNamespace()
    {
        $code = '<?php namespace App\\Models; class User {}';
        $table = $this->collectSymbols($code);

        $classes = $table->getClasses();
        $this->assertCount(1, $classes);
        $this->assertEquals('User', $classes[0]->getName());
        $this->assertEquals('App\\Models', $classes[0]->getNamespace());
        $this->assertEquals('App\\Models\\User', $classes[0]->getFullyQualifiedName());
    }

    public function testCollectClassWithExtends()
    {
        $code = '<?php class Child extends ParentClass {}';
        $table = $this->collectSymbols($code);

        $classes = $table->getClasses();
        $this->assertEquals(array('ParentClass'), $classes[0]->getExtends());
    }

    public function testCollectClassWithImplements()
    {
        $code = '<?php class MyClass implements InterfaceA, InterfaceB {}';
        $table = $this->collectSymbols($code);

        $classes = $table->getClasses();
        $this->assertEquals(array('InterfaceA', 'InterfaceB'), $classes[0]->getImplements());
    }

    public function testCollectInterface()
    {
        $code = '<?php interface MyInterface {}';
        $table = $this->collectSymbols($code);

        $this->assertEquals(1, $table->countByType(Symbol::TYPE_INTERFACE));

        $interfaces = $table->getInterfaces();
        $this->assertEquals('MyInterface', $interfaces[0]->getName());
    }

    public function testCollectTrait()
    {
        $code = '<?php trait MyTrait {}';
        $table = $this->collectSymbols($code);

        $this->assertEquals(1, $table->countByType(Symbol::TYPE_TRAIT));

        $traits = $table->getTraits();
        $this->assertEquals('MyTrait', $traits[0]->getName());
    }

    public function testCollectClassWithTrait()
    {
        $code = '<?php class MyClass { use TraitA, TraitB; }';
        $table = $this->collectSymbols($code);

        $classes = $table->getClasses();
        $this->assertEquals(array('TraitA', 'TraitB'), $classes[0]->getUses());
    }

    public function testCollectFunction()
    {
        $code = '<?php function myFunction() {}';
        $table = $this->collectSymbols($code);

        $this->assertEquals(1, $table->countByType(Symbol::TYPE_FUNCTION));

        $functions = $table->getFunctions();
        $this->assertEquals('myFunction', $functions[0]->getName());
    }

    public function testCollectMethod()
    {
        $code = '<?php class MyClass { public function myMethod() {} }';
        $table = $this->collectSymbols($code);

        $methods = $table->getByType(Symbol::TYPE_METHOD);
        $this->assertCount(1, $methods);
        $this->assertEquals('myMethod', $methods[0]->getName());
        $this->assertEquals('MyClass', $methods[0]->getParent());
        $this->assertEquals(Symbol::VISIBILITY_PUBLIC, $methods[0]->getVisibility());
    }

    public function testCollectPrivateMethod()
    {
        $code = '<?php class MyClass { private function privateMethod() {} }';
        $table = $this->collectSymbols($code);

        $methods = $table->getByType(Symbol::TYPE_METHOD);
        $this->assertEquals(Symbol::VISIBILITY_PRIVATE, $methods[0]->getVisibility());
    }

    public function testCollectProtectedMethod()
    {
        $code = '<?php class MyClass { protected function protectedMethod() {} }';
        $table = $this->collectSymbols($code);

        $methods = $table->getByType(Symbol::TYPE_METHOD);
        $this->assertEquals(Symbol::VISIBILITY_PROTECTED, $methods[0]->getVisibility());
    }

    public function testCollectStaticMethod()
    {
        $code = '<?php class MyClass { public static function staticMethod() {} }';
        $table = $this->collectSymbols($code);

        $methods = $table->getByType(Symbol::TYPE_METHOD);
        $this->assertTrue($methods[0]->isStatic());
    }

    public function testCollectProperty()
    {
        $code = '<?php class MyClass { private $property; }';
        $table = $this->collectSymbols($code);

        $properties = $table->getByType(Symbol::TYPE_PROPERTY);
        $this->assertCount(1, $properties);
        $this->assertEquals('property', $properties[0]->getName());
        $this->assertEquals(Symbol::VISIBILITY_PRIVATE, $properties[0]->getVisibility());
    }

    public function testCollectClassConstant()
    {
        $code = '<?php class MyClass { const MY_CONST = 1; }';
        $table = $this->collectSymbols($code);

        $constants = $table->getByType(Symbol::TYPE_CLASS_CONSTANT);
        $this->assertCount(1, $constants);
        $this->assertEquals('MY_CONST', $constants[0]->getName());
    }

    public function testCollectGlobalConstant()
    {
        $code = '<?php const GLOBAL_CONST = 1;';
        $table = $this->collectSymbols($code);

        $constants = $table->getByType(Symbol::TYPE_CONSTANT);
        $this->assertCount(1, $constants);
        $this->assertEquals('GLOBAL_CONST', $constants[0]->getName());
    }

    public function testCollectDefineConstant()
    {
        $code = "<?php define('MY_DEFINE', 'value');";
        $table = $this->collectSymbols($code);

        $constants = $table->getByType(Symbol::TYPE_CONSTANT);
        $this->assertCount(1, $constants);
        $this->assertEquals('MY_DEFINE', $constants[0]->getName());
    }

    public function testCollectAbstractClass()
    {
        $code = '<?php abstract class AbstractClass {}';
        $table = $this->collectSymbols($code);

        $classes = $table->getClasses();
        $this->assertTrue($classes[0]->isAbstract());
    }

    public function testCollectFinalClass()
    {
        $code = '<?php final class FinalClass {}';
        $table = $this->collectSymbols($code);

        $classes = $table->getClasses();
        $this->assertTrue($classes[0]->isFinal());
    }

    public function testCollectMagicMethod()
    {
        $code = '<?php class MyClass { public function __construct() {} }';
        $table = $this->collectSymbols($code);

        $methods = $table->getByType(Symbol::TYPE_METHOD);
        $this->assertTrue($methods[0]->getMetadataValue('isMagic', false));
    }

    public function testCollectMultipleClasses()
    {
        $code = '<?php class A {} class B {} class C {}';
        $table = $this->collectSymbols($code);

        $this->assertEquals(3, $table->countByType(Symbol::TYPE_CLASS));
    }

    public function testCollectClassMembers()
    {
        $code = '<?php
        class MyClass {
            const CONST_A = 1;
            public $propA;
            private $propB;
            public function methodA() {}
            private function methodB() {}
        }';
        $table = $this->collectSymbols($code);

        $members = $table->getClassMembers('MyClass');
        $this->assertCount(5, $members);
    }

    public function testLineNumbers()
    {
        $code = "<?php\nclass MyClass {\n    public function test() {}\n}";
        $table = $this->collectSymbols($code);

        $classes = $table->getClasses();
        $this->assertEquals(2, $classes[0]->getStartLine());
    }
}
