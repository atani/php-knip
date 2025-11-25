<?php
/**
 * ComposerJsonParser Tests
 */

namespace PhpKnip\Tests\Unit\Composer;

use PHPUnit\Framework\TestCase;
use PhpKnip\Composer\ComposerJsonParser;

class ComposerJsonParserTest extends TestCase
{
    public function testParseArray()
    {
        $parser = new ComposerJsonParser();
        $parser->parseArray(array(
            'name' => 'vendor/package',
            'require' => array(
                'php' => '^7.4',
                'symfony/console' => '^5.0',
            ),
        ));

        $this->assertEquals('vendor/package', $parser->getName());
    }

    public function testGetRequire()
    {
        $parser = new ComposerJsonParser();
        $parser->parseArray(array(
            'require' => array(
                'php' => '^7.4',
                'symfony/console' => '^5.0',
                'monolog/monolog' => '^2.0',
            ),
        ));

        $require = $parser->getRequire();

        $this->assertCount(3, $require);
        $this->assertEquals('^7.4', $require['php']);
        $this->assertEquals('^5.0', $require['symfony/console']);
    }

    public function testGetRequireDev()
    {
        $parser = new ComposerJsonParser();
        $parser->parseArray(array(
            'require-dev' => array(
                'phpunit/phpunit' => '^9.0',
                'phpstan/phpstan' => '^1.0',
            ),
        ));

        $requireDev = $parser->getRequireDev();

        $this->assertCount(2, $requireDev);
        $this->assertTrue(isset($requireDev['phpunit/phpunit']));
    }

    public function testGetAllDependencies()
    {
        $parser = new ComposerJsonParser();
        $parser->parseArray(array(
            'require' => array(
                'symfony/console' => '^5.0',
            ),
            'require-dev' => array(
                'phpunit/phpunit' => '^9.0',
            ),
        ));

        $all = $parser->getAllDependencies(true);
        $this->assertCount(2, $all);

        $withoutDev = $parser->getAllDependencies(false);
        $this->assertCount(1, $withoutDev);
    }

    public function testGetPsr4Autoload()
    {
        $parser = new ComposerJsonParser();
        $parser->parseArray(array(
            'autoload' => array(
                'psr-4' => array(
                    'App\\' => 'src/',
                    'App\\Tests\\' => 'tests/',
                ),
            ),
        ));

        $psr4 = $parser->getPsr4Autoload();

        $this->assertCount(2, $psr4);
        $this->assertEquals('src/', $psr4['App\\']);
    }

    public function testGetPsr0Autoload()
    {
        $parser = new ComposerJsonParser();
        $parser->parseArray(array(
            'autoload' => array(
                'psr-0' => array(
                    'Legacy_' => 'lib/',
                ),
            ),
        ));

        $psr0 = $parser->getPsr0Autoload();

        $this->assertCount(1, $psr0);
        $this->assertEquals('lib/', $psr0['Legacy_']);
    }

    public function testGetClassmapAutoload()
    {
        $parser = new ComposerJsonParser();
        $parser->parseArray(array(
            'autoload' => array(
                'classmap' => array(
                    'src/legacy/',
                    'lib/',
                ),
            ),
        ));

        $classmap = $parser->getClassmapAutoload();

        $this->assertCount(2, $classmap);
        $this->assertContains('src/legacy/', $classmap);
    }

    public function testHasDependency()
    {
        $parser = new ComposerJsonParser();
        $parser->parseArray(array(
            'require' => array(
                'symfony/console' => '^5.0',
            ),
            'require-dev' => array(
                'phpunit/phpunit' => '^9.0',
            ),
        ));

        $this->assertTrue($parser->hasDependency('symfony/console'));
        $this->assertTrue($parser->hasDependency('phpunit/phpunit'));
        $this->assertFalse($parser->hasDependency('unknown/package'));
    }

    public function testHasRequire()
    {
        $parser = new ComposerJsonParser();
        $parser->parseArray(array(
            'require' => array(
                'symfony/console' => '^5.0',
            ),
            'require-dev' => array(
                'phpunit/phpunit' => '^9.0',
            ),
        ));

        $this->assertTrue($parser->hasRequire('symfony/console'));
        $this->assertFalse($parser->hasRequire('phpunit/phpunit'));
    }

    public function testHasRequireDev()
    {
        $parser = new ComposerJsonParser();
        $parser->parseArray(array(
            'require' => array(
                'symfony/console' => '^5.0',
            ),
            'require-dev' => array(
                'phpunit/phpunit' => '^9.0',
            ),
        ));

        $this->assertFalse($parser->hasRequireDev('symfony/console'));
        $this->assertTrue($parser->hasRequireDev('phpunit/phpunit'));
    }

    public function testGetVersionConstraint()
    {
        $parser = new ComposerJsonParser();
        $parser->parseArray(array(
            'require' => array(
                'symfony/console' => '^5.0',
            ),
            'require-dev' => array(
                'phpunit/phpunit' => '^9.0',
            ),
        ));

        $this->assertEquals('^5.0', $parser->getVersionConstraint('symfony/console'));
        $this->assertEquals('^9.0', $parser->getVersionConstraint('phpunit/phpunit'));
        $this->assertNull($parser->getVersionConstraint('unknown/package'));
    }

    public function testGetPhpVersionRequirement()
    {
        $parser = new ComposerJsonParser();
        $parser->parseArray(array(
            'require' => array(
                'php' => '^7.4 || ^8.0',
            ),
        ));

        $this->assertEquals('^7.4 || ^8.0', $parser->getPhpVersionRequirement());
    }

    public function testGetExtensionRequirements()
    {
        $parser = new ComposerJsonParser();
        $parser->parseArray(array(
            'require' => array(
                'php' => '^7.4',
                'ext-json' => '*',
                'ext-mbstring' => '*',
                'symfony/console' => '^5.0',
            ),
        ));

        $extensions = $parser->getExtensionRequirements();

        $this->assertCount(2, $extensions);
        $this->assertTrue(isset($extensions['ext-json']));
        $this->assertTrue(isset($extensions['ext-mbstring']));
    }

    public function testGetPackageDependencies()
    {
        $parser = new ComposerJsonParser();
        $parser->parseArray(array(
            'require' => array(
                'php' => '^7.4',
                'ext-json' => '*',
                'symfony/console' => '^5.0',
            ),
            'require-dev' => array(
                'phpunit/phpunit' => '^9.0',
            ),
        ));

        $packages = $parser->getPackageDependencies(true);

        $this->assertCount(2, $packages);
        $this->assertTrue(isset($packages['symfony/console']));
        $this->assertTrue(isset($packages['phpunit/phpunit']));
        $this->assertFalse(isset($packages['php']));
        $this->assertFalse(isset($packages['ext-json']));
    }

    public function testGetScripts()
    {
        $parser = new ComposerJsonParser();
        $parser->parseArray(array(
            'scripts' => array(
                'test' => 'phpunit',
                'lint' => 'phpcs',
            ),
        ));

        $scripts = $parser->getScripts();

        $this->assertCount(2, $scripts);
        $this->assertEquals('phpunit', $scripts['test']);
    }

    public function testEmptyDataReturnsEmptyArrays()
    {
        $parser = new ComposerJsonParser();
        $parser->parseArray(array());

        $this->assertNull($parser->getName());
        $this->assertEmpty($parser->getRequire());
        $this->assertEmpty($parser->getRequireDev());
        $this->assertEmpty($parser->getPsr4Autoload());
        $this->assertEmpty($parser->getClassmapAutoload());
    }
}
