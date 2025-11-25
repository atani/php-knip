<?php
/**
 * Encoding Integration Tests
 *
 * Tests full analysis flow with different encodings
 */

namespace PhpKnip\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PhpKnip\Parser\AstBuilder;
use PhpKnip\Parser\Encoding\EncodingDetector;
use PhpKnip\Parser\Encoding\EncodingConverter;
use PhpKnip\Resolver\SymbolCollector;
use PhpKnip\Resolver\ReferenceCollector;
use PhpKnip\Resolver\SymbolTable;
use PhpKnip\Analyzer\AnalysisContext;
use PhpKnip\Analyzer\ClassAnalyzer;
use PhpKnip\Analyzer\FunctionAnalyzer;
use PhpKnip\Analyzer\Issue;
use PhpParser\NodeTraverser;

class EncodingIntegrationTest extends TestCase
{
    /**
     * @var AstBuilder
     */
    private $astBuilder;

    /**
     * @var EncodingDetector
     */
    private $encodingDetector;

    /**
     * @var EncodingConverter
     */
    private $encodingConverter;

    protected function setUp()
    {
        $this->encodingDetector = new EncodingDetector();
        $this->encodingConverter = new EncodingConverter($this->encodingDetector);
        $this->astBuilder = new AstBuilder('auto');
    }

    /**
     * Test analysis of UTF-8 file with Japanese identifiers
     */
    public function testUtf8FileWithJapaneseIdentifiers()
    {
        $code = <<<'PHP'
<?php
namespace App;

class 日本語クラス
{
    private $データ;

    public function __construct($データ)
    {
        $this->データ = $データ;
    }

    public function データ取得()
    {
        return $this->データ;
    }
}

class 未使用クラス
{
    public function 何もしない()
    {
    }
}

$obj = new 日本語クラス('テスト');
$obj->データ取得();
PHP;

        $issues = $this->analyzeCode($code, '/test/japanese.php');

        $unusedClasses = $this->filterByType($issues, Issue::TYPE_UNUSED_CLASS);
        $this->assertCount(1, $unusedClasses);
        $this->assertEquals('App\\未使用クラス', $unusedClasses[0]->getSymbolName());
    }

    /**
     * Test analysis of EUC-JP encoded content (simulated)
     */
    public function testEucJpEncodedContent()
    {
        // Original UTF-8 code
        $utf8Code = <<<'PHP'
<?php
namespace Legacy;

class レガシークラス
{
    public function 古いメソッド()
    {
        return '結果';
    }
}

function 使われる関数()
{
    return new レガシークラス();
}

function 使われない関数()
{
    return '未使用';
}

使われる関数();
PHP;

        // Convert to EUC-JP
        if (function_exists('mb_convert_encoding')) {
            $eucjpCode = mb_convert_encoding($utf8Code, 'EUC-JP', 'UTF-8');

            // Detect and convert back
            $detectedEncoding = $this->encodingDetector->detect($eucjpCode);
            $result = $this->encodingConverter->toUtf8($eucjpCode, $detectedEncoding['encoding']);
            $convertedCode = $result['content'];

            $issues = $this->analyzeCode($convertedCode, '/test/legacy.php');

            $unusedFunctions = $this->filterByType($issues, Issue::TYPE_UNUSED_FUNCTION);
            $this->assertCount(1, $unusedFunctions);
            $this->assertContains('使われない関数', $unusedFunctions[0]->getSymbolName());
        } else {
            $this->markTestSkipped('mbstring extension not available');
        }
    }

    /**
     * Test analysis of Shift_JIS encoded content (simulated)
     */
    public function testShiftJisEncodedContent()
    {
        // Original UTF-8 code
        $utf8Code = <<<'PHP'
<?php
namespace Old;

class 古いクラス
{
    public function 処理()
    {
        return '完了';
    }
}

$obj = new 古いクラス();
PHP;

        // Convert to Shift_JIS
        if (function_exists('mb_convert_encoding')) {
            $sjisCode = mb_convert_encoding($utf8Code, 'SJIS', 'UTF-8');

            // Detect and convert back
            $detectedEncoding = $this->encodingDetector->detect($sjisCode);
            $result = $this->encodingConverter->toUtf8($sjisCode, $detectedEncoding['encoding']);
            $convertedCode = $result['content'];

            $issues = $this->analyzeCode($convertedCode, '/test/old.php');

            // No unused classes (all used)
            $unusedClasses = $this->filterByType($issues, Issue::TYPE_UNUSED_CLASS);
            $this->assertCount(0, $unusedClasses);
        } else {
            $this->markTestSkipped('mbstring extension not available');
        }
    }

