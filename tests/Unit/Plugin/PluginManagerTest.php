<?php
/**
 * PluginManager Tests
 */

namespace PhpKnip\Tests\Unit\Plugin;

use PhpKnip\Tests\TestCase;
use PhpKnip\Plugin\PluginManager;
use PhpKnip\Plugin\PluginInterface;
use PhpKnip\Plugin\AbstractPlugin;
use PhpKnip\Resolver\SymbolTable;

class PluginManagerTest extends TestCase
{
    /**
     * @var PluginManager
     */
    private $manager;

    protected function setUp(): void
    {
        $this->manager = new PluginManager();
    }

    public function testRegisterPlugin()
    {
        $plugin = $this->createMockPlugin('test-plugin');

        $this->manager->registerPlugin($plugin);

        $this->assertSame($plugin, $this->manager->getPlugin('test-plugin'));
    }

    public function testRegisterMultiplePlugins()
    {
        $plugin1 = $this->createMockPlugin('plugin-1');
        $plugin2 = $this->createMockPlugin('plugin-2');

        $this->manager->registerPlugins(array($plugin1, $plugin2));

        $registered = $this->manager->getRegisteredPlugins();
        $this->assertCount(2, $registered);
        $this->assertSame($plugin1, $registered['plugin-1']);
        $this->assertSame($plugin2, $registered['plugin-2']);
    }

    public function testGetPluginReturnsNullForUnknownPlugin()
    {
        $this->assertNull($this->manager->getPlugin('unknown'));
    }

    public function testActivateApplicablePlugins()
    {
        $applicable = $this->createMockPlugin('applicable', true);
        $notApplicable = $this->createMockPlugin('not-applicable', false);

        $this->manager->registerPlugins(array($applicable, $notApplicable));
        $this->manager->activate('/project', array());

        $active = $this->manager->getActivePlugins();
        $this->assertCount(1, $active);
        $this->assertSame($applicable, $active[0]);
    }

    public function testHasActivePlugins()
    {
        $applicable = $this->createMockPlugin('applicable', true);

        $this->manager->registerPlugin($applicable);
        $this->manager->activate('/project', array());

        $this->assertTrue($this->manager->hasActivePlugins());
    }

    public function testHasNoActivePlugins()
    {
        $notApplicable = $this->createMockPlugin('not-applicable', false);

        $this->manager->registerPlugin($notApplicable);
        $this->manager->activate('/project', array());

        $this->assertFalse($this->manager->hasActivePlugins());
    }

    public function testPluginsAreSortedByPriority()
    {
        $lowPriority = $this->createMockPlugin('low', true, 10);
        $highPriority = $this->createMockPlugin('high', true, 100);
        $mediumPriority = $this->createMockPlugin('medium', true, 50);

        $this->manager->registerPlugins(array($lowPriority, $highPriority, $mediumPriority));
        $this->manager->activate('/project', array());

        $active = $this->manager->getActivePlugins();
        $this->assertEquals('high', $active[0]->getName());
        $this->assertEquals('medium', $active[1]->getName());
        $this->assertEquals('low', $active[2]->getName());
    }

    public function testGetActivePluginNames()
    {
        $plugin1 = $this->createMockPlugin('plugin-a', true);
        $plugin2 = $this->createMockPlugin('plugin-b', true);

        $this->manager->registerPlugins(array($plugin1, $plugin2));
        $this->manager->activate('/project', array());

        $names = $this->manager->getActivePluginNames();
        $this->assertContains('plugin-a', $names);
        $this->assertContains('plugin-b', $names);
    }

    public function testAggregateIgnorePatterns()
    {
        $plugin1 = $this->createMockPluginWithPatterns('p1', array('pattern1', 'pattern2'));
        $plugin2 = $this->createMockPluginWithPatterns('p2', array('pattern3'));

        $this->manager->registerPlugins(array($plugin1, $plugin2));
        $this->manager->activate('/project', array());

        $patterns = $this->manager->getIgnorePatterns();
        $this->assertContains('pattern1', $patterns);
        $this->assertContains('pattern2', $patterns);
        $this->assertContains('pattern3', $patterns);
    }

    public function testAggregateIgnorePatternsRemovesDuplicates()
    {
        $plugin1 = $this->createMockPluginWithPatterns('p1', array('shared', 'unique1'));
        $plugin2 = $this->createMockPluginWithPatterns('p2', array('shared', 'unique2'));

        $this->manager->registerPlugins(array($plugin1, $plugin2));
        $this->manager->activate('/project', array());

        $patterns = $this->manager->getIgnorePatterns();
        $this->assertCount(3, $patterns);
    }

