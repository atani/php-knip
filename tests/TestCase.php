<?php
/**
 * Base TestCase with PHPUnit and php-parser version compatibility
 */

namespace PhpKnip\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use PhpParser\ParserFactory;

/**
 * Base test case that provides compatibility layer for different PHPUnit and php-parser versions
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Create a PHP parser compatible with both php-parser v4 and v5
     *
     * @return \PhpParser\Parser
     */
    protected function createParser()
    {
        $factory = new ParserFactory();

        // php-parser v5 uses createForNewestSupportedVersion()
        // php-parser v4 uses create(ParserFactory::PREFER_PHP7)
        if (method_exists($factory, 'createForNewestSupportedVersion')) {
            return $factory->createForNewestSupportedVersion();
        }

        return $factory->create(ParserFactory::PREFER_PHP7);
    }

    /**
     * Assert that a string contains a substring (PHPUnit version compatible)
     *
     * @param string $needle
     * @param string $haystack
     * @param string $message
     */
    protected static function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        if (method_exists(parent::class, 'assertStringContainsString')) {
            parent::assertStringContainsString($needle, $haystack, $message);
        } else {
            // PHPUnit < 9
            parent::assertContains($needle, $haystack, $message);
        }
    }

    /**
     * Assert that a string does not contain a substring (PHPUnit version compatible)
     *
     * @param string $needle
     * @param string $haystack
     * @param string $message
     */
    protected static function assertStringNotContains(string $needle, string $haystack, string $message = ''): void
    {
        if (method_exists(parent::class, 'assertStringNotContainsString')) {
            parent::assertStringNotContainsString($needle, $haystack, $message);
        } else {
            // PHPUnit < 9
            parent::assertNotContains($needle, $haystack, $message);
        }
    }

    /**
     * Assert that an array contains a value
     *
     * @param mixed $needle
     * @param array $haystack
     * @param string $message
     */
    protected static function assertArrayContainsValue($needle, array $haystack, string $message = ''): void
    {
        parent::assertContains($needle, $haystack, $message);
    }
}
