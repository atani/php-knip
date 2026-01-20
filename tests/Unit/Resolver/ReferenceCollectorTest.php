<?php
/**
 * ReferenceCollector Test
 */

namespace PhpKnip\Tests\Unit\Resolver;

use PhpKnip\Tests\TestCase;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpKnip\Resolver\Reference;
use PhpKnip\Resolver\ReferenceCollector;

class ReferenceCollectorTest extends TestCase
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
     * Parse code and collect references
     *
     * @param string $code PHP code
     * @return array<Reference>
     */
    private function collectReferences($code)
    {
        $ast = $this->parser->parse($code);

        $collector = new ReferenceCollector();
        $collector->setCurrentFile('test.php');

        $traverser = new NodeTraverser();
        $traverser->addVisitor($collector);
        $traverser->traverse($ast);

        return $collector->getReferences();
    }

    /**
     * Get references of a specific type
     *
     * @param array $refs References
     * @param string $type Reference type
     * @return array
     */
    private function filterByType(array $refs, $type)
    {
        return array_values(array_filter($refs, function ($ref) use ($type) {
            return $ref->getType() === $type;
        }));
    }

    public function testCollectNewExpression()
    {
        $code = '<?php $obj = new MyClass();';
        $refs = $this->collectReferences($code);

        $newRefs = $this->filterByType($refs, Reference::TYPE_NEW);
        $this->assertCount(1, $newRefs);
        $this->assertEquals('MyClass', $newRefs[0]->getSymbolName());
    }

    public function testCollectNewWithNamespace()
    {
        $code = '<?php use App\\Models\\User; $user = new User();';
        $refs = $this->collectReferences($code);

        $newRefs = $this->filterByType($refs, Reference::TYPE_NEW);
        $this->assertCount(1, $newRefs);
        $this->assertEquals('App\\Models\\User', $newRefs[0]->getSymbolName());
    }

    public function testCollectExtends()
    {
        $code = '<?php class Child extends ParentClass {}';
        $refs = $this->collectReferences($code);

        $extendsRefs = $this->filterByType($refs, Reference::TYPE_EXTENDS);
        $this->assertCount(1, $extendsRefs);
        $this->assertEquals('ParentClass', $extendsRefs[0]->getSymbolName());
    }

    public function testCollectImplements()
    {
        $code = '<?php class MyClass implements InterfaceA, InterfaceB {}';
        $refs = $this->collectReferences($code);

        $implementsRefs = $this->filterByType($refs, Reference::TYPE_IMPLEMENTS);
        $this->assertCount(2, $implementsRefs);
    }

    public function testCollectUseTrait()
    {
        $code = '<?php class MyClass { use TraitA, TraitB; }';
        $refs = $this->collectReferences($code);

        $traitRefs = $this->filterByType($refs, Reference::TYPE_USE_TRAIT);
        $this->assertCount(2, $traitRefs);
    }

    public function testCollectUseImport()
    {
        $code = '<?php use App\\Service\\UserService;';
        $refs = $this->collectReferences($code);

        $useRefs = $this->filterByType($refs, Reference::TYPE_USE_IMPORT);
        $this->assertCount(1, $useRefs);
        $this->assertEquals('App\\Service\\UserService', $useRefs[0]->getSymbolName());
    }

    public function testCollectStaticCall()
    {
        $code = '<?php MyClass::staticMethod();';
        $refs = $this->collectReferences($code);

        $staticRefs = $this->filterByType($refs, Reference::TYPE_STATIC_CALL);
        $this->assertCount(1, $staticRefs);
        $this->assertEquals('staticMethod', $staticRefs[0]->getSymbolName());
        $this->assertEquals('MyClass', $staticRefs[0]->getSymbolParent());
    }

    public function testCollectFunctionCall()
    {
        $code = '<?php myFunction();';
        $refs = $this->collectReferences($code);

        $funcRefs = $this->filterByType($refs, Reference::TYPE_FUNCTION_CALL);
        $this->assertCount(1, $funcRefs);
        $this->assertEquals('myFunction', $funcRefs[0]->getSymbolName());
    }

    public function testCollectMethodCall()
    {
        $code = '<?php $obj->myMethod();';
        $refs = $this->collectReferences($code);

        $methodRefs = $this->filterByType($refs, Reference::TYPE_METHOD_CALL);
        $this->assertCount(1, $methodRefs);
        $this->assertEquals('myMethod', $methodRefs[0]->getSymbolName());
    }

    public function testCollectInstanceof()
    {
        $code = '<?php if ($obj instanceof MyClass) {}';
        $refs = $this->collectReferences($code);

        $instanceofRefs = $this->filterByType($refs, Reference::TYPE_INSTANCEOF);
        $this->assertCount(1, $instanceofRefs);
        $this->assertEquals('MyClass', $instanceofRefs[0]->getSymbolName());
    }

    public function testCollectTypeHint()
    {
        $code = '<?php function test(MyClass $param) {}';
        $refs = $this->collectReferences($code);

        $typeRefs = $this->filterByType($refs, Reference::TYPE_TYPE_HINT);
        $this->assertCount(1, $typeRefs);
        $this->assertEquals('MyClass', $typeRefs[0]->getSymbolName());
    }

    public function testCollectReturnType()
    {
        $code = '<?php function test(): MyClass {}';
        $refs = $this->collectReferences($code);

        $returnRefs = $this->filterByType($refs, Reference::TYPE_RETURN_TYPE);
        $this->assertCount(1, $returnRefs);
        $this->assertEquals('MyClass', $returnRefs[0]->getSymbolName());
    }

    public function testCollectCatch()
    {
        $code = '<?php try {} catch (MyException $e) {}';
        $refs = $this->collectReferences($code);

        $catchRefs = $this->filterByType($refs, Reference::TYPE_CATCH);
        $this->assertCount(1, $catchRefs);
        $this->assertEquals('MyException', $catchRefs[0]->getSymbolName());
    }

    public function testCollectClassConstant()
    {
        $code = '<?php echo MyClass::MY_CONST;';
        $refs = $this->collectReferences($code);

        $constRefs = $this->filterByType($refs, Reference::TYPE_CONSTANT);
        $this->assertCount(1, $constRefs);
        $this->assertEquals('MY_CONST', $constRefs[0]->getSymbolName());
        $this->assertEquals('MyClass', $constRefs[0]->getSymbolParent());
    }

    public function testCollectClassString()
    {
        $code = '<?php $class = MyClass::class;';
        $refs = $this->collectReferences($code);

        $classStringRefs = $this->filterByType($refs, Reference::TYPE_CLASS_STRING);
        $this->assertCount(1, $classStringRefs);
        $this->assertEquals('MyClass', $classStringRefs[0]->getSymbolName());
    }

    public function testCollectDynamicNew()
    {
        $code = '<?php $obj = new $className();';
        $refs = $this->collectReferences($code);

        $newRefs = $this->filterByType($refs, Reference::TYPE_NEW);
        $this->assertCount(1, $newRefs);
        $this->assertTrue($newRefs[0]->isDynamic());
    }

    public function testCollectDynamicMethodCall()
    {
        $code = '<?php $obj->$methodName();';
        $refs = $this->collectReferences($code);

        $methodRefs = $this->filterByType($refs, Reference::TYPE_METHOD_CALL);
        $this->assertCount(1, $methodRefs);
        $this->assertTrue($methodRefs[0]->isDynamic());
    }

    public function testSkipBuiltinTypes()
    {
        $code = '<?php function test(int $a, string $b, array $c): bool {}';
        $refs = $this->collectReferences($code);

        $typeRefs = $this->filterByType($refs, Reference::TYPE_TYPE_HINT);
        $this->assertCount(0, $typeRefs);

        $returnRefs = $this->filterByType($refs, Reference::TYPE_RETURN_TYPE);
        $this->assertCount(0, $returnRefs);
    }

    public function testUseAliasResolution()
    {
        $code = '<?php
        use App\\Models\\User as UserModel;
        $user = new UserModel();
        ';
        $refs = $this->collectReferences($code);

        $newRefs = $this->filterByType($refs, Reference::TYPE_NEW);
        $this->assertCount(1, $newRefs);
        $this->assertEquals('App\\Models\\User', $newRefs[0]->getSymbolName());
    }

    public function testContextTracking()
    {
        $code = '<?php
        class MyClass {
            public function myMethod() {
                $obj = new OtherClass();
            }
        }
        ';
        $refs = $this->collectReferences($code);

        $newRefs = $this->filterByType($refs, Reference::TYPE_NEW);
        $this->assertCount(1, $newRefs);
        $this->assertEquals('MyClass::myMethod', $newRefs[0]->getContext());
    }

    public function testNullableTypeHint()
    {
        $code = '<?php function test(?MyClass $param): ?OtherClass {}';
        $refs = $this->collectReferences($code);

        $typeRefs = $this->filterByType($refs, Reference::TYPE_TYPE_HINT);
        $this->assertCount(1, $typeRefs);
        $this->assertEquals('MyClass', $typeRefs[0]->getSymbolName());

        $returnRefs = $this->filterByType($refs, Reference::TYPE_RETURN_TYPE);
        $this->assertCount(1, $returnRefs);
        $this->assertEquals('OtherClass', $returnRefs[0]->getSymbolName());
    }

    public function testReferenceLocation()
    {
        $code = "<?php\n\$obj = new MyClass();";
        $refs = $this->collectReferences($code);

        $newRefs = $this->filterByType($refs, Reference::TYPE_NEW);
        $this->assertEquals('test.php', $newRefs[0]->getFilePath());
        $this->assertEquals(2, $newRefs[0]->getLine());
    }
}