    /**
     * Test analysis with declare encoding statement
     */
    public function testDeclareEncodingStatement()
    {
        $utf8Code = <<<'PHP'
<?php
declare(encoding='UTF-8');

namespace App;

class エンコード宣言クラス
{
    public function メソッド()
    {
        return 'テスト';
    }
}

$obj = new エンコード宣言クラス();
PHP;

        $issues = $this->analyzeCode($utf8Code, '/test/declare.php');

        // Class is used, should have no issues
        $unusedClasses = $this->filterByType($issues, Issue::TYPE_UNUSED_CLASS);
        $this->assertCount(0, $unusedClasses);
    }

    /**
     * Test UTF-8 BOM handling
     */
    public function testUtf8BomHandling()
    {
        // UTF-8 BOM + code
        $codeWithBom = "\xEF\xBB\xBF" . <<<'PHP'
<?php
namespace App;

class BOMテストクラス
{
    public function メソッド()
    {
        return true;
    }
}

class 未使用BOMクラス {}

$obj = new BOMテストクラス();
PHP;

        // Should handle BOM correctly
        $issues = $this->analyzeCode($codeWithBom, '/test/bom.php');

        $unusedClasses = $this->filterByType($issues, Issue::TYPE_UNUSED_CLASS);
        $this->assertCount(1, $unusedClasses);
        $this->assertContains('未使用BOMクラス', $unusedClasses[0]->getSymbolName());
    }

    /**
     * Test mixed content (Japanese in comments and strings)
     */
    public function testMixedJapaneseContent()
    {
        $code = <<<'PHP'
<?php
namespace App;

/**
 * ユーザーサービスクラス
 * このクラスはユーザー関連の処理を行います
 */
class UserService
{
    /**
     * ユーザー取得
     * @return string ユーザー名
     */
    public function getUser()
    {
        return 'ユーザー名';
    }
}

/**
 * 未使用のヘルパークラス
 */
class UnusedHelper
{
    public function help()
    {
        return '使われません';
    }
}

$service = new UserService();
$service->getUser();
PHP;

        $issues = $this->analyzeCode($code, '/test/mixed.php');

        $unusedClasses = $this->filterByType($issues, Issue::TYPE_UNUSED_CLASS);
        $this->assertCount(1, $unusedClasses);
        $this->assertEquals('App\\UnusedHelper', $unusedClasses[0]->getSymbolName());
    }

    /**
     * Analyze PHP code and return issues
     *
     * @param string $code PHP code
     * @param string $filePath File path
     *
     * @return array<Issue>
     */
    private function analyzeCode($code, $filePath)
    {
        // Handle BOM
        if (substr($code, 0, 3) === "\xEF\xBB\xBF") {
            $code = substr($code, 3);
        }

        // Build AST
        $ast = $this->astBuilder->buildFromString($code);

        // Collect symbols
        $symbolTable = new SymbolTable();
        $symbolCollector = new SymbolCollector($symbolTable, $filePath);

        $traverser = new NodeTraverser();
        $traverser->addVisitor($symbolCollector);
        $traverser->traverse($ast);

        // Collect references
        $referenceCollector = new ReferenceCollector($filePath);

        $traverser2 = new NodeTraverser();
        $traverser2->addVisitor($referenceCollector);
        $traverser2->traverse($ast);

        $references = $referenceCollector->getReferences();

        // Create analysis context
        $context = new AnalysisContext($symbolTable, $references);

        // Run analyzers
        $issues = array();

        $classAnalyzer = new ClassAnalyzer();
        $issues = array_merge($issues, $classAnalyzer->analyze($context));

        $functionAnalyzer = new FunctionAnalyzer();
        $issues = array_merge($issues, $functionAnalyzer->analyze($context));

        return $issues;
    }

    /**
     * Filter issues by type
     *
     * @param array $issues Issues
     * @param string $type Issue type
     *
     * @return array
     */
    private function filterByType(array $issues, $type)
    {
        return array_values(array_filter($issues, function ($issue) use ($type) {
            return $issue->getType() === $type;
        }));
    }
}
