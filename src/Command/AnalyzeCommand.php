<?php
/**
 * Analyze Command
 *
 * Main CLI command for running dead code analysis
 */

namespace PhpKnip\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use PhpParser\NodeTraverser;
use PhpKnip\Config\ConfigLoader;
use PhpKnip\Parser\AstBuilder;
use PhpKnip\Resolver\SymbolCollector;
use PhpKnip\Resolver\SymbolTable;
use PhpKnip\Resolver\ReferenceCollector;
use PhpKnip\Analyzer\AnalysisContext;
use PhpKnip\Analyzer\ClassAnalyzer;
use PhpKnip\Analyzer\FunctionAnalyzer;
use PhpKnip\Analyzer\UseStatementAnalyzer;
use PhpKnip\Analyzer\MethodAnalyzer;
use PhpKnip\Analyzer\ConstantAnalyzer;
use PhpKnip\Analyzer\PropertyAnalyzer;
use PhpKnip\Analyzer\FileAnalyzer;
use PhpKnip\Reporter\TextReporter;
use PhpKnip\Reporter\JsonReporter;
use PhpKnip\Reporter\XmlReporter;
use PhpKnip\Reporter\JunitReporter;
use PhpKnip\Reporter\GithubReporter;
use PhpKnip\Reporter\CsvReporter;
use PhpKnip\Reporter\HtmlReporter;
use PhpKnip\Plugin\PluginManager;
use PhpKnip\Cache\CacheManager;
use PhpKnip\Resolver\Symbol;
use PhpKnip\Resolver\Reference;
use PhpKnip\Fixer\FixerManager;

/**
 * Command to analyze PHP code for unused elements
 */
class AnalyzeCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'analyze';

    /**
     * Configure the command
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('analyze')
            ->setDescription('Analyze PHP code for unused files, classes, functions, and more')
            ->setHelp('This command analyzes your PHP codebase and reports unused code elements.')
            ->addArgument(
                'path',
                InputArgument::OPTIONAL,
                'Path to analyze',
                '.'
            )
            ->addOption(
                'config',
                'c',
                InputOption::VALUE_REQUIRED,
                'Path to configuration file',
                'php-knip.json'
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_REQUIRED,
                'Output format (text, json, xml, junit, github, csv, html)'
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output file path (defaults to stdout)'
            )
            ->addOption(
                'only',
                null,
                InputOption::VALUE_REQUIRED,
                'Only run specified rules (comma-separated)'
            )
            ->addOption(
                'exclude',
                null,
                InputOption::VALUE_REQUIRED,
                'Exclude specified rules (comma-separated)'
            )
            ->addOption(
                'min-severity',
                null,
                InputOption::VALUE_REQUIRED,
                'Minimum severity level (error, warning, info)',
                'info'
            )
            ->addOption(
                'strict',
                null,
                InputOption::VALUE_NONE,
                'Exit with code 1 if any errors are found'
            )
            ->addOption(
                'fix',
                null,
                InputOption::VALUE_NONE,
                'Automatically fix issues where possible'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Preview fixes without applying them (use with --fix)'
            )
            ->addOption(
                'php-version',
                null,
                InputOption::VALUE_REQUIRED,
                'PHP version of the code being analyzed',
                'auto'
            )
            ->addOption(
                'encoding',
                'e',
                InputOption::VALUE_REQUIRED,
                'Source file encoding (auto, utf-8, euc-jp, shift_jis)',
                'auto'
            )
            ->addOption(
                'no-cache',
                null,
                InputOption::VALUE_NONE,
                'Disable caching'
            )
            ->addOption(
                'clear-cache',
                null,
                InputOption::VALUE_NONE,
                'Clear cache before analyzing'
            )
            ->addOption(
                'parallel',
                'j',
                InputOption::VALUE_REQUIRED,
                'Number of parallel workers',
                '1'
            )
            ->addOption(
                'no-colors',
                null,
                InputOption::VALUE_NONE,
                'Disable colored output'
            );
    }

    /**
     * Execute the command
     *
     * @param InputInterface  $input  Input interface
     * @param OutputInterface $output Output interface
     *
     * @return int Exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $path = $input->getArgument('path');
        $configFile = $input->getOption('config');
        $realPath = realpath($path);

        if ($realPath === false) {
            $output->writeln(sprintf('<error>Path not found: %s</error>', $path));
            return 1;
        }

        // Load configuration
        $configLoader = new ConfigLoader();
        $config = $configLoader->load($configFile, $path);

        // Merge CLI options into config
        $config = $this->mergeCliOptions($config, $input);

        // Check if progress output is enabled
        $showProgress = isset($config['output']['show_progress']) ? $config['output']['show_progress'] : true;

        if ($showProgress) {
            $output->writeln('<info>PHP-Knip - Dead Code Detector</info>');
            $output->writeln('');
            $output->writeln(sprintf('Analyzing: <comment>%s</comment>', $realPath));
            $output->writeln(sprintf('Encoding: <comment>%s</comment>', $config['encoding']));
            $output->writeln('');
        }

        // Find PHP files
        if ($showProgress) {
            $output->write('Finding PHP files... ');
        }
        $files = $this->findPhpFiles($realPath, $config);
        if ($showProgress) {
            $output->writeln(sprintf('<info>%d files found</info>', count($files)));
        }

        if (empty($files)) {
            $output->writeln('<comment>No PHP files found to analyze.</comment>');
            return 0;
        }

        // Initialize cache manager
        $cacheDir = $realPath . '/' . (isset($config['cache']['directory']) ? $config['cache']['directory'] : '.php-knip-cache');
        $cacheEnabled = isset($config['cache']['enabled']) ? $config['cache']['enabled'] : true;
        $cacheManager = new CacheManager($cacheDir, $cacheEnabled);

        // Clear cache if requested
        if ($input->getOption('clear-cache')) {
            $cacheManager->clear();
            if ($showProgress) {
                $output->writeln('<comment>Cache cleared.</comment>');
            }
        }

        // Phase 1: Parse files and collect symbols
        if ($showProgress) {
            $output->write('Parsing files... ');
        }
        $astBuilder = new AstBuilder(
            isset($config['php_version']) ? $config['php_version'] : 'auto',
            $config['encoding'] !== 'auto' ? $config['encoding'] : null
        );

        $symbolTable = new SymbolTable();
        $symbolCollector = new SymbolCollector($symbolTable);
        $allReferences = array();
        $allUseStatements = array();
        $parsedCount = 0;
        $errorCount = 0;
        $cacheHits = 0;

        foreach ($files as $filePath) {
            // Try to use cache
            if ($cacheManager->isValid($filePath)) {
                $cached = $cacheManager->get($filePath);
                if ($cached !== null) {
                    // Restore symbols from cache
                    if (isset($cached['symbols'])) {
                        foreach ($cached['symbols'] as $symbolData) {
                            $symbol = Symbol::fromArray($symbolData);
                            $symbolTable->add($symbol);
                        }
                    }

                    // Restore references from cache
                    if (isset($cached['references'])) {
                        foreach ($cached['references'] as $refData) {
                            $allReferences[] = Reference::fromArray($refData);
                        }
                    }

                    // Restore use statements from cache
                    if (isset($cached['useStatements'])) {
                        $allUseStatements[$filePath] = $cached['useStatements'];
                    }

                    $parsedCount++;
                    $cacheHits++;
                    continue;
                }
            }

            // Parse file
            $ast = $astBuilder->buildFromFile($filePath);

            if ($ast === null) {
                $errorCount++;
                continue;
            }

            // Collect symbols
            $symbolCollector->setCurrentFile($filePath);
            $symbolCollector->reset();
            $traverser = new NodeTraverser();
            $traverser->addVisitor($symbolCollector);
            $traverser->traverse($ast);

            // Get collected symbols for this file
            $fileSymbols = array();
            foreach ($symbolTable->getAll() as $symbol) {
                if ($symbol->getFilePath() === $filePath) {
                    $fileSymbols[] = $symbol->toArray();
                }
            }

            // Collect references
            $referenceCollector = new ReferenceCollector($filePath);
            $traverser2 = new NodeTraverser();
            $traverser2->addVisitor($referenceCollector);
            $traverser2->traverse($ast);

            $fileReferences = $referenceCollector->getReferences();
            $fileUseStatements = $referenceCollector->getUseStatements();

            $allReferences = array_merge($allReferences, $fileReferences);
            $allUseStatements[$filePath] = $fileUseStatements;

            // Save to cache
            $cacheManager->set($filePath, array(
                'symbols' => $fileSymbols,
                'references' => array_map(function ($ref) {
                    return $ref->toArray();
                }, $fileReferences),
                'useStatements' => $fileUseStatements,
            ));

            $parsedCount++;
        }

        // Save cache metadata
        $cacheManager->saveMetadata();

        if ($showProgress) {
            $cacheInfo = $cacheEnabled && $cacheHits > 0 ? sprintf(' (%d from cache)', $cacheHits) : '';
            $output->writeln(sprintf('<info>%d parsed</info>%s (%d errors)', $parsedCount, $cacheInfo, $errorCount));
        }

        // Show parse errors if any
        if ($astBuilder->hasErrors() && $output->isVerbose()) {
            $output->writeln('');
            $output->writeln('<comment>Parse errors:</comment>');
            foreach ($astBuilder->getErrors() as $error) {
                $output->writeln(sprintf('  %s:%d - %s', $error['file'], $error['line'], $error['message']));
            }
        }

        // Phase 2: Analysis
        if ($showProgress) {
            $output->write('Analyzing... ');
        }

        // Set up plugin manager
        $pluginManager = new PluginManager();
        $pluginManager->discoverBuiltinPlugins();

        // Read composer.json if available
        $composerData = array();
        $composerPath = $realPath . '/composer.json';
        if (file_exists($composerPath)) {
            $composerContent = file_get_contents($composerPath);
            if ($composerContent !== false) {
                $composerData = json_decode($composerContent, true);
                if (!is_array($composerData)) {
                    $composerData = array();
                }
            }
        }

        // Activate applicable plugins
        $framework = isset($config['framework']) ? $config['framework'] : 'auto';
        $pluginManager->activate($realPath, $composerData, $framework);

        // Show active plugins
        $activePlugins = $pluginManager->getActivePluginNames();
        if (!empty($activePlugins) && $output->isVerbose()) {
            $output->writeln('');
            $output->writeln(sprintf('Active plugins: <info>%s</info>', implode(', ', $activePlugins)));
        }

        $context = new AnalysisContext($symbolTable, $allReferences, $config);
        $context->setPluginManager($pluginManager);

        // Set use statements
        foreach ($allUseStatements as $file => $uses) {
            $context->setUseStatements($file, $uses);
        }

        $issues = array();

        // Run analyzers
        $analyzers = array(
            new ClassAnalyzer(),
            new FunctionAnalyzer(),
            new UseStatementAnalyzer(),
            new MethodAnalyzer(),
            new ConstantAnalyzer(),
            new PropertyAnalyzer(),
            new FileAnalyzer(),
        );

        foreach ($analyzers as $analyzer) {
            $analyzerIssues = $analyzer->analyze($context);
            $issues = array_merge($issues, $analyzerIssues);
        }

        if ($showProgress) {
            $output->writeln(sprintf('<info>%d issues found</info>', count($issues)));
            $output->writeln('');
        }

        // Phase 3: Auto-fix (if requested)
        $fixMode = $input->getOption('fix');
        $dryRun = $input->getOption('dry-run');

        if ($fixMode && count($issues) > 0) {
            $fixerManager = new FixerManager();
            $fixerManager->registerBuiltinFixers();
            $fixerManager->setDryRun($dryRun);

            if ($showProgress) {
                $output->writeln('<info>Running auto-fix...</info>');
                if ($dryRun) {
                    $output->writeln('<comment>(dry-run mode - no files will be modified)</comment>');
                }
                $output->writeln('');
            }

            $fixerManager->fixIssues($issues);
            $summary = $fixerManager->getSummary();

            if ($summary['successful'] > 0) {
                if (!$dryRun) {
                    $written = $fixerManager->applyFixes();
                    $output->writeln(sprintf(
                        '<info>Fixed %d issues in %d files</info>',
                        $summary['successful'],
                        count($written)
                    ));
                } else {
                    $output->writeln(sprintf(
                        '<info>Would fix %d issues in %d files (dry-run)</info>',
                        $summary['successful'],
                        $summary['files_modified']
                    ));
                }

                // Show what was fixed
                foreach ($fixerManager->getSuccessfulResults() as $result) {
                    $issue = $result->getIssue();
                    $prefix = $dryRun ? '  Would remove: ' : '  Removed: ';
                    $output->writeln(sprintf(
                        '%s<comment>%s</comment> at %s:%d',
                        $prefix,
                        $issue->getSymbolName(),
                        basename($issue->getFilePath()),
                        $issue->getLine()
                    ));
                }

                $output->writeln('');

                // Remove fixed issues from the report
                if (!$dryRun) {
                    $fixedIssues = array_map(function ($result) {
                        return $result->getIssue();
                    }, $fixerManager->getSuccessfulResults());

                    $issues = array_filter($issues, function ($issue) use ($fixedIssues) {
                        return !in_array($issue, $fixedIssues, true);
                    });
                    $issues = array_values($issues);
                }
            }

            if ($summary['skipped'] > 0 && $showProgress) {
                $output->writeln(sprintf(
                    '<comment>Skipped %d issues (no fixer available)</comment>',
                    $summary['skipped']
                ));
                $output->writeln('');
            }
        }

        // Phase 4: Report
        $showColors = !$input->getOption('no-colors') && $output->isDecorated();
        $reporter = $this->getReporter($config['output']['format']);
        $reportOptions = array(
            'colors' => $showColors,
            'basePath' => $realPath,
            'groupBy' => 'type',
        );

        $report = $reporter->report($issues, $reportOptions);

        // Output to file or stdout
        $outputFile = $input->getOption('output');
        if ($outputFile) {
            file_put_contents($outputFile, $report);
            $output->writeln(sprintf('Report written to: <info>%s</info>', $outputFile));
        } else {
            $output->write($report);
        }

        // Return code
        if ($input->getOption('strict') && count($issues) > 0) {
            return 1;
        }

        return 0;
    }

    /**
     * Find PHP files to analyze
     *
     * @param string $path Base path
     * @param array $config Configuration
     *
     * @return array File paths
     */
    private function findPhpFiles($path, array $config)
    {
        $finder = new Finder();
        $finder->files()->name('*.php')->in($path);

        // Apply exclude patterns (these always apply)
        $excludePatterns = isset($config['exclude']) ? $config['exclude'] : array();
        $defaultExcludes = array('vendor', 'node_modules', '.git', 'cache', 'storage', 'tmp', 'temp');
        $allExcludes = array_merge($defaultExcludes, $excludePatterns);

        // Also exclude from ignore.paths if specified
        if (isset($config['ignore']['paths'])) {
            foreach ($config['ignore']['paths'] as $ignorePath) {
                // Convert glob to directory name
                $dirName = str_replace(array('/**', '/*', '*'), '', $ignorePath);
                if (!empty($dirName)) {
                    $allExcludes[] = $dirName;
                }
            }
        }

        foreach ($allExcludes as $exclude) {
            $finder->notPath($exclude);
        }

        $files = array();
        foreach ($finder as $file) {
            $files[] = $file->getRealPath();
        }

        return $files;
    }

    /**
     * Convert glob pattern to regex
     *
     * @param string $glob Glob pattern
     *
     * @return string Regex pattern
     */
    private function globToRegex($glob)
    {
        // Simple conversion for common patterns
        $regex = str_replace(
            array('**/', '*', '?'),
            array('(.*/)?', '[^/]*', '[^/]'),
            $glob
        );
        return $regex;
    }

    /**
     * Get reporter for format
     *
     * @param string $format Format name
     *
     * @return \PhpKnip\Reporter\ReporterInterface
     */
    private function getReporter($format)
    {
        switch ($format) {
            case 'json':
                return new JsonReporter();
            case 'xml':
                return new XmlReporter();
            case 'junit':
                return new JunitReporter();
            case 'github':
                return new GithubReporter();
            case 'csv':
                return new CsvReporter();
            case 'html':
                return new HtmlReporter();
            case 'text':
            default:
                return new TextReporter();
        }
    }

    /**
     * Merge CLI options into configuration
     *
     * @param array          $config Configuration array
     * @param InputInterface $input  Input interface
     *
     * @return array Merged configuration
     */
    private function mergeCliOptions(array $config, InputInterface $input)
    {
        if ($input->getOption('encoding') !== 'auto') {
            $config['encoding'] = $input->getOption('encoding');
        }

        if ($input->getOption('php-version') !== 'auto') {
            $config['php_version'] = $input->getOption('php-version');
        }

        // Format: CLI option overrides config, default to 'text'
        $cliFormat = $input->getOption('format');
        if ($cliFormat !== null) {
            $config['output']['format'] = $cliFormat;
        } elseif (!isset($config['output']['format'])) {
            $config['output']['format'] = 'text';
        }

        if ($input->getOption('strict')) {
            $config['strict'] = true;
        }

        if ($input->getOption('no-cache')) {
            $config['cache']['enabled'] = false;
        }

        if ($input->getOption('parallel')) {
            $config['parallel'] = (int) $input->getOption('parallel');
        }

        return $config;
    }
}
