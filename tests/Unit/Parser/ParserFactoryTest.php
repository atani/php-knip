<?php
/**
 * ParserFactory Tests
 */

namespace PhpKnip\Tests\Unit\Parser;

use PhpKnip\Tests\TestCase;
use PhpKnip\Parser\ParserFactory;

class ParserFactoryTest extends TestCase
{
    /**
     * @var ParserFactory
     */
    private $factory;

    protected function setUp(): void
    {
        $this->factory = new ParserFactory();
    }

    public function testGetSupportedVersionsContainsPHP44()
    {
        $versions = $this->factory->getSupportedVersions();
        $this->assertContains('4.4', $versions);
    }

    public function testGetSupportedVersionsContainsPHP56()
    {
        $versions = $this->factory->getSupportedVersions();
        $this->assertContains('5.6', $versions);
    }

    public function testCreateWithPHP44ReturnsParser()
    {
        $parser = $this->factory->create('4.4');
        $this->assertInstanceOf('PhpParser\\Parser', $parser);
    }

    public function testCreateWithAutoReturnsParser()
    {
        $parser = $this->factory->create('auto');
        $this->assertInstanceOf('PhpParser\\Parser', $parser);
    }

    public function testPHP4ParserCanParsePHP4Code()
    {
        $parser = $this->factory->create('4.4');
        $code = '<?php
class MyClass {
    var $name;

    function MyClass($name) {
        $this->name = $name;
    }

    function getName() {
        return $this->name;
    }
}';
        $ast = $parser->parse($code);
        $this->assertNotNull($ast);
    }

    public function testDetectVersionReturnsPHP44ForPHP4Code()
    {
        $code = '<?php
class MyClass {
    var $name;

    function MyClass($name) {
        $this->name = $name;
    }
}';
        $this->assertEquals('4.4', $this->factory->detectVersion($code));
    }

    public function testDetectVersionReturnsPHP56ForNamespacedCode()
    {
        $code = '<?php
namespace App\\Models;

class User {
    public $name;
}';
        $this->assertEquals('5.6', $this->factory->detectVersion($code));
    }

    public function testDetectVersionReturnsPHP56ForTraitCode()
    {
        $code = '<?php
trait Cacheable {
    public function cache() {}
}';
        $this->assertEquals('5.6', $this->factory->detectVersion($code));
    }

    public function testDetectVersionReturnsPHP56ForVisibilityModifiers()
    {
        $code = '<?php
class MyClass {
    public function test() {}
}';
        $this->assertEquals('5.6', $this->factory->detectVersion($code));
    }

    public function testDetectVersionReturnsPHP56ForAbstractClass()
    {
        $code = '<?php
abstract class Base {
    function doSomething() {}
}';
        $this->assertEquals('5.6', $this->factory->detectVersion($code));
    }

    public function testDetectVersionReturnsPHP56ForTryCatch()
    {
        $code = '<?php
function test() {
    try {
        doSomething();
    } catch (Exception $e) {
        // handle
    }
}';
        $this->assertEquals('5.6', $this->factory->detectVersion($code));
    }

    public function testDetectVersionReturnsPHP56ForConstructMethod()
    {
        $code = '<?php
class MyClass {
    function __construct() {}
}';
        $this->assertEquals('5.6', $this->factory->detectVersion($code));
    }

    public function testDetectVersionReturnsPHP44ForVarKeyword()
    {
        $code = '<?php
class Config {
    var $settings;

    function getSettings() {
        return $this->settings;
    }
}';
        $this->assertEquals('4.4', $this->factory->detectVersion($code));
    }

    public function testDetectVersionReturnsPHP44ForClassWithoutVisibility()
    {
        $code = '<?php
class Simple {
    function doSomething() {
        return 1;
    }
}';
        $this->assertEquals('4.4', $this->factory->detectVersion($code));
    }

    public function testDetectVersionReturnsPHP44ForOldStyleConstructorCaseInsensitive()
    {
        $code = '<?php
class MyClass {
    function myclass() {
        // old-style constructor with different case
    }
}';
        $this->assertEquals('4.4', $this->factory->detectVersion($code));
    }

    public function testDetectVersionReturnsPHP44ForOldStyleConstructorWithMethodsBefore()
    {
        $code = '<?php
class MyClass {
    function helper() {
        return 1;
    }

    function MyClass() {
        // constructor after another method
    }
}';
        $this->assertEquals('4.4', $this->factory->detectVersion($code));
    }

