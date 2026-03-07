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

    /**
     * @var string|null
     */
    private $tmpDir;

    protected function setUp(): void
    {
        $application = new Application();
        $application->add(new AnalyzeCommand());
        $command = $application->find('analyze');
        $this->commandTester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        if ($this->tmpDir !== null && is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
            $this->tmpDir = null;
        }
    }

    private function createTmpDir()
    {
        $this->tmpDir = sys_get_temp_dir() . '/php_knip_test_empty_' . uniqid();
        mkdir($this->tmpDir);

        return $this->tmpDir;
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

    /**
     * @dataProvider validVersionProvider
     */
    public function testValidPhpVersionIsAccepted($version)
    {
        $tmpDir = $this->createTmpDir();

        $this->commandTester->execute(array(
            'path' => $tmpDir,
            '--php-version' => $version,
        ));
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public static function validVersionProvider()
    {
        return array(
            'PHP 4.4' => array('4.4'),
            'PHP 5.6' => array('5.6'),
            'PHP 7.4' => array('7.4'),
            'PHP 8.3' => array('8.3'),
        );
    }

    public function testAutoPhpVersionIsAccepted()
    {
        $tmpDir = $this->createTmpDir();

        $this->commandTester->execute(array(
            'path' => $tmpDir,
            '--php-version' => 'auto',
        ));
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    /**
     * @dataProvider unsupportedVersionProvider
     */
    public function testUnsupportedBoundaryVersionsThrowException($version)
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->commandTester->execute(array(
            'path' => '.',
            '--php-version' => $version,
        ));
    }

    public static function unsupportedVersionProvider()
    {
        return array(
            'below minimum' => array('4.3'),
            'gap version' => array('5.5'),
            'non-existent major' => array('6.0'),
            'above maximum' => array('8.4'),
        );
    }
}
