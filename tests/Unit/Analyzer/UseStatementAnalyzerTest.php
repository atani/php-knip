<?php
/**
 * UseStatementAnalyzer Tests
 */

namespace PhpKnip\Tests\Unit\Analyzer;

use PHPUnit\Framework\TestCase;
use PhpKnip\Analyzer\UseStatementAnalyzer;
use PhpKnip\Analyzer\AnalysisContext;
use PhpKnip\Analyzer\Issue;
use PhpKnip\Resolver\SymbolTable;
use PhpKnip\Resolver\Reference;

class UseStatementAnalyzerTest extends TestCase
{
    /**
     * @var UseStatementAnalyzer
     */
    private $analyzer;

    protected function setUp()
    {
        $this->analyzer = new UseStatementAnalyzer();
    }

    public function testGetName()
    {
        $this->assertEquals('use-statement-analyzer', $this->analyzer->getName());
    }

    public function testUnusedUseStatementIsDetected()
    {
        $symbolTable = new SymbolTable();
        $context = new AnalysisContext($symbolTable, array());

        $context->setUseStatements('/src/Consumer.php', array(
            array(
                'fqn' => 'App\\UnusedClass',
                'alias' => 'UnusedClass',
                'line' => 5,
                'type' => 'class',
            ),
        ));

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(1, $issues);
        $this->assertEquals(Issue::TYPE_UNUSED_USE, $issues[0]->getType());
        $this->assertEquals('App\\UnusedClass', $issues[0]->getSymbolName());
    }

