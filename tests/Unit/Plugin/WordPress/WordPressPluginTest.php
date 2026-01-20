<?php
/**
 * WordPress Plugin Tests
 */

namespace PhpKnip\Tests\Unit\Plugin\WordPress;

use PhpKnip\Tests\TestCase;
use PhpKnip\Plugin\WordPress\WordPressPlugin;
use PhpKnip\Resolver\SymbolTable;
use PhpKnip\Resolver\Symbol;

class WordPressPluginTest extends TestCase
{
    /**
     * @var WordPressPlugin
     */
    private $plugin;

    /**
     * @var string
     */
    private $tempDir;

    protected function setUp(): void
    {
        $this->plugin = new WordPressPlugin();
        $this->tempDir = sys_get_temp_dir() . '/php-knip-wordpress-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testGetName()
    {
        $this->assertEquals('wordpress', $this->plugin->getName());
    }

    public function testGetDescription()
    {
        $this->assertNotEmpty($this->plugin->getDescription());
    }

    public function testIsApplicableWithWpConfig()
    {
        file_put_contents($this->tempDir . '/wp-config.php', '<?php // wp-config');

        $this->assertTrue($this->plugin->isApplicable($this->tempDir, array()));
    }

    public function testIsApplicableWithWpContentDirectory()
    {
        mkdir($this->tempDir . '/wp-content');

        $this->assertTrue($this->plugin->isApplicable($this->tempDir, array()));
    }

    public function testIsApplicableWithWordPressCoreDirectories()
    {
        mkdir($this->tempDir . '/wp-includes');
        mkdir($this->tempDir . '/wp-admin');

        $this->assertTrue($this->plugin->isApplicable($this->tempDir, array()));
    }

    public function testIsApplicableWithWordPressDependency()
    {
        $composerData = array(
            'require' => array(
                'johnpbloch/wordpress' => '^6.0',
            ),
        );

        $this->assertTrue($this->plugin->isApplicable($this->tempDir, $composerData));
    }

    public function testIsApplicableWithRootsWordPress()
    {
        $composerData = array(
            'require' => array(
                'roots/wordpress' => '^6.0',
            ),
        );

        $this->assertTrue($this->plugin->isApplicable($this->tempDir, $composerData));
    }

    public function testIsApplicableWithWpackagistPlugin()
    {
        $composerData = array(
            'require' => array(
                'wpackagist-plugin/advanced-custom-fields' => '^6.0',
            ),
        );

        $this->assertTrue($this->plugin->isApplicable($this->tempDir, $composerData));
    }

    public function testIsApplicableWithWpackagistTheme()
    {
        $composerData = array(
            'require' => array(
                'wpackagist-theme/twentytwentythree' => '*',
            ),
        );

        $this->assertTrue($this->plugin->isApplicable($this->tempDir, $composerData));
    }

    public function testIsNotApplicableWithoutWordPress()
    {
        $composerData = array(
            'require' => array(
                'laravel/framework' => '^9.0',
            ),
        );

        $this->assertFalse($this->plugin->isApplicable($this->tempDir, $composerData));
    }

    public function testGetPriority()
    {
        $this->assertEquals(100, $this->plugin->getPriority());
    }

    public function testGetIgnorePatterns()
    {
        $patterns = $this->plugin->getIgnorePatterns();

        $this->assertContains('*_Widget', $patterns);
        $this->assertContains('*Widget', $patterns);
        $this->assertContains('*_callback', $patterns);
        $this->assertContains('wp_ajax_*', $patterns);
    }

    public function testGetIgnoreFilePatterns()
    {
        $patterns = $this->plugin->getIgnoreFilePatterns();

        $this->assertContains('**/wp-admin/**', $patterns);
        $this->assertContains('**/wp-includes/**', $patterns);
        $this->assertContains('**/wp-content/cache/**', $patterns);
        $this->assertContains('**/wp-content/uploads/**', $patterns);
    }

    public function testGetEntryPointsWithTheme()
    {
        // Create WordPress theme structure
        $themeDir = $this->tempDir . '/wp-content/themes/my-theme';
        mkdir($themeDir, 0777, true);

        file_put_contents($themeDir . '/functions.php', '<?php // theme functions');
        file_put_contents($themeDir . '/index.php', '<?php // theme index');

        $entryPoints = $this->plugin->getEntryPoints($this->tempDir);

        $this->assertContains($themeDir . '/functions.php', $entryPoints);
        $this->assertContains($themeDir . '/index.php', $entryPoints);
    }

    public function testGetEntryPointsWithThemeTemplates()
    {
        // Create WordPress theme with templates
        $themeDir = $this->tempDir . '/wp-content/themes/my-theme';
        mkdir($themeDir, 0777, true);

        file_put_contents($themeDir . '/single.php', '<?php // single post');
        file_put_contents($themeDir . '/page.php', '<?php // page');
        file_put_contents($themeDir . '/header.php', '<?php // header');
        file_put_contents($themeDir . '/footer.php', '<?php // footer');

        $entryPoints = $this->plugin->getEntryPoints($this->tempDir);

        $this->assertContains($themeDir . '/single.php', $entryPoints);
        $this->assertContains($themeDir . '/page.php', $entryPoints);
        $this->assertContains($themeDir . '/header.php', $entryPoints);
        $this->assertContains($themeDir . '/footer.php', $entryPoints);
    }

    public function testGetEntryPointsWithPlugin()
    {
        // Create WordPress plugin structure
        $pluginDir = $this->tempDir . '/wp-content/plugins/my-plugin';
        mkdir($pluginDir, 0777, true);

        $pluginContent = <<<'PHP'
<?php
/**
 * Plugin Name: My Plugin
 * Description: A test plugin
 */

function my_plugin_init() {}
PHP;
        file_put_contents($pluginDir . '/my-plugin.php', $pluginContent);

        $entryPoints = $this->plugin->getEntryPoints($this->tempDir);

        $this->assertContains($pluginDir . '/my-plugin.php', $entryPoints);
    }

    public function testGetEntryPointsWithSingleFilePlugin()
    {
        // Create single-file plugin directly in plugins directory
        $pluginsDir = $this->tempDir . '/wp-content/plugins';
        mkdir($pluginsDir, 0777, true);

        $pluginContent = <<<'PHP'
<?php
/**
 * Plugin Name: Single File Plugin
 */
PHP;
        file_put_contents($pluginsDir . '/single-plugin.php', $pluginContent);

        $entryPoints = $this->plugin->getEntryPoints($this->tempDir);

        $this->assertContains($pluginsDir . '/single-plugin.php', $entryPoints);
    }

    public function testGetEntryPointsWithMuPlugins()
    {
        // Create must-use plugin
        $muPluginDir = $this->tempDir . '/wp-content/mu-plugins';
        mkdir($muPluginDir, 0777, true);

        file_put_contents($muPluginDir . '/custom.php', '<?php // mu-plugin');

        $entryPoints = $this->plugin->getEntryPoints($this->tempDir);

        $this->assertContains($muPluginDir . '/custom.php', $entryPoints);
    }

    public function testGetEntryPointsWithDropIns()
    {
        // Create drop-in files
        $contentDir = $this->tempDir . '/wp-content';
        mkdir($contentDir, 0777, true);

        file_put_contents($contentDir . '/object-cache.php', '<?php // object cache');
        file_put_contents($contentDir . '/db.php', '<?php // custom db');

        $entryPoints = $this->plugin->getEntryPoints($this->tempDir);

        $this->assertContains($contentDir . '/object-cache.php', $entryPoints);
        $this->assertContains($contentDir . '/db.php', $entryPoints);
    }

    public function testGetAdditionalReferencesFromAddAction()
    {
        // Create plugin with add_action
        $pluginDir = $this->tempDir . '/wp-content/plugins/test';
        mkdir($pluginDir, 0777, true);

        $pluginContent = <<<'PHP'
<?php
/**
 * Plugin Name: Test Plugin
 */

add_action('init', 'my_init_function');
add_action('wp_enqueue_scripts', 'enqueue_my_scripts');
PHP;
        file_put_contents($pluginDir . '/test.php', $pluginContent);

        $references = $this->plugin->getAdditionalReferences($this->tempDir);

        $funcNames = array_map(function ($ref) {
            return $ref->getSymbolName();
        }, $references);

        $this->assertContains('my_init_function', $funcNames);
        $this->assertContains('enqueue_my_scripts', $funcNames);
    }

    public function testGetAdditionalReferencesFromAddFilter()
    {
        // Create plugin with add_filter
        $pluginDir = $this->tempDir . '/wp-content/plugins/test';
        mkdir($pluginDir, 0777, true);

        $pluginContent = <<<'PHP'
<?php
/**
 * Plugin Name: Test Plugin
 */

add_filter('the_content', 'modify_content');
add_filter('the_title', 'modify_title');
PHP;
        file_put_contents($pluginDir . '/test.php', $pluginContent);

        $references = $this->plugin->getAdditionalReferences($this->tempDir);

        $funcNames = array_map(function ($ref) {
            return $ref->getSymbolName();
        }, $references);

        $this->assertContains('modify_content', $funcNames);
        $this->assertContains('modify_title', $funcNames);
    }

    public function testGetAdditionalReferencesFromArrayCallback()
    {
        // Create plugin with array callback
        $pluginDir = $this->tempDir . '/wp-content/plugins/test';
        mkdir($pluginDir, 0777, true);

        $pluginContent = <<<'PHP'
<?php
/**
 * Plugin Name: Test Plugin
 */

class My_Plugin {
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_filter('the_content', [$this, 'filter_content']);
    }

    public function init() {}
    public function filter_content($content) { return $content; }
}
PHP;
        file_put_contents($pluginDir . '/test.php', $pluginContent);

        $references = $this->plugin->getAdditionalReferences($this->tempDir);

        $methodNames = array_map(function ($ref) {
            return $ref->getSymbolName();
        }, $references);

        $this->assertContains('init', $methodNames);
        $this->assertContains('filter_content', $methodNames);
    }

