<?php
/**
 * AnalyzeCommand Tests
 */

namespace PhpKnip\Tests\Unit\Command;

use PhpKnip\Tests\TestCase;
use PhpKnip\Command\AnalyzeCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class AnalyzeCommandTest extends TestCase
{
    /**
     * @var CommandTester
     */
    private $commandTester;

    protected function setUp(): void
    {
        $application = new Application();
        $application->add(new AnalyzeCommand());
        $command = $application->find('analyze');
        $this->commandTester = new CommandTester($command);
    }

    public function testUnsupportedPhpVersionThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported PHP version "3.0"');

        $this->commandTester->execute(array(
            'path' => '.',
            '--php-version' => '3.0',
        ));
    }

    public function testUnsupportedPhpVersionMessageContainsSupportedVersions()
    {
        try {
            $this->commandTester->execute(array(
                'path' => '.',
                '--php-version' => '9.9',
            ));
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContains('4.4', $e->getMessage());
            $this->assertStringContains('5.6', $e->getMessage());
            $this->assertStringContains('8.3', $e->getMessage());
        }
    }

    public function testValidPhpVersionIsAccepted()
    {
        // Should not throw - use a minimal path to avoid long execution
        $tmpDir = sys_get_temp_dir() . '/php_knip_test_empty_' . uniqid();
        mkdir($tmpDir);

        try {
            $this->commandTester->execute(array(
                'path' => $tmpDir,
                '--php-version' => '4.4',
            ));
            // No exception means validation passed
            $this->assertTrue(true);
        } finally {
            rmdir($tmpDir);
        }
    }

    public function testAutoPhpVersionIsAccepted()
    {
        $tmpDir = sys_get_temp_dir() . '/php_knip_test_empty_' . uniqid();
        mkdir($tmpDir);

        try {
            $this->commandTester->execute(array(
                'path' => $tmpDir,
                '--php-version' => 'auto',
            ));
            $this->assertTrue(true);
        } finally {
            rmdir($tmpDir);
        }
    }
}
