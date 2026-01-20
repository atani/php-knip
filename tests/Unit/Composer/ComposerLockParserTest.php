<?php
/**
 * ComposerLockParser Tests
 */

namespace PhpKnip\Tests\Unit\Composer;

use PhpKnip\Tests\TestCase;
use PhpKnip\Composer\ComposerLockParser;

class ComposerLockParserTest extends TestCase
{
    public function testParseArray()
    {
        $parser = new ComposerLockParser();
        $parser->parseArray(array(
            'packages' => array(
                array('name' => 'symfony/console', 'version' => 'v5.4.0'),
            ),
        ));

        $this->assertTrue($parser->hasPackage('symfony/console'));
    }

    public function testGetPackages()
    {
        $parser = new ComposerLockParser();
        $parser->parseArray(array(
            'packages' => array(
                array('name' => 'symfony/console', 'version' => 'v5.4.0'),
                array('name' => 'monolog/monolog', 'version' => 'v2.3.0'),
            ),
        ));

        $packages = $parser->getPackages();

        $this->assertCount(2, $packages);
    }

    public function testGetDevPackages()
    {
        $parser = new ComposerLockParser();
        $parser->parseArray(array(
            'packages' => array(
                array('name' => 'symfony/console', 'version' => 'v5.4.0'),
            ),
            'packages-dev' => array(
                array('name' => 'phpunit/phpunit', 'version' => 'v9.5.0'),
            ),
        ));

        $devPackages = $parser->getDevPackages();

        $this->assertCount(1, $devPackages);
        $this->assertEquals('phpunit/phpunit', $devPackages[0]['name']);
    }

    public function testGetAllPackages()
    {
        $parser = new ComposerLockParser();
        $parser->parseArray(array(
            'packages' => array(
                array('name' => 'symfony/console', 'version' => 'v5.4.0'),
            ),
            'packages-dev' => array(
                array('name' => 'phpunit/phpunit', 'version' => 'v9.5.0'),
            ),
        ));

        $all = $parser->getAllPackages(true);
        $this->assertCount(2, $all);

        $withoutDev = $parser->getAllPackages(false);
        $this->assertCount(1, $withoutDev);
    }

    public function testGetPackage()
    {
        $parser = new ComposerLockParser();
        $parser->parseArray(array(
            'packages' => array(
                array(
                    'name' => 'symfony/console',
                    'version' => 'v5.4.0',
                    'autoload' => array(
                        'psr-4' => array('Symfony\\Component\\Console\\' => ''),
                    ),
                ),
            ),
        ));

        $package = $parser->getPackage('symfony/console');

        $this->assertNotNull($package);
        $this->assertEquals('v5.4.0', $package['version']);
    }

    public function testHasPackage()
    {
        $parser = new ComposerLockParser();
        $parser->parseArray(array(
            'packages' => array(
                array('name' => 'symfony/console', 'version' => 'v5.4.0'),
            ),
            'packages-dev' => array(
                array('name' => 'phpunit/phpunit', 'version' => 'v9.5.0'),
            ),
        ));

        $this->assertTrue($parser->hasPackage('symfony/console'));
        $this->assertTrue($parser->hasPackage('phpunit/phpunit'));
        $this->assertFalse($parser->hasPackage('unknown/package'));
    }

    public function testIsDevPackage()
    {
        $parser = new ComposerLockParser();
        $parser->parseArray(array(
            'packages' => array(
                array('name' => 'symfony/console', 'version' => 'v5.4.0'),
            ),
            'packages-dev' => array(
                array('name' => 'phpunit/phpunit', 'version' => 'v9.5.0'),
            ),
        ));

        $this->assertFalse($parser->isDevPackage('symfony/console'));
        $this->assertTrue($parser->isDevPackage('phpunit/phpunit'));
    }

    public function testGetPackageVersion()
    {
        $parser = new ComposerLockParser();
        $parser->parseArray(array(
            'packages' => array(
                array('name' => 'symfony/console', 'version' => 'v5.4.0'),
            ),
        ));

        $this->assertEquals('v5.4.0', $parser->getPackageVersion('symfony/console'));
        $this->assertNull($parser->getPackageVersion('unknown/package'));
    }

    public function testGetPackageAutoload()
    {
        $parser = new ComposerLockParser();
        $parser->parseArray(array(
            'packages' => array(
                array(
                    'name' => 'symfony/console',
                    'version' => 'v5.4.0',
                    'autoload' => array(
                        'psr-4' => array('Symfony\\Component\\Console\\' => ''),
                    ),
                ),
            ),
        ));

        $autoload = $parser->getPackageAutoload('symfony/console');

        $this->assertTrue(isset($autoload['psr-4']));
    }