    public function testGetAdditionalReferencesFromStaticCallback()
    {
        // Create plugin with static callback
        $pluginDir = $this->tempDir . '/wp-content/plugins/test';
        mkdir($pluginDir, 0777, true);

        $pluginContent = <<<'PHP'
<?php
/**
 * Plugin Name: Test Plugin
 */
namespace MyPlugin;

class Bootstrap {
    public static function register() {
        add_action('init', [self::class, 'init']);
        add_action('admin_init', ['MyPlugin\Admin', 'init']);
    }

    public static function init() {}
}
PHP;
        file_put_contents($pluginDir . '/test.php', $pluginContent);

        $references = $this->plugin->getAdditionalReferences($this->tempDir);

        $symbolNames = array_map(function ($ref) {
            return $ref->getSymbolName();
        }, $references);

        $this->assertContains('init', $symbolNames);
    }

    public function testGetAdditionalReferencesFromAddShortcode()
    {
        // Create plugin with shortcode
        $pluginDir = $this->tempDir . '/wp-content/plugins/test';
        mkdir($pluginDir, 0777, true);

        $pluginContent = <<<'PHP'
<?php
/**
 * Plugin Name: Test Plugin
 */

add_shortcode('my_gallery', 'render_gallery');
PHP;
        file_put_contents($pluginDir . '/test.php', $pluginContent);

        $references = $this->plugin->getAdditionalReferences($this->tempDir);

        $funcNames = array_map(function ($ref) {
            return $ref->getSymbolName();
        }, $references);

        $this->assertContains('render_gallery', $funcNames);
    }

