<?php
/**
 * DependencyAnalyzer Tests
 */

namespace PhpKnip\Tests\Unit\Analyzer;

use PHPUnit\Framework\TestCase;
use PhpKnip\Analyzer\DependencyAnalyzer;
use PhpKnip\Analyzer\AnalysisContext;
use PhpKnip\Analyzer\Issue;
use PhpKnip\Composer\ComposerJsonParser;
use PhpKnip\Composer\ComposerLockParser;
use PhpKnip\Composer\AutoloadResolver;
use PhpKnip\Resolver\SymbolTable;
use PhpKnip\Resolver\Reference;

class DependencyAnalyzerTest extends TestCase
{
    /**
     * @var DependencyAnalyzer
     */
    private $analyzer;

    protected function setUp()
    {
        $this->analyzer = new DependencyAnalyzer();
    }

    public function testGetName()
    {
        $this->assertEquals('dependency-analyzer', $this->analyzer->getName());
    }

    public function testUnusedDependencyIsDetected()
    {
        $composerJson = new ComposerJsonParser();
        $composerJson->parseArray(array(
            'name' => 'test/project',
            'require' => array(
                'monolog/monolog' => '^2.0',
            ),
            'autoload' => array(
                'psr-4' => array('App\\' => 'src/'),
            ),
        ));

        $composerLock = new ComposerLockParser();
        $composerLock->parseArray(array(
            'packages' => array(
                array(
                    'name' => 'monolog/monolog',
                    'version' => 'v2.3.0',
                    'autoload' => array(
                        'psr-4' => array('Monolog\\' => 'src/'),
                    ),
                ),
            ),
        ));

        $this->analyzer->setComposerJson($composerJson);
        $this->analyzer->setComposerLock($composerLock);

        // No references to Monolog
        $context = new AnalysisContext(new SymbolTable(), array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(1, $issues);
        $this->assertEquals(Issue::TYPE_UNUSED_DEPENDENCY, $issues[0]->getType());
        $this->assertEquals('monolog/monolog', $issues[0]->getSymbolName());
    }

    public function testUsedDependencyIsNotFlagged()
    {
        $composerJson = new ComposerJsonParser();
        $composerJson->parseArray(array(
            'name' => 'test/project',
            'require' => array(
                'monolog/monolog' => '^2.0',
            ),
            'autoload' => array(
                'psr-4' => array('App\\' => 'src/'),
            ),
        ));

        $composerLock = new ComposerLockParser();
        $composerLock->parseArray(array(
            'packages' => array(
                array(
                    'name' => 'monolog/monolog',
                    'version' => 'v2.3.0',
                    'autoload' => array(
                        'psr-4' => array('Monolog\\' => 'src/'),
                    ),
                ),
            ),
        ));

        $this->analyzer->setComposerJson($composerJson);
        $this->analyzer->setComposerLock($composerLock);

        // Reference to Monolog\Logger
        $references = array(
            Reference::createNew('Monolog\\Logger'),
        );

        $context = new AnalysisContext(new SymbolTable(), $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testPhpRequirementIsNotFlagged()
    {
        $composerJson = new ComposerJsonParser();
        $composerJson->parseArray(array(
            'name' => 'test/project',
            'require' => array(
                'php' => '^7.4',
            ),
        ));

        $this->analyzer->setComposerJson($composerJson);

        $context = new AnalysisContext(new SymbolTable(), array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testExtensionRequirementIsNotFlagged()
    {
        $composerJson = new ComposerJsonParser();
        $composerJson->parseArray(array(
            'name' => 'test/project',
            'require' => array(
                'ext-json' => '*',
                'ext-mbstring' => '*',
            ),
        ));

        $this->analyzer->setComposerJson($composerJson);

        $context = new AnalysisContext(new SymbolTable(), array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testDevDependencyIsFlaggedWithInfoSeverity()
    {
        $composerJson = new ComposerJsonParser();
        $composerJson->parseArray(array(
            'name' => 'test/project',
            'require-dev' => array(
                'phpunit/phpunit' => '^9.0',
            ),
            'autoload' => array(
                'psr-4' => array('App\\' => 'src/'),
            ),
        ));

        $composerLock = new ComposerLockParser();
        $composerLock->parseArray(array(
            'packages-dev' => array(
                array(
                    'name' => 'phpunit/phpunit',
                    'version' => 'v9.5.0',
                    'autoload' => array(
                        'psr-4' => array('PHPUnit\\' => 'src/'),
                    ),
                ),
            ),
        ));

        $this->analyzer->setComposerJson($composerJson);
        $this->analyzer->setComposerLock($composerLock);

        // No references to PHPUnit
        $context = new AnalysisContext(new SymbolTable(), array());

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(1, $issues);
        $this->assertEquals(Issue::SEVERITY_INFO, $issues[0]->getSeverity());
        $this->assertTrue($issues[0]->getMetadataValue('isDev'));
    }

    public function testIgnoredDependencyIsNotFlagged()
    {
        $composerJson = new ComposerJsonParser();
        $composerJson->parseArray(array(
            'name' => 'test/project',
            'require' => array(
                'monolog/monolog' => '^2.0',
            ),
        ));

        $composerLock = new ComposerLockParser();
        $composerLock->parseArray(array(
            'packages' => array(
                array(
                    'name' => 'monolog/monolog',
                    'version' => 'v2.3.0',
                    'autoload' => array(
                        'psr-4' => array('Monolog\\' => 'src/'),
                    ),
                ),
            ),
        ));

        $this->analyzer->setComposerJson($composerJson);
        $this->analyzer->setComposerLock($composerLock);

        $config = array(
            'ignore' => array(
                'dependencies' => array('monolog/*'),
            ),
        );

        $context = new AnalysisContext(new SymbolTable(), array(), $config);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testMultipleUnusedDependencies()
    {
        $composerJson = new ComposerJsonParser();
        $composerJson->parseArray(array(
            'name' => 'test/project',
            'require' => array(
                'monolog/monolog' => '^2.0',
                'guzzlehttp/guzzle' => '^7.0',
                'symfony/console' => '^5.0',
            ),
            'autoload' => array(
                'psr-4' => array('App\\' => 'src/'),
            ),
        ));

        $composerLock = new ComposerLockParser();
        $composerLock->parseArray(array(
            'packages' => array(
                array(
                    'name' => 'monolog/monolog',
                    'version' => 'v2.3.0',
                    'autoload' => array(
                        'psr-4' => array('Monolog\\' => 'src/'),
                    ),
                ),
                array(
                    'name' => 'guzzlehttp/guzzle',
                    'version' => 'v7.0.0',
                    'autoload' => array(
                        'psr-4' => array('GuzzleHttp\\' => 'src/'),
                    ),
                ),
                array(
                    'name' => 'symfony/console',
                    'version' => 'v5.4.0',
                    'autoload' => array(
                        'psr-4' => array('Symfony\\Component\\Console\\' => ''),
                    ),
                ),
            ),
        ));

        $this->analyzer->setComposerJson($composerJson);
        $this->analyzer->setComposerLock($composerLock);

        // Only use Symfony Console
        $references = array(
            Reference::createNew('Symfony\\Component\\Console\\Application'),
        );

        $context = new AnalysisContext(new SymbolTable(), $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(2, $issues);

        $packageNames = array_map(function ($issue) {
            return $issue->getSymbolName();
        }, $issues);

        $this->assertContains('monolog/monolog', $packageNames);
        $this->assertContains('guzzlehttp/guzzle', $packageNames);
        $this->assertNotContains('symfony/console', $packageNames);
    }

    public function testTypeHintReferenceMarksPackageAsUsed()
    {
        $composerJson = new ComposerJsonParser();
        $composerJson->parseArray(array(
            'name' => 'test/project',
            'require' => array(
                'psr/log' => '^1.0',
            ),
            'autoload' => array(
                'psr-4' => array('App\\' => 'src/'),
            ),
        ));

        $composerLock = new ComposerLockParser();
        $composerLock->parseArray(array(
            'packages' => array(
                array(
                    'name' => 'psr/log',
                    'version' => 'v1.1.0',
                    'autoload' => array(
                        'psr-4' => array('Psr\\Log\\' => 'Psr/Log/'),
                    ),
                ),
            ),
        ));

        $this->analyzer->setComposerJson($composerJson);
        $this->analyzer->setComposerLock($composerLock);

        // Type hint reference
        $references = array(
            Reference::createTypeHint('Psr\\Log\\LoggerInterface'),
        );

        $context = new AnalysisContext(new SymbolTable(), $references);

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testGetDependencyReport()
    {
        $composerJson = new ComposerJsonParser();
        $composerJson->parseArray(array(
            'name' => 'test/project',
            'require' => array(
                'monolog/monolog' => '^2.0',
                'symfony/console' => '^5.0',
            ),
            'autoload' => array(
                'psr-4' => array('App\\' => 'src/'),
            ),
        ));

        $composerLock = new ComposerLockParser();
        $composerLock->parseArray(array(
            'packages' => array(
                array(
                    'name' => 'monolog/monolog',
                    'version' => 'v2.3.0',
                    'autoload' => array(
                        'psr-4' => array('Monolog\\' => 'src/'),
                    ),
                ),
                array(
                    'name' => 'symfony/console',
                    'version' => 'v5.4.0',
                    'autoload' => array(
                        'psr-4' => array('Symfony\\Component\\Console\\' => ''),
                    ),
                ),
            ),
        ));

        $this->analyzer->setComposerJson($composerJson);
        $this->analyzer->setComposerLock($composerLock);

        $references = array(
            Reference::createNew('Symfony\\Component\\Console\\Application'),
        );

        $context = new AnalysisContext(new SymbolTable(), $references);

        $report = $this->analyzer->getDependencyReport($context);

        $this->assertArrayHasKey('declared', $report);
        $this->assertArrayHasKey('used', $report);
        $this->assertArrayHasKey('unused', $report);
        $this->assertArrayHasKey('missing', $report);

        $this->assertContains('monolog/monolog', $report['unused']);
        $this->assertContains('symfony/console', $report['used']);
    }

    public function testWithoutComposerJsonReturnsEmpty()
    {
        $context = new AnalysisContext(new SymbolTable(), array());

        $issues = $this->analyzer->analyze($context);

        $this->assertEmpty($issues);
    }
}
