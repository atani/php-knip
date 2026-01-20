<?php
/**
 * Plugin Manager
 *
 * Manages discovery, loading, and execution of framework plugins
 */

namespace PhpKnip\Plugin;

use PhpKnip\Resolver\SymbolTable;
use PhpKnip\Resolver\Reference;

/**
 * Manages framework plugins
 */
class PluginManager
{
    /**
     * @var array<PluginInterface> Registered plugins
     */
    private $plugins = array();

    /**
     * @var array<PluginInterface> Active plugins for current project
     */
    private $activePlugins = array();

    /**
     * @var string|null Project root directory
     */
    private $projectRoot;

    /**
     * @var array Composer data
     */
    private $composerData = array();

    /**
     * @var bool Whether plugins have been activated
     */
    private $activated = false;

    /**
     * Register a plugin
     *
     * @param PluginInterface $plugin Plugin to register
     *
     * @return $this
     */
    public function registerPlugin(PluginInterface $plugin)
    {
        $this->plugins[$plugin->getName()] = $plugin;
        $this->activated = false; // Reset activation state
        return $this;
    }

    /**
     * Register multiple plugins
     *
     * @param array<PluginInterface> $plugins Plugins to register
     *
     * @return $this
     */
    public function registerPlugins(array $plugins)
    {
        foreach ($plugins as $plugin) {
            $this->registerPlugin($plugin);
        }
        return $this;
    }

    /**
     * Get all registered plugins
     *
     * @return array<PluginInterface>
     */
    public function getRegisteredPlugins()
    {
        return $this->plugins;
    }

    /**
     * Get a registered plugin by name
     *
     * @param string $name Plugin name
     *
     * @return PluginInterface|null
     */
    public function getPlugin($name)
    {
        return isset($this->plugins[$name]) ? $this->plugins[$name] : null;
    }

    /**
     * Discover and register built-in plugins
     *
     * @return $this
     */
    public function discoverBuiltinPlugins()
    {
        // Register Laravel plugin
        $this->registerPlugin(new Laravel\LaravelPlugin());

        // Register WordPress plugin
        $this->registerPlugin(new WordPress\WordPressPlugin());

        // Register Symfony plugin
        $this->registerPlugin(new Symfony\SymfonyPlugin());

        return $this;
    }

    /**
     * Activate applicable plugins for a project
     *
     * @param string $projectRoot Project root directory
     * @param array $composerData Parsed composer.json data
     * @param string $framework Framework setting ('auto' or specific framework name)
     *
     * @return $this
     */
    public function activate($projectRoot, array $composerData, $framework = 'auto')
    {
        $this->projectRoot = $projectRoot;
        $this->composerData = $composerData;
        $this->activePlugins = array();

        // Check each plugin for applicability
        foreach ($this->plugins as $plugin) {
            // If specific framework is set, only activate matching plugin
            if ($framework !== 'auto' && $framework !== null) {
                if ($plugin->getName() === $framework) {
                    $this->activePlugins[] = $plugin;
                }
            } elseif ($plugin->isApplicable($projectRoot, $composerData)) {
                $this->activePlugins[] = $plugin;
            }
        }

        // Sort by priority (higher first)
        usort($this->activePlugins, function (PluginInterface $a, PluginInterface $b) {
            return $b->getPriority() - $a->getPriority();
        });

        $this->activated = true;
        return $this;
    }

    /**
     * Get active plugins
     *
     * @return array<PluginInterface>
     */
    public function getActivePlugins()
    {
        return $this->activePlugins;
    }

    /**
     * Check if any plugins are active
     *
     * @return bool
     */
    public function hasActivePlugins()
    {
        return !empty($this->activePlugins);
    }

    /**
     * Get active plugin names
     *
     * @return array<string>
     */
    public function getActivePluginNames()
    {
        return array_map(function (PluginInterface $plugin) {
            return $plugin->getName();
        }, $this->activePlugins);
    }

    /**
     * Get aggregated ignore patterns from all active plugins
     *
     * @return array<string>
     */
    public function getIgnorePatterns()
    {
        $patterns = array();
        foreach ($this->activePlugins as $plugin) {
            $patterns = array_merge($patterns, $plugin->getIgnorePatterns());
        }
        return array_unique($patterns);
    }

    /**
     * Get aggregated file ignore patterns from all active plugins
     *
     * @return array<string>
     */
    public function getIgnoreFilePatterns()
    {
        $patterns = array();
        foreach ($this->activePlugins as $plugin) {
            $patterns = array_merge($patterns, $plugin->getIgnoreFilePatterns());
        }
        return array_unique($patterns);
    }

    /**
     * Process symbols through all active plugins
     *
     * @param SymbolTable $symbolTable Symbol table
     *
     * @return void
     */
    public function processSymbols(SymbolTable $symbolTable)
    {
        if ($this->projectRoot === null) {
            return;
        }

        foreach ($this->activePlugins as $plugin) {
            $plugin->processSymbols($symbolTable, $this->projectRoot);
        }
    }

    /**
     * Get additional references from all active plugins
     *
     * @return array<Reference>
     */
    public function getAdditionalReferences()
    {
        if ($this->projectRoot === null) {
            return array();
        }

        $references = array();
        foreach ($this->activePlugins as $plugin) {
            $references = array_merge($references, $plugin->getAdditionalReferences($this->projectRoot));
        }
        return $references;
    }

    /**
     * Get entry points from all active plugins
     *
     * @return array<string>
     */
    public function getEntryPoints()
    {
        if ($this->projectRoot === null) {
            return array();
        }

        $entryPoints = array();
        foreach ($this->activePlugins as $plugin) {
            $entryPoints = array_merge($entryPoints, $plugin->getEntryPoints($this->projectRoot));
        }
        return array_unique($entryPoints);
    }

    /**
     * Check if a symbol should be ignored based on plugin patterns
     *
     * @param string $symbolName Symbol name (FQN)
     *
     * @return bool
     */
    public function shouldIgnoreSymbol($symbolName)
    {
        foreach ($this->getIgnorePatterns() as $pattern) {
            if ($this->matchesPattern($symbolName, $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a file should be ignored based on plugin patterns
     *
     * @param string $filePath File path
     *
     * @return bool
     */
    public function shouldIgnoreFile($filePath)
    {
        foreach ($this->getIgnoreFilePatterns() as $pattern) {
            if ($this->matchesPattern($filePath, $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if name matches a glob pattern
     *
     * @param string $name Name to check
     * @param string $pattern Glob pattern
     *
     * @return bool
     */
    private function matchesPattern($name, $pattern)
    {
        // Convert glob to regex
        $regex = '/^' . str_replace(
            array('\\*\\*', '\\*', '\\?'),
            array('.*', '[^\\\\]*', '.'),
            preg_quote($pattern, '/')
        ) . '$/';

        return preg_match($regex, $name) === 1;
    }
}