    public function testGetAdditionalReferencesFromWpCliCommand()
    {
        // Create plugin with WP-CLI command
        $pluginDir = $this->tempDir . '/wp-content/plugins/test';
        mkdir($pluginDir, 0777, true);

        $pluginContent = <<<'PHP'
<?php
/**
 * Plugin Name: Test Plugin
 */
namespace MyPlugin;

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('myplugin', 'MyPlugin\CLI\Command');
    WP_CLI::add_command('other', CLI_Handler::class);
}
PHP;
        file_put_contents($pluginDir . '/test.php', $pluginContent);

        $references = $this->plugin->getAdditionalReferences($this->tempDir);

        $classNames = array_map(function ($ref) {
            return $ref->getSymbolName();
        }, $references);

        $this->assertContains('MyPlugin\\CLI\\Command', $classNames);
        $this->assertContains('MyPlugin\\CLI_Handler', $classNames);
    }

    public function testProcessSymbolsMarksWidgetsAsUsed()
    {
        $symbolTable = new SymbolTable();
        $widget = Symbol::createClass('My_Custom_Widget', 'MyPlugin');
        $symbolTable->add($widget);

        $this->plugin->processSymbols($symbolTable, $this->tempDir);

        $this->assertTrue($widget->getMetadataValue('framework_used', false));
    }

    public function testProcessSymbolsMarksRestControllersWithFrameworkType()
    {
        $symbolTable = new SymbolTable();
        $controller = Symbol::createClass('Posts_REST_Controller', 'MyPlugin\\REST');
        $symbolTable->add($controller);

        $this->plugin->processSymbols($symbolTable, $this->tempDir);

        $this->assertEquals('rest_controller', $controller->getMetadataValue('framework_type'));
    }

    public function testProcessSymbolsMarksAdminPagesWithFrameworkType()
    {
        $symbolTable = new SymbolTable();
        $admin = Symbol::createClass('Settings_Page', 'MyPlugin\\Admin');
        $symbolTable->add($admin);

        $this->plugin->processSymbols($symbolTable, $this->tempDir);

        $this->assertEquals('admin_page', $admin->getMetadataValue('framework_type'));
    }

    public function testProcessSymbolsMarksCliCommandsWithFrameworkType()
    {
        $symbolTable = new SymbolTable();
        $command = Symbol::createClass('Export_Command', 'MyPlugin\\CLI');
        $symbolTable->add($command);

        $this->plugin->processSymbols($symbolTable, $this->tempDir);

        $this->assertEquals('cli_command', $command->getMetadataValue('framework_type'));
    }

    public function testReferencesHaveMetadata()
    {
        // Create plugin with hook
        $pluginDir = $this->tempDir . '/wp-content/plugins/test';
        mkdir($pluginDir, 0777, true);

        $pluginContent = <<<'PHP'
<?php
/**
 * Plugin Name: Test Plugin
 */

add_action('init', 'my_init');
PHP;
        file_put_contents($pluginDir . '/test.php', $pluginContent);

        $references = $this->plugin->getAdditionalReferences($this->tempDir);

        $this->assertNotEmpty($references);
        $ref = $references[0];
        $metadata = $ref->getMetadata();

        $this->assertEquals('wordpress_hook', $metadata['source']);
        $this->assertEquals('add_action', $metadata['hook_function']);
    }

    public function testIgnoresVendorDirectory()
    {
        // Create plugin with vendor directory
        $pluginDir = $this->tempDir . '/wp-content/plugins/test';
        $vendorDir = $pluginDir . '/vendor/some-package';
        mkdir($vendorDir, 0777, true);

        $pluginContent = <<<'PHP'
<?php
/**
 * Plugin Name: Test Plugin
 */
PHP;
        file_put_contents($pluginDir . '/test.php', $pluginContent);

        $vendorContent = <<<'PHP'
<?php
add_action('init', 'vendor_function');
PHP;
        file_put_contents($vendorDir . '/init.php', $vendorContent);

        $references = $this->plugin->getAdditionalReferences($this->tempDir);

        $funcNames = array_map(function ($ref) {
            return $ref->getSymbolName();
        }, $references);

        $this->assertNotContains('vendor_function', $funcNames);
    }

    /**
     * Remove directory recursively
     *
     * @param string $dir Directory path
     */
    private function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
