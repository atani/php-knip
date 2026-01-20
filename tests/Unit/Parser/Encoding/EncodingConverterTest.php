<?php
/**
 * EncodingConverter Test
 */

namespace PhpKnip\Tests\Unit\Parser\Encoding;

use PhpKnip\Tests\TestCase;
use PhpKnip\Parser\Encoding\EncodingConverter;
use PhpKnip\Parser\Encoding\EncodingDetector;

class EncodingConverterTest extends TestCase
{
    /**
     * @var EncodingConverter
     */
    private $converter;

    protected function setUp(): void
    {
        $this->converter = new EncodingConverter();
    }

    public function testToUtf8AlreadyUtf8()
    {
        $content = "<?php echo '日本語テスト';";
        $result = $this->converter->toUtf8($content, 'UTF-8');

        $this->assertEquals($content, $result['content']);
        $this->assertEquals('UTF-8', $result['original_encoding']);
        $this->assertFalse($result['converted']);
    }

    public function testToUtf8Ascii()
    {
        $content = "<?php echo 'test';";
        $result = $this->converter->toUtf8($content, 'ASCII');

        $this->assertEquals($content, $result['content']);
        $this->assertEquals('ASCII', $result['original_encoding']);
        $this->assertFalse($result['converted']);
    }

    /**
     * @requires extension mbstring
     */
    public function testToUtf8FromEucJp()
    {
        // Create EUC-JP content
        $utf8Content = "<?php echo '日本語';";
        $eucjpContent = mb_convert_encoding($utf8Content, 'EUC-JP', 'UTF-8');

        $result = $this->converter->toUtf8($eucjpContent, 'EUC-JP');

        $this->assertEquals($utf8Content, $result['content']);
        $this->assertEquals('EUC-JP', $result['original_encoding']);
        $this->assertTrue($result['converted']);
    }

    /**
     * @requires extension mbstring
     */
    public function testToUtf8FromShiftJis()
    {
        // Create Shift_JIS content
        $utf8Content = "<?php echo 'テスト';";
        $sjisContent = mb_convert_encoding($utf8Content, 'SJIS', 'UTF-8');

        $result = $this->converter->toUtf8($sjisContent, 'SJIS');

        $this->assertEquals($utf8Content, $result['content']);
        $this->assertEquals('SJIS', $result['original_encoding']);
        $this->assertTrue($result['converted']);
    }

    /**
     * @requires extension mbstring
     */
    public function testFromUtf8ToEucJp()
    {
        $utf8Content = "<?php echo '日本語';";
        $expected = mb_convert_encoding($utf8Content, 'EUC-JP', 'UTF-8');

        $result = $this->converter->fromUtf8($utf8Content, 'EUC-JP');

        $this->assertEquals($expected, $result);
    }

    public function testFromUtf8ToUtf8()
    {
        $content = "<?php echo 'test';";
        $result = $this->converter->fromUtf8($content, 'UTF-8');

        $this->assertEquals($content, $result);
    }

    /**
     * @requires extension mbstring
     */
    public function testConvertSameEncoding()
    {
        $content = "<?php echo 'test';";
        $result = $this->converter->convert($content, 'UTF-8', 'UTF-8');

        $this->assertEquals($content, $result);
    }

    public function testIsAvailable()
    {
        // Should be true if either mbstring or iconv is available
        $expected = function_exists('mb_convert_encoding') || function_exists('iconv');
        $this->assertEquals($expected, $this->converter->isAvailable());
    }

    /**
     * @requires extension mbstring
     */
    public function testIsValidEncodingValid()
    {
        $utf8Content = "<?php echo '日本語';";
        $this->assertTrue($this->converter->isValidEncoding($utf8Content, 'UTF-8'));
    }

    public function testToUtf8RemovesBom()
    {
        $contentWithBom = "\xEF\xBB\xBF<?php echo 'test';";
        $result = $this->converter->toUtf8($contentWithBom, 'UTF-8');

        $this->assertEquals("<?php echo 'test';", $result['content']);
    }

    /**
     * @requires extension mbstring
     */
    public function testAutoDetectAndConvert()
    {
        // UTF-8 content should be auto-detected
        $content = "<?php echo '日本語テスト';";
        $result = $this->converter->toUtf8($content);

        $this->assertEquals($content, $result['content']);
    }
}
