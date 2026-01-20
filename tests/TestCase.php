<?php
/**
 * Base TestCase with PHPUnit version compatibility
 */

namespace PhpKnip\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case that provides compatibility layer for different PHPUnit versions
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Asserts that a haystack contains a needle (PHPUnit 9+ compatible)
     *
     * For PHPUnit 9+, this method routes string haystacks to assertStringContainsString
     * and array haystacks to assertContains.
     *
     * @param mixed $needle
     * @param mixed $haystack
     * @param string $message
     */
    public static function assertContains($needle, $haystack, string $message = ''): void
    {
        if (is_string($haystack)) {
            if (method_exists(parent::class, 'assertStringContainsString')) {
                parent::assertStringContainsString($needle, $haystack, $message);
            } else {
                // PHPUnit < 9
                parent::assertContains($needle, $haystack, $message);
            }
        } else {
            parent::assertContains($needle, $haystack, $message);
        }
    }

    /**
     * Asserts that a haystack does not contain a needle (PHPUnit 9+ compatible)
     *
     * @param mixed $needle
     * @param mixed $haystack
     * @param string $message
     */
    public static function assertNotContains($needle, $haystack, string $message = ''): void
    {
        if (is_string($haystack)) {
            if (method_exists(parent::class, 'assertStringNotContainsString')) {
                parent::assertStringNotContainsString($needle, $haystack, $message);
            } else {
                // PHPUnit < 9
                parent::assertNotContains($needle, $haystack, $message);
            }
        } else {
            parent::assertNotContains($needle, $haystack, $message);
        }
    }

    /**
     * Asserts that a variable is of type array (PHPUnit 8+ compatible)
     *
     * @param mixed $actual
     * @param string $message
     */
    public static function assertIsArray($actual, string $message = ''): void
    {
        if (method_exists(parent::class, 'assertIsArray')) {
            parent::assertIsArray($actual, $message);
        } else {
            // PHPUnit < 8
            parent::assertInternalType('array', $actual, $message);
        }
    }
}