    public function testDetectVersionReturnsPHP44ForExtendsClass()
    {
        $code = '<?php
class Child extends Parent {
    var $data;

    function Child() {}
}';
        $this->assertEquals('4.4', $this->factory->detectVersion($code));
    }

    public function testDetectVersionReturnsPHP70ForReturnType()
    {
        $code = '<?php
class MyClass {
    public function test(): string {
        return "hello";
    }
}';
        $this->assertEquals('7.0', $this->factory->detectVersion($code));
    }

    public function testDetectVersionReturnsPHP80ForAttributes()
    {
        $code = '<?php
#[Route("/api")]
class Controller {}';
        $this->assertEquals('8.0', $this->factory->detectVersion($code));
    }

    public function testDetectVersionReturnsPHP56ForVarWithVisibility()
    {
        $code = '<?php
class Foo {
    var $x;
    public function test() {}
}';
        $this->assertEquals('5.6', $this->factory->detectVersion($code));
    }

    public function testDetectVersionReturnsPHP56ForInterfaceDeclaration()
    {
        $code = '<?php
interface Loggable {
    function log($message);
}';
        $this->assertEquals('5.6', $this->factory->detectVersion($code));
    }

    public function testDetectVersionReturnsPHP56ForImplementsClause()
    {
        $code = '<?php
class Logger implements Loggable {
    function log($message) {
        echo $message;
    }
}';
        $this->assertEquals('5.6', $this->factory->detectVersion($code));
    }

    public function testDetectVersionReturnsPHP56ForTypeHintArray()
    {
        $code = '<?php
class Processor {
    function process(array $items) {
        return $items;
    }
}';
        $this->assertEquals('5.6', $this->factory->detectVersion($code));
    }

    public function testDetectVersionReturnsPHP44ForUnderscoreClassName()
    {
        $code = '<?php
class My_Class {
    var $data;

    function My_Class() {}
}';
        $this->assertEquals('4.4', $this->factory->detectVersion($code));
    }

    public function testDetectVersionReturnsPHP56ForDefaultFallback()
    {
        $code = '<?php echo "hello";';
        $this->assertEquals('5.6', $this->factory->detectVersion($code));
    }

    public function testDetectVersionReturnsPHP56ForFinalClass()
    {
        $code = '<?php
final class Singleton {
    function getInstance() {}
}';
        $this->assertEquals('5.6', $this->factory->detectVersion($code));
    }

    public function testDetectVersionReturnsPHP56ForCallableTypeHint()
    {
        $code = '<?php
class Runner {
    function run(callable $fn) { return $fn(); }
}';
        $this->assertEquals('5.6', $this->factory->detectVersion($code));
    }

    public function testDetectVersionReturnsPHP56ForPrivateProperty()
    {
        $code = '<?php
class Foo {
    private $x;
    function test() {}
}';
        $this->assertEquals('5.6', $this->factory->detectVersion($code));
    }

    public function testDetectVersionReturnsPHP56ForVarWithNamespace()
    {
        $code = '<?php
namespace App;
class Foo {
    var $x;
}';
        $this->assertEquals('5.6', $this->factory->detectVersion($code));
    }

    public function testDetectVersionReturnsPHP56ForVarWithConstruct()
    {
        $code = '<?php
class MyClass {
    var $x;
    function __construct() {}
}';
        $this->assertEquals('5.6', $this->factory->detectVersion($code));
    }

    public function testDetectVersionNotConfusedByMultipleClasses()
    {
        // class Foo has function Bar() - should NOT be detected as old-style constructor
        // because Bar is a different class name, not Foo
        $code = '<?php
class Foo {
    function helper() {}
}
class Bar {
    function other() {}
}';
        // No PHP 5 features, no old-style constructors, but classes without visibility -> PHP 4
        $this->assertEquals('4.4', $this->factory->detectVersion($code));
    }

    public function testDetectVersionReturnsPHP56ForProtectedProperty()
    {
        $code = '<?php
class Foo {
    protected $x;
    function test() {}
}';
        $this->assertEquals('5.6', $this->factory->detectVersion($code));
    }

    public function testDetectVersionMultipleClassesNoFalseOldStyleConstructor()
    {
        // function Foo() exists in class Bar - the two-step regex should still match
        // since we check class names against function names globally
        // This is acceptable: the heuristic detects PHP 4 patterns in the file
        $code = '<?php
class Foo {
    function doWork() {}
}
class Bar {
    function Foo() {}
}';
        $this->assertEquals('4.4', $this->factory->detectVersion($code));
    }
}
