<?php
/**
 * AstBuilder Test
 */

namespace PhpKnip\Tests\Unit\Parser;

use PhpKnip\Tests\TestCase;
use PhpKnip\Parser\AstBuilder;

class AstBuilderTest extends TestCase
{
    /**
     * @var AstBuilder
     */
    private $builder;

    protected function setUp(): void
    {
        $this->builder = new AstBuilder();
    }

    public function testBuildFromStringSimpleClass()
    {
        $code = <<<'PHP'
<?php
class TestClass
{
    public function testMethod()
    {
        return 'test';
    }
}
PHP;

        $ast = $this->builder->buildFromString($code);

        $this->assertNotNull($ast);
        $this->assertFalse($this->builder->hasErrors());
        $this->assertIsArray($ast);
        $this->assertGreaterThan(0, count($ast));
    }

    public function testBuildFromStringWithNamespace()
    {
        $code = <<<'PHP'
<?php
namespace App\Models;

use App\Interfaces\UserInterface;

class User implements UserInterface
{
    private $name;

    public function getName()
    {
        return $this->name;
    }
}
PHP;

        $ast = $this->builder->buildFromString($code);

        $this->assertNotNull($ast);
        $this->assertFalse($this->builder->hasErrors());
    }

    public function testBuildFromStringWithSyntaxError()
    {
        $code = "<?php class { }"; // Invalid syntax

        $ast = $this->builder->buildFromString($code);

        $this->assertNull($ast);
        $this->assertTrue($this->builder->hasErrors());

        $errors = $this->builder->getErrors();
        $this->assertGreaterThan(0, count($errors));
    }

    public function testBuildFromFileNotFound()
    {
        $ast = $this->builder->buildFromFile('/non/existent/file.php');

        $this->assertNull($ast);
        $this->assertTrue($this->builder->hasErrors());
    }

    public function testBuildFromFileSimple()
    {
        $fixtureFile = __DIR__ . '/../../Fixtures/utf8/simple_class.php';

        // Skip if fixture doesn't exist
        if (!file_exists($fixtureFile)) {
            $this->markTestSkipped('Fixture file not found');
        }

        $ast = $this->builder->buildFromFile($fixtureFile);

        $this->assertNotNull($ast);
        $this->assertFalse($this->builder->hasErrors());
    }

    public function testBuildFromFileWithJapaneseIdentifiers()
    {
        $fixtureFile = __DIR__ . '/../../Fixtures/utf8/japanese_identifiers.php';

        // Skip if fixture doesn't exist
        if (!file_exists($fixtureFile)) {
            $this->markTestSkipped('Fixture file not found');
        }

        $ast = $this->builder->buildFromFile($fixtureFile);

        $this->assertNotNull($ast);
        $this->assertFalse($this->builder->hasErrors());
    }

    public function testBuildFromFiles()
    {
        $code1 = "<?php class A {}";
        $code2 = "<?php class B {}";

        // Create temporary files
        $file1 = tempnam(sys_get_temp_dir(), 'php_knip_test_');
        $file2 = tempnam(sys_get_temp_dir(), 'php_knip_test_');

        file_put_contents($file1, $code1);
        file_put_contents($file2, $code2);

        try {
            $results = $this->builder->buildFromFiles(array($file1, $file2));

            $this->assertCount(2, $results);
            $this->assertNotNull($results[$file1]);
            $this->assertNotNull($results[$file2]);
        } finally {
            unlink($file1);
            unlink($file2);
        }
    }

    public function testClearErrors()
    {
        // Generate an error
        $this->builder->buildFromString("<?php class { }");
        $this->assertTrue($this->builder->hasErrors());

        $this->builder->clearErrors();
        $this->assertFalse($this->builder->hasErrors());
    }

    public function testSetPhpVersion()
    {
        $this->builder->setPhpVersion('7.4');

        $code = "<?php class Test { public function test(): ?string { return null; } }";
        $ast = $this->builder->buildFromString($code);

        $this->assertNotNull($ast);
    }

    public function testSetDefaultEncoding()
    {
        $this->builder->setDefaultEncoding('EUC-JP');

        // Should still parse UTF-8 content correctly
        $code = "<?php class Test {}";
        $ast = $this->builder->buildFromString($code);

        $this->assertNotNull($ast);
    }

    public function testDetectEncoding()
    {
        $content = "\xEF\xBB\xBF<?php echo 'test';";
        $result = $this->builder->detectEncoding($content);

        $this->assertEquals('UTF-8', $result['encoding']);
        $this->assertEquals('bom', $result['method']);
    }

    public function testBuildFromStringWithUtf8Bom()
    {
        $code = "\xEF\xBB\xBF<?php class Test {}";
        $ast = $this->builder->buildFromString($code);

        $this->assertNotNull($ast);
        $this->assertFalse($this->builder->hasErrors());
    }

    /**
     * @requires extension mbstring
     */
    public function testBuildFromStringWithEucJp()
    {
        // Create EUC-JP content
        $utf8Code = "<?php class Test { public function test() { return '日本語'; } }";
        $eucjpCode = mb_convert_encoding($utf8Code, 'EUC-JP', 'UTF-8');

        $builder = new AstBuilder('auto', 'EUC-JP');
        $ast = $builder->buildFromString($eucjpCode);

        $this->assertNotNull($ast);
        $this->assertFalse($builder->hasErrors());
    }

    public function testBuildPHP5Syntax()
    {
        $code = <<<'PHP'
<?php
class OldStyle
{
    var $property;

    function OldStyle()
    {
        $this->property = 'value';
    }
}
PHP;

        $builder = new AstBuilder('5.6');
        $ast = $builder->buildFromString($code);

        $this->assertNotNull($ast);
    }

    public function testBuildPHP7Syntax()
    {
        $code = <<<'PHP'
<?php
class ModernClass
{
    public function test(?string $param): ?int
    {
        return $param ?? null;
    }
}
PHP;

        $builder = new AstBuilder('7.4');
        $ast = $builder->buildFromString($code);

        $this->assertNotNull($ast);
    }
}