    public function testShouldIgnoreSymbol()
    {
        $plugin = $this->createMockPluginWithPatterns('p1', array('App\\*ServiceProvider'));

        $this->manager->registerPlugin($plugin);
        $this->manager->activate('/project', array());

        $this->assertTrue($this->manager->shouldIgnoreSymbol('App\\MyServiceProvider'));
        $this->assertFalse($this->manager->shouldIgnoreSymbol('App\\MyController'));
    }

    public function testShouldIgnoreFile()
    {
        $plugin = $this->createMockPluginWithFilePatterns('p1', array('**/vendor/**'));

        $this->manager->registerPlugin($plugin);
        $this->manager->activate('/project', array());

        $this->assertTrue($this->manager->shouldIgnoreFile('/project/vendor/package/src/File.php'));
        $this->assertFalse($this->manager->shouldIgnoreFile('/project/src/File.php'));
    }

    public function testGetEntryPointsFromActivePlugins()
    {
        $plugin = $this->createMockPluginWithEntryPoints('p1', array('App\\Entry1', 'App\\Entry2'));

        $this->manager->registerPlugin($plugin);
        $this->manager->activate('/project', array());

        $entryPoints = $this->manager->getEntryPoints();
        $this->assertContains('App\\Entry1', $entryPoints);
        $this->assertContains('App\\Entry2', $entryPoints);
    }

    /**
     * Create a mock plugin
     *
     * @param string $name Plugin name
     * @param bool $applicable Whether plugin is applicable
     * @param int $priority Plugin priority
     *
     * @return PluginInterface
     */
    private function createMockPlugin($name, $applicable = true, $priority = 0)
    {
        $plugin = $this->getMockBuilder(PluginInterface::class)->getMock();
        $plugin->method('getName')->willReturn($name);
        $plugin->method('getDescription')->willReturn('Test plugin');
        $plugin->method('isApplicable')->willReturn($applicable);
        $plugin->method('getPriority')->willReturn($priority);
        $plugin->method('getIgnorePatterns')->willReturn(array());
        $plugin->method('getIgnoreFilePatterns')->willReturn(array());
        $plugin->method('getEntryPoints')->willReturn(array());
        $plugin->method('getAdditionalReferences')->willReturn(array());

        return $plugin;
    }

    /**
     * Create a mock plugin with ignore patterns
     *
     * @param string $name Plugin name
     * @param array $patterns Ignore patterns
     *
     * @return PluginInterface
     */
    private function createMockPluginWithPatterns($name, array $patterns)
    {
        $plugin = $this->getMockBuilder(PluginInterface::class)->getMock();
        $plugin->method('getName')->willReturn($name);
        $plugin->method('getDescription')->willReturn('Test plugin');
        $plugin->method('isApplicable')->willReturn(true);
        $plugin->method('getPriority')->willReturn(0);
        $plugin->method('getIgnorePatterns')->willReturn($patterns);
        $plugin->method('getIgnoreFilePatterns')->willReturn(array());
        $plugin->method('getEntryPoints')->willReturn(array());
        $plugin->method('getAdditionalReferences')->willReturn(array());

        return $plugin;
    }

    /**
     * Create a mock plugin with file ignore patterns
     *
     * @param string $name Plugin name
     * @param array $patterns File ignore patterns
     *
     * @return PluginInterface
     */
    private function createMockPluginWithFilePatterns($name, array $patterns)
    {
        $plugin = $this->getMockBuilder(PluginInterface::class)->getMock();
        $plugin->method('getName')->willReturn($name);
        $plugin->method('getDescription')->willReturn('Test plugin');
        $plugin->method('isApplicable')->willReturn(true);
        $plugin->method('getPriority')->willReturn(0);
        $plugin->method('getIgnorePatterns')->willReturn(array());
        $plugin->method('getIgnoreFilePatterns')->willReturn($patterns);
        $plugin->method('getEntryPoints')->willReturn(array());
        $plugin->method('getAdditionalReferences')->willReturn(array());

        return $plugin;
    }

    /**
     * Create a mock plugin with entry points
     *
     * @param string $name Plugin name
     * @param array $entryPoints Entry points
     *
     * @return PluginInterface
     */
    private function createMockPluginWithEntryPoints($name, array $entryPoints)
    {
        $plugin = $this->getMockBuilder(PluginInterface::class)->getMock();
        $plugin->method('getName')->willReturn($name);
        $plugin->method('getDescription')->willReturn('Test plugin');
        $plugin->method('isApplicable')->willReturn(true);
        $plugin->method('getPriority')->willReturn(0);
        $plugin->method('getIgnorePatterns')->willReturn(array());
        $plugin->method('getIgnoreFilePatterns')->willReturn(array());
        $plugin->method('getEntryPoints')->willReturn($entryPoints);
        $plugin->method('getAdditionalReferences')->willReturn(array());

        return $plugin;
    }
}
