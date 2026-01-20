<?php
/**
 * WordPress Plugin
 *
 * Framework support for WordPress applications
 */

namespace PhpKnip\Plugin\WordPress;

use PhpKnip\Plugin\AbstractPlugin;
use PhpKnip\Resolver\SymbolTable;
use PhpKnip\Resolver\Reference;

/**
 * WordPress framework plugin
 *
 * Provides support for:
 * - Hook callbacks (add_action, add_filter)
 * - Theme functions.php
 * - Plugin main files
 * - Shortcodes
 * - Widgets (WP_Widget subclasses)
 * - REST API endpoints
 * - WP-CLI commands
 */
class WordPressPlugin extends AbstractPlugin
{
    /**
     * Hook functions that register callbacks
     *
     * @var array
     */
    private static $hookFunctions = array(
        'add_action',
        'add_filter',
        'add_shortcode',
        'register_activation_hook',
        'register_deactivation_hook',
        'register_uninstall_hook',
        'register_rest_route',
        'register_block_type',
        'register_widget',
        'wp_register_sidebar_widget',
        'add_menu_page',
        'add_submenu_page',
        'add_options_page',
        'add_theme_page',
        'add_plugins_page',
        'add_users_page',
        'add_dashboard_page',
        'add_posts_page',
        'add_media_page',
        'add_links_page',
        'add_pages_page',
        'add_comments_page',
        'add_management_page',
    );

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'wordpress';
    }

    /**
     * @inheritDoc
     */
    public function getDescription()
    {
        return 'WordPress framework support - recognizes hooks, themes, plugins, widgets, and REST API';
    }

    /**
     * @inheritDoc
     */
    public function isApplicable($projectRoot, array $composerData)
    {
        // Check for wp-config.php (WordPress signature)
        if ($this->fileExists($projectRoot, 'wp-config.php')) {
            return true;
        }

        // Check for wp-content directory (WordPress themes/plugins)
        if ($this->directoryExists($projectRoot, 'wp-content')) {
            return true;
        }

        // Check for WordPress core directories
        if ($this->directoryExists($projectRoot, 'wp-includes') &&
            $this->directoryExists($projectRoot, 'wp-admin')) {
            return true;
        }

        // Check for WordPress-related composer packages
        $wordpressPackages = array(
            'johnpbloch/wordpress',
            'johnpbloch/wordpress-core',
            'roots/wordpress',
            'roots/wordpress-core-installer',
        );

        foreach ($wordpressPackages as $package) {
            if ($this->hasComposerDependency($package, $composerData)) {
                return true;
            }
        }

        // Check for wpackagist packages (plugins/themes from WordPress.org)
        $require = isset($composerData['require']) ? $composerData['require'] : array();
        $requireDev = isset($composerData['require-dev']) ? $composerData['require-dev'] : array();
        $allDeps = array_merge(array_keys($require), array_keys($requireDev));

        foreach ($allDeps as $package) {
            if (strpos($package, 'wpackagist-plugin/') === 0 ||
                strpos($package, 'wpackagist-theme/') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function getPriority()
    {
        return 100; // High priority for WordPress
    }

    /**
     * @inheritDoc
     */
    public function getIgnorePatterns()
    {
        return array(
            // Widget classes
            '*_Widget',
            'WP_Widget_*',
            '*Widget',

            // WordPress lifecycle hooks
            '*\\activate',
            '*\\deactivate',
            '*\\uninstall',

            // Common WordPress callback patterns
            '*_callback',
            '*_handler',
            '*_hook',
            'render_*',
            'handle_*',

            // Admin menu pages
            '*_page',
            '*_menu',
            '*_admin',

            // AJAX handlers
            'wp_ajax_*',
            'wp_ajax_nopriv_*',

            // REST API
            '*_rest_*',
            '*_endpoint',

            // Block patterns
            '*_block',
            'render_block_*',

            // Customizer
            '*_customize_*',
            '*Customizer*',
        );
    }

    /**
     * @inheritDoc
     */
    public function getIgnoreFilePatterns()
    {
        return array(
            // WordPress core files (should never be analyzed)
            '**/wp-admin/**',
            '**/wp-includes/**',

            // WordPress generated/cache files
            '**/wp-content/cache/**',
            '**/wp-content/uploads/**',
            '**/wp-content/upgrade/**',
            '**/wp-content/languages/**',
            '**/wp-content/debug.log',

            // Vendor directories
            '**/vendor/**',
            '**/node_modules/**',

            // Common WordPress build artifacts
            '**/wp-content/plugins/*/build/**',
            '**/wp-content/themes/*/build/**',
        );
    }

    /**
     * @inheritDoc
     */
    public function getEntryPoints($projectRoot)
    {
        $entryPoints = array();

        // Theme functions.php files
        $entryPoints = array_merge($entryPoints, $this->getThemeEntryPoints($projectRoot));

        // Plugin main files
        $entryPoints = array_merge($entryPoints, $this->getPluginEntryPoints($projectRoot));

        // Must-use plugins
        $entryPoints = array_merge($entryPoints, $this->getMuPluginEntryPoints($projectRoot));

        // Drop-ins
        $entryPoints = array_merge($entryPoints, $this->getDropInEntryPoints($projectRoot));

        return array_unique($entryPoints);
    }

    /**
     * @inheritDoc
     */
    public function getAdditionalReferences($projectRoot)
    {
        $references = array();

        // Parse theme files for hook references
        $themeDir = $projectRoot . '/wp-content/themes';
        if (is_dir($themeDir)) {
            $references = array_merge($references, $this->parseDirectoryForHooks($themeDir));
        }

        // Parse plugin files for hook references
        $pluginDir = $projectRoot . '/wp-content/plugins';
        if (is_dir($pluginDir)) {
            $references = array_merge($references, $this->parseDirectoryForHooks($pluginDir));
        }

        // Parse mu-plugins
        $muPluginDir = $projectRoot . '/wp-content/mu-plugins';
        if (is_dir($muPluginDir)) {
            $references = array_merge($references, $this->parseDirectoryForHooks($muPluginDir));
        }

        return $references;
    }

    /**
     * @inheritDoc
     */
    public function processSymbols(SymbolTable $symbolTable, $projectRoot)
    {
        foreach ($symbolTable->getClasses() as $class) {
            $fqn = $class->getFullyQualifiedName();
            $name = $class->getName();

            // Widgets are always used by WordPress
            if ($this->isWidget($name, $fqn)) {
                $class->setMetadata('framework_used', true);
                $class->setMetadata('framework_reason', 'WordPress Widget');
            }

            // REST Controllers
            if ($this->isRestController($name, $fqn)) {
                $class->setMetadata('framework_type', 'rest_controller');
            }

            // Admin pages
            if ($this->isAdminPage($name, $fqn)) {
                $class->setMetadata('framework_type', 'admin_page');
            }

            // WP-CLI Commands
            if ($this->isCliCommand($name, $fqn)) {
                $class->setMetadata('framework_type', 'cli_command');
            }
        }
    }

    /**
     * Get theme entry points
     *
     * @param string $projectRoot Project root
     *
     * @return array
     */
    private function getThemeEntryPoints($projectRoot)
    {
        $entryPoints = array();
        $themesDir = $projectRoot . '/wp-content/themes';

        if (!is_dir($themesDir)) {
            return $entryPoints;
        }

        $themes = glob($themesDir . '/*', GLOB_ONLYDIR);
        if ($themes === false) {
            return $entryPoints;
        }

        foreach ($themes as $themeDir) {
            // functions.php is the main theme entry point
            $functionsFile = $themeDir . '/functions.php';
            if (file_exists($functionsFile)) {
                $entryPoints[] = $functionsFile;
            }

            // index.php is required
            $indexFile = $themeDir . '/index.php';
            if (file_exists($indexFile)) {
                $entryPoints[] = $indexFile;
            }

            // Template files
            $templateFiles = array(
                'header.php', 'footer.php', 'sidebar.php',
                'single.php', 'page.php', 'archive.php',
                'search.php', '404.php', 'front-page.php',
                'home.php', 'comments.php',
            );

            foreach ($templateFiles as $template) {
                $templatePath = $themeDir . '/' . $template;
                if (file_exists($templatePath)) {
                    $entryPoints[] = $templatePath;
                }
            }
        }

        return $entryPoints;
    }

    /**
     * Get plugin entry points
     *
     * @param string $projectRoot Project root
     *
     * @return array
     */
    private function getPluginEntryPoints($projectRoot)
    {
        $entryPoints = array();
        $pluginsDir = $projectRoot . '/wp-content/plugins';

        if (!is_dir($pluginsDir)) {
            return $entryPoints;
        }

        $plugins = glob($pluginsDir . '/*', GLOB_ONLYDIR);
        if ($plugins === false) {
            return $entryPoints;
        }

        foreach ($plugins as $pluginDir) {
            $pluginName = basename($pluginDir);

            // Main plugin file (same name as directory)
            $mainFile = $pluginDir . '/' . $pluginName . '.php';
            if (file_exists($mainFile)) {
                $entryPoints[] = $mainFile;
            }

            // Alternative: plugin.php
            $altFile = $pluginDir . '/plugin.php';
            if (file_exists($altFile)) {
                $entryPoints[] = $altFile;
            }

            // Look for plugin header in any PHP file at root level
            $rootFiles = glob($pluginDir . '/*.php');
            if ($rootFiles !== false) {
                foreach ($rootFiles as $file) {
                    if ($this->hasPluginHeader($file)) {
                        $entryPoints[] = $file;
                    }
                }
            }
        }

        // Single-file plugins directly in plugins directory
        $singleFiles = glob($pluginsDir . '/*.php');
        if ($singleFiles !== false) {
            foreach ($singleFiles as $file) {
                if ($this->hasPluginHeader($file)) {
                    $entryPoints[] = $file;
                }
            }
        }

        return array_unique($entryPoints);
    }

    /**
     * Get must-use plugin entry points
     *
     * @param string $projectRoot Project root
     *
     * @return array
     */
    private function getMuPluginEntryPoints($projectRoot)
    {
        $entryPoints = array();
        $muPluginsDir = $projectRoot . '/wp-content/mu-plugins';

        if (!is_dir($muPluginsDir)) {
            return $entryPoints;
        }

        // All PHP files in mu-plugins are auto-loaded
        $files = glob($muPluginsDir . '/*.php');
        if ($files !== false) {
            $entryPoints = array_merge($entryPoints, $files);
        }

        return $entryPoints;
    }

    /**
     * Get drop-in entry points
     *
     * @param string $projectRoot Project root
     *
     * @return array
     */
    private function getDropInEntryPoints($projectRoot)
    {
        $entryPoints = array();
        $contentDir = $projectRoot . '/wp-content';

        if (!is_dir($contentDir)) {
            return $entryPoints;
        }

        // Standard WordPress drop-ins
        $dropIns = array(
            'advanced-cache.php',
            'db.php',
            'db-error.php',
            'install.php',
            'maintenance.php',
            'object-cache.php',
            'sunrise.php',
            'blog-deleted.php',
            'blog-inactive.php',
            'blog-suspended.php',
        );

        foreach ($dropIns as $dropIn) {
            $path = $contentDir . '/' . $dropIn;
            if (file_exists($path)) {
                $entryPoints[] = $path;
            }
        }

        return $entryPoints;
    }

    /**
     * Check if a file has WordPress plugin header
     *
     * @param string $filePath File path
     *
     * @return bool
     */
    private function hasPluginHeader($filePath)
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return false;
        }

        // Read only first 8KB for performance
        $header = substr($content, 0, 8192);

        // Check for Plugin Name header
        return preg_match('/Plugin Name\s*:/i', $header) === 1;
    }

    /**
     * Parse directory for hook callbacks
     *
     * @param string $directory Directory to parse
     *
     * @return array<Reference>
     */
    private function parseDirectoryForHooks($directory)
    {
        $references = array();

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $filePath = $file->getPathname();

            // Skip vendor directories
            if (strpos($filePath, '/vendor/') !== false ||
                strpos($filePath, '/node_modules/') !== false) {
                continue;
            }

            $refs = $this->parseFileForHooks($filePath);
            $references = array_merge($references, $refs);
        }

        return $references;
    }

    /**
     * Parse a PHP file for hook callbacks
     *
     * @param string $filePath File path
     *
     * @return array<Reference>
     */
    private function parseFileForHooks($filePath)
    {
        $references = array();
        $content = file_get_contents($filePath);

        if ($content === false) {
            return $references;
        }

        // Extract namespace
        $namespace = '';
        if (preg_match('/namespace\s+([^;]+);/', $content, $nsMatches)) {
            $namespace = trim($nsMatches[1]);
        }

        // Parse hook function calls
        foreach (self::$hookFunctions as $hookFunc) {
            $refs = $this->extractHookCallbacks($content, $hookFunc, $filePath, $namespace);
            $references = array_merge($references, $refs);
        }

        // Parse WP_CLI::add_command
        $cliRefs = $this->extractCliCommands($content, $filePath, $namespace);
        $references = array_merge($references, $cliRefs);

        return $references;
    }

    /**
     * Extract callbacks from hook function calls
     *
     * @param string $content File content
     * @param string $hookFunc Hook function name
     * @param string $filePath File path
     * @param string $namespace Current namespace
     *
     * @return array<Reference>
     */
    private function extractHookCallbacks($content, $hookFunc, $filePath, $namespace)
    {
        $references = array();

        // Pattern for function name as string: add_action('hook', 'function_name')
        $stringPattern = '/' . preg_quote($hookFunc, '/') .
            '\s*\(\s*[\'"][^\'"]+[\'"]\s*,\s*[\'"]([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)[\'"]/';

        if (preg_match_all($stringPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $match) {
                $funcName = $match[0];
                $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;

                $ref = Reference::createFunctionCall($funcName, $filePath, $line);
                $ref->setMetadata('source', 'wordpress_hook');
                $ref->setMetadata('hook_function', $hookFunc);
                $references[] = $ref;
            }
        }

        // Pattern for array callback: add_action('hook', array($this, 'method'))
        // or add_action('hook', [$this, 'method'])
        $arrayPattern = '/' . preg_quote($hookFunc, '/') .
            '\s*\(\s*[\'"][^\'"]+[\'"]\s*,\s*(?:array\s*\(|\[)\s*\$this\s*,\s*[\'"]([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)[\'"]\s*(?:\)|\])/';

        if (preg_match_all($arrayPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $match) {
                $methodName = $match[0];
                $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;

                $ref = Reference::createMethodCall($methodName, $filePath, $line);
                $ref->setMetadata('source', 'wordpress_hook');
                $ref->setMetadata('hook_function', $hookFunc);
                $references[] = $ref;
            }
        }

        // Pattern for static callback: add_action('hook', array('ClassName', 'method'))
        // or add_action('hook', [ClassName::class, 'method'])
        $staticPattern = '/' . preg_quote($hookFunc, '/') .
            '\s*\(\s*[\'"][^\'"]+[\'"]\s*,\s*(?:array\s*\(|\[)\s*' .
            '(?:[\'"]([A-Za-z_\x7f-\xff][A-Za-z0-9_\x7f-\xff\\\\]*)[\'"]\s*,' .
            '|([A-Za-z_\x7f-\xff][A-Za-z0-9_\x7f-\xff\\\\]*)::class\s*,)' .
            '\s*[\'"]([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)[\'"]\s*(?:\)|\])/';

        if (preg_match_all($staticPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            for ($i = 0; $i < count($matches[0]); $i++) {
                $className = !empty($matches[1][$i][0]) ? $matches[1][$i][0] : $matches[2][$i][0];
                $methodName = $matches[3][$i][0];
                $offset = $matches[0][$i][1];
                $line = substr_count(substr($content, 0, $offset), "\n") + 1;

                // Resolve class name with namespace if not already qualified
                if (strpos($className, '\\') === false && $namespace !== '') {
                    $className = $namespace . '\\' . $className;
                }

                $ref = Reference::createStaticCall($className, $methodName, $filePath, $line);
                $ref->setMetadata('source', 'wordpress_hook');
                $ref->setMetadata('hook_function', $hookFunc);
                $references[] = $ref;
            }
        }

        // Pattern for closure with __CLASS__ : add_action('hook', [__CLASS__, 'method'])
        $classConstPattern = '/' . preg_quote($hookFunc, '/') .
            '\s*\(\s*[\'"][^\'"]+[\'"]\s*,\s*(?:array\s*\(|\[)\s*__CLASS__\s*,' .
            '\s*[\'"]([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)[\'"]\s*(?:\)|\])/';

        if (preg_match_all($classConstPattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $match) {
                $methodName = $match[0];
                $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;

                $ref = Reference::createMethodCall($methodName, $filePath, $line);
                $ref->setMetadata('source', 'wordpress_hook');
                $ref->setMetadata('hook_function', $hookFunc);
                $ref->setMetadata('uses_class_constant', true);
                $references[] = $ref;
            }
        }

        return $references;
    }

    /**
     * Extract WP-CLI command callbacks
     *
     * @param string $content File content
     * @param string $filePath File path
     * @param string $namespace Current namespace
     *
     * @return array<Reference>
     */
    private function extractCliCommands($content, $filePath, $namespace)
    {
        $references = array();

        // Pattern: WP_CLI::add_command('command', 'ClassName')
        // or WP_CLI::add_command('command', ClassName::class)
        $pattern = '/WP_CLI::add_command\s*\(\s*[\'"][^\'"]+[\'"]\s*,\s*' .
            '(?:[\'"]([A-Za-z_\x7f-\xff][A-Za-z0-9_\x7f-\xff\\\\]*)[\'"]\s*' .
            '|([A-Za-z_\x7f-\xff][A-Za-z0-9_\x7f-\xff\\\\]*)::class)/';

        if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            for ($i = 0; $i < count($matches[0]); $i++) {
                $className = !empty($matches[1][$i][0]) ? $matches[1][$i][0] : $matches[2][$i][0];
                $offset = $matches[0][$i][1];
                $line = substr_count(substr($content, 0, $offset), "\n") + 1;

                // Resolve class name with namespace if not already qualified
                if (strpos($className, '\\') === false && $namespace !== '') {
                    $className = $namespace . '\\' . $className;
                }

                $ref = Reference::createClassString($className, $filePath, $line);
                $ref->setMetadata('source', 'wp_cli_command');
                $references[] = $ref;
            }
        }

        return $references;
    }

    /**
     * Check if class is a Widget
     *
     * @param string $name Class short name
     * @param string $fqn Class FQN
     *
     * @return bool
     */
    private function isWidget($name, $fqn)
    {
        return strpos($name, 'Widget') !== false ||
            strpos($name, '_Widget') !== false;
    }

    /**
     * Check if class is a REST Controller
     *
     * @param string $name Class short name
     * @param string $fqn Class FQN
     *
     * @return bool
     */
    private function isRestController($name, $fqn)
    {
        return strpos($fqn, '\\REST\\') !== false ||
            strpos($fqn, '\\Api\\') !== false ||
            strpos($name, 'REST_Controller') !== false ||
            strpos($name, 'Rest_Controller') !== false;
    }

    /**
     * Check if class is an Admin Page
     *
     * @param string $name Class short name
     * @param string $fqn Class FQN
     *
     * @return bool
     */
    private function isAdminPage($name, $fqn)
    {
        return strpos($fqn, '\\Admin\\') !== false ||
            strpos($name, 'Admin') !== false ||
            strpos($name, '_Admin') !== false;
    }

    /**
     * Check if class is a WP-CLI Command
     *
     * @param string $name Class short name
     * @param string $fqn Class FQN
     *
     * @return bool
     */
    private function isCliCommand($name, $fqn)
    {
        return strpos($fqn, '\\CLI\\') !== false ||
            strpos($fqn, '\\Command\\') !== false ||
            strpos($name, '_Command') !== false ||
            strpos($name, 'CLI') !== false;
    }
}
