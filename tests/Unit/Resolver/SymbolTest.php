<?php
/**
 * Symbol Tests
 */

namespace PhpKnip\Tests\Unit\Resolver;

use PhpKnip\Tests\TestCase;
use PhpKnip\Resolver\Symbol;

class SymbolTest extends TestCase
{
    public function testExtractShortNameFromFqn()
    {
        $this->assertEquals('MyClass', Symbol::extractShortName('App\\Models\\MyClass'));
    }

    public function testExtractShortNameWithoutNamespace()
    {
        $this->assertEquals('MyClass', Symbol::extractShortName('MyClass'));
    }

    public function testExtractShortNameWithDeeplyNested()
    {
        $this->assertEquals('Handler', Symbol::extractShortName('App\\Http\\Controllers\\Api\\V2\\Handler'));
    }
}
