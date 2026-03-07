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

    public function testGetShortNameUsesFullyQualifiedName()
    {
        $symbol = new Symbol(Symbol::TYPE_CLASS, 'MyClass');
        $symbol->setNamespace('App\\Models');
        $symbol->setFullyQualifiedName('App\\Models\\MyClass');

        $this->assertEquals('MyClass', $symbol->getShortName());
    }

    public function testGetShortNameFallsBackToName()
    {
        // When fullyQualifiedName is null, getShortName should use name
        $symbol = new Symbol(Symbol::TYPE_CLASS, 'SimpleClass');

        $this->assertEquals('SimpleClass', $symbol->getShortName());
    }
}
