<?php
/**
 * PHP-Knip Application
 *
 * Main application class for the dead code detector
 */

namespace PhpKnip;

use Symfony\Component\Console\Application as ConsoleApplication;
use PhpKnip\Command\AnalyzeCommand;

/**
 * Main application entry point
 */
class Application
{
    const VERSION = '0.1.0-dev';
    const NAME = 'php-knip';

    /**
     * @var ConsoleApplication
     */
    private $console;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->console = new ConsoleApplication(self::NAME, self::VERSION);
        $this->registerCommands();
    }

    /**
     * Register available commands
     *
     * @return void
     */
    private function registerCommands()
    {
        $this->console->add(new AnalyzeCommand());
        $this->console->setDefaultCommand('analyze', true);
    }

    /**
     * Run the application
     *
     * @return int Exit code
     */
    public function run()
    {
        return $this->console->run();
    }
}