    public function testUsedUseStatementIsNotFlagged()
    {
        $symbolTable = new SymbolTable();

        $references = array(
            Reference::createNew('UsedClass', '/src/Consumer.php', 20),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $context->setUseStatements('/src/Consumer.php', array(
            array(
                'fqn' => 'App\\UsedClass',
                'alias' => 'UsedClass',
                'line' => 5,
                'type' => 'class',
            ),
        ));

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testUseStatementWithAliasUsedIsNotFlagged()
    {
        $symbolTable = new SymbolTable();

        $references = array(
            Reference::createNew('Repo', '/src/Consumer.php', 20),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $context->setUseStatements('/src/Consumer.php', array(
            array(
                'fqn' => 'App\\Repository\\UserRepository',
                'alias' => 'Repo',
                'line' => 5,
                'type' => 'class',
            ),
        ));

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testUseStatementUsedInTypeHintIsNotFlagged()
    {
        $symbolTable = new SymbolTable();

        $references = array(
            Reference::createTypeHint('ServiceInterface', '/src/Consumer.php', 15),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $context->setUseStatements('/src/Consumer.php', array(
            array(
                'fqn' => 'App\\Contracts\\ServiceInterface',
                'alias' => 'ServiceInterface',
                'line' => 5,
                'type' => 'class',
            ),
        ));

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testUseStatementUsedInStaticCallIsNotFlagged()
    {
        $symbolTable = new SymbolTable();

        $references = array(
            Reference::createStaticCall('Helper', 'format', '/src/Consumer.php', 20),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $context->setUseStatements('/src/Consumer.php', array(
            array(
                'fqn' => 'App\\Utils\\Helper',
                'alias' => 'Helper',
                'line' => 5,
                'type' => 'class',
            ),
        ));

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testMultipleUseStatementsPartiallyUsed()
    {
        $symbolTable = new SymbolTable();

        $references = array(
            Reference::createNew('UsedOne', '/src/Consumer.php', 20),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $context->setUseStatements('/src/Consumer.php', array(
            array(
                'fqn' => 'App\\UsedOne',
                'alias' => 'UsedOne',
                'line' => 5,
                'type' => 'class',
            ),
            array(
                'fqn' => 'App\\UnusedTwo',
                'alias' => 'UnusedTwo',
                'line' => 6,
                'type' => 'class',
            ),
        ));

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(1, $issues);
        $this->assertEquals('App\\UnusedTwo', $issues[0]->getSymbolName());
    }

    public function testIgnoredUseStatementIsNotFlagged()
    {
        $symbolTable = new SymbolTable();

        $config = array(
            'ignore' => array(
                'symbols' => array('App\\Testing\\*'),
            ),
        );

        $context = new AnalysisContext($symbolTable, array(), $config);

        $context->setUseStatements('/src/Consumer.php', array(
            array(
                'fqn' => 'App\\Testing\\MockHelper',
                'alias' => 'MockHelper',
                'line' => 5,
                'type' => 'class',
            ),
        ));

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testUseStatementInDifferentFilesAreSeparate()
    {
        $symbolTable = new SymbolTable();

        // Reference in file A uses Helper
        $references = array(
            Reference::createNew('Helper', '/src/FileA.php', 20),
        );

        $context = new AnalysisContext($symbolTable, $references);

        // File A imports Helper (used)
        $context->setUseStatements('/src/FileA.php', array(
            array(
                'fqn' => 'App\\Helper',
                'alias' => 'Helper',
                'line' => 5,
                'type' => 'class',
            ),
        ));

        // File B also imports Helper (unused - no reference in this file)
        $context->setUseStatements('/src/FileB.php', array(
            array(
                'fqn' => 'App\\Helper',
                'alias' => 'Helper',
                'line' => 5,
                'type' => 'class',
            ),
        ));

        $issues = $this->analyzer->analyze($context);

        // Only File B's import should be flagged
        $this->assertCount(1, $issues);
        $this->assertEquals('/src/FileB.php', $issues[0]->getFilePath());
    }

    public function testUseImportReferenceIsNotCountedAsUsage()
    {
        $symbolTable = new SymbolTable();

        // Only a use import reference (the use statement itself)
        $references = array(
            Reference::createUseImport('App\\SomeClass', 'SomeClass', '/src/Consumer.php', 5),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $context->setUseStatements('/src/Consumer.php', array(
            array(
                'fqn' => 'App\\SomeClass',
                'alias' => 'SomeClass',
                'line' => 5,
                'type' => 'class',
            ),
        ));

        $issues = $this->analyzer->analyze($context);

        // Should be flagged because the only reference is the use statement itself
        $this->assertCount(1, $issues);
    }

    public function testUseStatementUsedViaFQNIsNotFlagged()
    {
        $symbolTable = new SymbolTable();

        $references = array(
            Reference::createNew('App\\SomeClass', '/src/Consumer.php', 20),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $context->setUseStatements('/src/Consumer.php', array(
            array(
                'fqn' => 'App\\SomeClass',
                'alias' => 'SomeClass',
                'line' => 5,
                'type' => 'class',
            ),
        ));

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(0, $issues);
    }

    public function testIssueContainsFileAndLineInfo()
    {
        $symbolTable = new SymbolTable();
        $context = new AnalysisContext($symbolTable, array());

        $context->setUseStatements('/src/MyFile.php', array(
            array(
                'fqn' => 'App\\Unused',
                'alias' => 'Unused',
                'line' => 42,
                'type' => 'class',
            ),
        ));

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(1, $issues);
        $this->assertEquals('/src/MyFile.php', $issues[0]->getFilePath());
        $this->assertEquals(42, $issues[0]->getLine());
    }

    public function testIssueSeverityIsWarning()
    {
        $symbolTable = new SymbolTable();
        $context = new AnalysisContext($symbolTable, array());

        $context->setUseStatements('/src/Consumer.php', array(
            array(
                'fqn' => 'App\\Unused',
                'alias' => 'Unused',
                'line' => 5,
                'type' => 'class',
            ),
        ));

        $issues = $this->analyzer->analyze($context);

        $this->assertCount(1, $issues);
        $this->assertEquals(Issue::SEVERITY_WARNING, $issues[0]->getSeverity());
    }

    public function testQualifiedNameUsageIsDetected()
    {
        $symbolTable = new SymbolTable();

        // Using Foo\Bar where Foo is imported
        $references = array(
            Reference::createNew('Foo\\Bar', '/src/Consumer.php', 20),
        );

        $context = new AnalysisContext($symbolTable, $references);

        $context->setUseStatements('/src/Consumer.php', array(
            array(
                'fqn' => 'App\\Foo',
                'alias' => 'Foo',
                'line' => 5,
                'type' => 'class',
            ),
        ));

        $issues = $this->analyzer->analyze($context);

        // Foo is used as part of qualified name Foo\Bar
        $this->assertCount(0, $issues);
    }
}
