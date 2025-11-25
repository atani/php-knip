<?php
/**
 * Generate test fixtures in different encodings
 *
 * Run this script with PHP to generate EUC-JP and Shift_JIS test files
 */

$baseDir = __DIR__;

// UTF-8 source for conversion
$utf8Source = <<<'PHP'
<?php
/**
 * テストクラス
 */

namespace App\Legacy;

class レガシークラス
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

function ヘルパー関数($引数)
{
    return $引数 . 'の処理結果';
}
PHP;

// Generate EUC-JP file
$eucjpContent = mb_convert_encoding($utf8Source, 'EUC-JP', 'UTF-8');
file_put_contents($baseDir . '/eucjp/legacy_class.php', $eucjpContent);
echo "Generated: eucjp/legacy_class.php\n";

// Generate Shift_JIS file
$sjisContent = mb_convert_encoding($utf8Source, 'SJIS', 'UTF-8');
file_put_contents($baseDir . '/shiftjis/legacy_class.php', $sjisContent);
echo "Generated: shiftjis/legacy_class.php\n";

// Generate EUC-JP file with declare encoding
$eucjpWithDeclare = "<?php\ndeclare(encoding='EUC-JP');\n" . substr($eucjpContent, 6);
file_put_contents($baseDir . '/eucjp/with_declare.php', $eucjpWithDeclare);
echo "Generated: eucjp/with_declare.php\n";

// Generate Shift_JIS file with declare encoding
$sjisWithDeclare = "<?php\ndeclare(encoding='Shift_JIS');\n" . substr($sjisContent, 6);
file_put_contents($baseDir . '/shiftjis/with_declare.php', $sjisWithDeclare);
echo "Generated: shiftjis/with_declare.php\n";

// Generate UTF-8 with BOM
$utf8Bom = "\xEF\xBB\xBF" . $utf8Source;
file_put_contents($baseDir . '/utf8/with_bom.php', $utf8Bom);
echo "Generated: utf8/with_bom.php\n";

echo "\nAll fixtures generated successfully.\n";