    public function testGetPackagePsr4()
    {
        $parser = new ComposerLockParser();
        $parser->parseArray(array(
            'packages' => array(
                array(
                    'name' => 'symfony/console',
                    'version' => 'v5.4.0',
                    'autoload' => array(
                        'psr-4' => array('Symfony\\Component\\Console\\' => ''),
                    ),
                ),
            ),
        ));

        $psr4 = $parser->getPackagePsr4('symfony/console');

        $this->assertTrue(isset($psr4['Symfony\\Component\\Console\\']));
    }

    public function testGetPackageNamespaces()
    {
        $parser = new ComposerLockParser();
        $parser->parseArray(array(
            'packages' => array(
                array(
                    'name' => 'symfony/console',
                    'version' => 'v5.4.0',
                    'autoload' => array(
                        'psr-4' => array(
                            'Symfony\\Component\\Console\\' => '',
                        ),
                    ),
                ),
            ),
        ));

        $namespaces = $parser->getPackageNamespaces('symfony/console');

        $this->assertContains('Symfony\\Component\\Console', $namespaces);
    }

    public function testBuildNamespaceMap()
    {
        $parser = new ComposerLockParser();
        $parser->parseArray(array(
            'packages' => array(
                array(
                    'name' => 'symfony/console',
                    'version' => 'v5.4.0',
                    'autoload' => array(
                        'psr-4' => array('Symfony\\Component\\Console\\' => ''),
                    ),
                ),
                array(
                    'name' => 'monolog/monolog',
                    'version' => 'v2.3.0',
                    'autoload' => array(
                        'psr-4' => array('Monolog\\' => 'src/'),
                    ),
                ),
            ),
        ));

        $map = $parser->buildNamespaceMap();

        $this->assertEquals('symfony/console', $map['Symfony\\Component\\Console']);
        $this->assertEquals('monolog/monolog', $map['Monolog']);
    }

    public function testFindPackageByNamespace()
    {
        $parser = new ComposerLockParser();
        $parser->parseArray(array(
            'packages' => array(
                array(
                    'name' => 'symfony/console',
                    'version' => 'v5.4.0',
                    'autoload' => array(
                        'psr-4' => array('Symfony\\Component\\Console\\' => ''),
                    ),
                ),
            ),
        ));

        // Exact match
        $this->assertEquals(
            'symfony/console',
            $parser->findPackageByNamespace('Symfony\\Component\\Console')
        );

        // Sub-namespace match
        $this->assertEquals(
            'symfony/console',
            $parser->findPackageByNamespace('Symfony\\Component\\Console\\Command')
        );

        // No match
        $this->assertNull($parser->findPackageByNamespace('Unknown\\Namespace'));
    }

    public function testGetPackageNames()
    {
        $parser = new ComposerLockParser();
        $parser->parseArray(array(
            'packages' => array(
                array('name' => 'symfony/console', 'version' => 'v5.4.0'),
            ),
            'packages-dev' => array(
                array('name' => 'phpunit/phpunit', 'version' => 'v9.5.0'),
            ),
        ));

        $names = $parser->getPackageNames(true);

        $this->assertCount(2, $names);
        $this->assertContains('symfony/console', $names);
        $this->assertContains('phpunit/phpunit', $names);

        $namesWithoutDev = $parser->getPackageNames(false);
        $this->assertCount(1, $namesWithoutDev);
    }

    public function testEmptyDataReturnsEmptyArrays()
    {
        $parser = new ComposerLockParser();
        $parser->parseArray(array());

        $this->assertEmpty($parser->getPackages());
        $this->assertEmpty($parser->getDevPackages());
        $this->assertEmpty($parser->getPackageNames());
    }

    public function testNamespaceMapSortedByLengthDescending()
    {
        $parser = new ComposerLockParser();
        $parser->parseArray(array(
            'packages' => array(
                array(
                    'name' => 'package-a',
                    'version' => '1.0.0',
                    'autoload' => array(
                        'psr-4' => array('App\\' => ''),
                    ),
                ),
                array(
                    'name' => 'package-b',
                    'version' => '1.0.0',
                    'autoload' => array(
                        'psr-4' => array('App\\SubNamespace\\' => ''),
                    ),
                ),
            ),
        ));

        $map = $parser->buildNamespaceMap();
        $keys = array_keys($map);

        // Longer namespace should come first
        $this->assertEquals('App\\SubNamespace', $keys[0]);
        $this->assertEquals('App', $keys[1]);
    }
}
