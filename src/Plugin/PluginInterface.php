<?php
/**
 * Plugin Interface
 *
 * Defines the contract for framework-specific plugins
 */

namespace PhpKnip\Plugin;

use PhpKnip\Resolver\SymbolTable;
use PhpKnip\Resolver\Reference;

/**
 * Interface for framework-specific plugins
 *
 * Plugins can:
 * - Detect if a framework is in use
 * - Register additional symbols (e.g., Service Providers)
 * - Mark symbols as used based on framework conventions
 * - Provide ignore patterns specific to the framework
 */
interface PluginInterface
{
    /**
     * Get plugin name
     *
     * @return string Unique plugin identifier
     */
    public function getName();

    /**
     * Get plugin description
     *
     * @return string Human-readable description
     */
    public function getDescription();

    /**
     * Check if this plugin should be activated for the project
     *
     * This method examines the project structure to determine
     * if the framework is in use (e.g., checks for artisan file,
     * specific dependencies in composer.json, etc.)
     *
     * @param string $projectRoot Project root directory
     * @param array $composerData Parsed composer.json data
     *
     * @return bool True if plugin should be activated
     */
    public function isApplicable($projectRoot, array $composerData);

    /**
     * Get priority for this plugin
     *
     * Higher priority plugins are processed first.
     * Default should be 0.
     *
     * @return int Priority value
     */
    public function getPriority();

    /**
     * Get symbol patterns that should be ignored
     *
     * Returns patterns for symbols that are used implicitly
     * by the framework and should not be flagged as unused.
     *
     * @return array<string> Glob patterns for symbols to ignore
     */
    public function getIgnorePatterns();

    /**
     * Get file patterns that should be ignored
     *
     * Returns patterns for files that should be excluded
     * from analysis (e.g., framework-generated files).
     *
     * @return array<string> Glob patterns for files to ignore
     */
    public function getIgnoreFilePatterns();

    /**
     * Process symbols after collection
     *
     * Called after all symbols have been collected.
     * Plugins can mark symbols as "framework-used" here.
     *
     * @param SymbolTable $symbolTable Collected symbols
     * @param string $projectRoot Project root directory
     *
     * @return void
     */
    public function processSymbols(SymbolTable $symbolTable, $projectRoot);

    /**
     * Get additional references from framework configuration
     *
     * Plugins can analyze framework-specific files (routes, config, etc.)
     * and return additional references that should be considered.
     *
     * @param string $projectRoot Project root directory
     *
     * @return array<Reference> Additional references
     */
    public function getAdditionalReferences($projectRoot);

    /**
     * Get entry points for the framework
     *
     * Entry points are classes/functions that are called by the framework
     * but may not have explicit references in user code.
     *
     * @param string $projectRoot Project root directory
     *
     * @return array<string> FQN of entry point classes/functions
     */
    public function getEntryPoints($projectRoot);
}
