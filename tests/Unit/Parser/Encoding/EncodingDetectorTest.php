<?php
/**
 * EncodingDetector Test
 */

namespace PhpKnip\Tests\Unit\Parser\Encoding;

use PhpKnip\Tests\TestCase;
use PhpKnip\Parser\Encoding\EncodingDetector;

class EncodingDetectorTest extends TestCase
{
    /**
     * @var EncodingDetector
     */
    private $detector;

    protected function setUp(): void
    {
        $this->detector = new EncodingDetector();
    }

    public function testDetectUtf8Bom()
    {
        $content = "\xEF\xBB\xBF<?php echo 'test';";
        $result = $this->detector->detect($content);

        $this->assertEquals('UTF-8', $result['encoding']);
        $this->assertEquals('bom', $result['method']);
        $this->assertEquals('high', $result['confidence']);
    }

    public function testDetectUtf16BeBom()
    {
        $content = "\xFE\xFF<?php echo 'test';";
        $result = $this->detector->detect($content);

        $this->assertEquals('UTF-16BE', $result['encoding']);
        $this->assertEquals('bom', $result['method']);
    }

    public function testDetectUtf16LeBom()
    {
        $content = "\xFF\xFE<?php echo 'test';";
        $result = $this->detector->detect($content);

        $this->assertEquals('UTF-16LE', $result['encoding']);
        $this->assertEquals('bom', $result['method']);
    }

    public function testDetectDeclareEncodingUtf8()
    {
        $content = "<?php\ndeclare(encoding='UTF-8');\necho 'test';";
        $result = $this->detector->detect($content);

        $this->assertEquals('UTF-8', $result['encoding']);
        $this->assertEquals('declare', $result['method']);
    }

    public function testDetectDeclareEncodingEucJp()
    {
        $content = "<?php\ndeclare(encoding='EUC-JP');\necho 'test';";
        $result = $this->detector->detect($content);

        $this->assertEquals('EUC-JP', $result['encoding']);
        $this->assertEquals('declare', $result['method']);
    }

    public function testDetectDeclareEncodingShiftJis()
    {
        $content = "<?php\ndeclare(encoding=\"Shift_JIS\");\necho 'test';";
        $result = $this->detector->detect($content);

        $this->assertEquals('SJIS', $result['encoding']);
        $this->assertEquals('declare', $result['method']);
    }

    public function testDetectWithConfiguredEncoding()
    {
        $detector = new EncodingDetector('EUC-JP');
        $content = "<?php echo 'test';";
        $result = $detector->detect($content);

        $this->assertEquals('EUC-JP', $result['encoding']);
        $this->assertEquals('config', $result['method']);
    }

    public function testDetectDefaultsToUtf8()
    {
        $content = "<?php echo 'test';";
        $result = $this->detector->detect($content);

        // Without mbstring, should default to UTF-8
        // With mbstring, may detect as ASCII or UTF-8
        $this->assertContains($result['encoding'], array('UTF-8', 'ASCII'));
    }

    public function testNormalizeEncodingEucJp()
    {
        $this->assertEquals('EUC-JP', $this->detector->normalizeEncoding('euc-jp'));
        $this->assertEquals('EUC-JP', $this->detector->normalizeEncoding('eucjp'));
        $this->assertEquals('EUC-JP', $this->detector->normalizeEncoding('EUC_JP'));
        $this->assertEquals('EUC-JP', $this->detector->normalizeEncoding('ujis'));
    }

    public function testNormalizeEncodingShiftJis()
    {
        $this->assertEquals('SJIS', $this->detector->normalizeEncoding('shift-jis'));
        $this->assertEquals('SJIS', $this->detector->normalizeEncoding('shiftjis'));
        $this->assertEquals('SJIS', $this->detector->normalizeEncoding('Shift_JIS'));
        $this->assertEquals('SJIS-win', $this->detector->normalizeEncoding('cp932'));
    }

    public function testIsSupportedEncoding()
    {
        $this->assertTrue($this->detector->isSupported('UTF-8'));
        $this->assertTrue($this->detector->isSupported('euc-jp'));
        $this->assertTrue($this->detector->isSupported('shift_jis'));
        $this->assertFalse($this->detector->isSupported('UNKNOWN-ENCODING'));
    }

    public function testRemoveBom()
    {
        $contentWithBom = "\xEF\xBB\xBF<?php echo 'test';";
        $contentWithoutBom = $this->detector->removeBom($contentWithBom, 'UTF-8');

        $this->assertEquals("<?php echo 'test';", $contentWithoutBom);
    }

    public function testRemoveBomWhenNoBomPresent()
    {
        $content = "<?php echo 'test';";
        $result = $this->detector->removeBom($content, 'UTF-8');

        $this->assertEquals($content, $result);
    }

    public function testGetBomLength()
    {
        $this->assertEquals(3, $this->detector->getBomLength('UTF-8'));
        $this->assertEquals(2, $this->detector->getBomLength('UTF-16BE'));
        $this->assertEquals(2, $this->detector->getBomLength('UTF-16LE'));
        $this->assertEquals(0, $this->detector->getBomLength('EUC-JP'));
    }
}
