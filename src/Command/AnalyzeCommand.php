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
use PhpKnip\Config\ConfigLoader;

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
                'Output format (text, json, xml, junit, github, csv, html)',
                'text'
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
                'parallel',
                'j',
                InputOption::VALUE_REQUIRED,
                'Number of parallel workers',
                '1'
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
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>PHP-Knip - Dead Code Detector</info>');
        $output->writeln('');

        $path = $input->getArgument('path');
        $configFile = $input->getOption('config');

        // Load configuration
        $configLoader = new ConfigLoader();
        $config = $configLoader->load($configFile, $path);

        // Merge CLI options into config
        $config = $this->mergeCliOptions($config, $input);

        $output->writeln(sprintf('Analyzing: <comment>%s</comment>', realpath($path) ?: $path));
        $output->writeln(sprintf('Encoding: <comment>%s</comment>', $config['encoding']));
        $output->writeln('');

        // TODO: Implement actual analysis
        $output->writeln('<comment>Analysis not yet implemented. Phase 0 setup complete.</comment>');

        return 0;
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

        if ($input->getOption('format')) {
            $config['output']['format'] = $input->getOption('format');
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
