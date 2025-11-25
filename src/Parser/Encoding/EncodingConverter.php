<?php
/**
 * Encoding Converter
 *
 * Converts file content between encodings
 */

namespace PhpKnip\Parser\Encoding;

/**
 * Converts content between different character encodings
 *
 * Primary use case: Convert EUC-JP/Shift_JIS to UTF-8 for parsing
 */
class EncodingConverter
{
    /**
     * @var EncodingDetector
     */
    private $detector;

    /**
     * Constructor
     *
     * @param EncodingDetector|null $detector Encoding detector instance
     */
    public function __construct(EncodingDetector $detector = null)
    {
        $this->detector = $detector ?: new EncodingDetector();
    }

    /**
     * Convert content to UTF-8
     *
     * @param string $content Original content
     * @param string|null $sourceEncoding Source encoding (auto-detect if null)
     *
     * @return array Result with 'content', 'original_encoding', 'converted' keys
     *
     * @throws \RuntimeException If conversion fails
     */
    public function toUtf8($content, $sourceEncoding = null)
    {
        // Detect encoding if not specified
        if ($sourceEncoding === null) {
            $detection = $this->detector->detect($content);
            $sourceEncoding = $detection['encoding'];
        } else {
            $sourceEncoding = $this->detector->normalizeEncoding($sourceEncoding);
        }

        // Remove BOM if present
        $content = $this->detector->removeBom($content, $sourceEncoding);

        // Already UTF-8
        if ($sourceEncoding === 'UTF-8' || $sourceEncoding === 'ASCII') {
            return array(
                'content' => $content,
                'original_encoding' => $sourceEncoding,
                'converted' => false,
            );
        }

        // Convert to UTF-8
        $converted = $this->convert($content, $sourceEncoding, 'UTF-8');

        return array(
            'content' => $converted,
            'original_encoding' => $sourceEncoding,
            'converted' => true,
        );
    }

    /**
     * Convert content from UTF-8 to target encoding
     *
     * @param string $content UTF-8 content
     * @param string $targetEncoding Target encoding
     *
     * @return string Converted content
     *
     * @throws \RuntimeException If conversion fails
     */
    public function fromUtf8($content, $targetEncoding)
    {
        $targetEncoding = $this->detector->normalizeEncoding($targetEncoding);

        if ($targetEncoding === 'UTF-8' || $targetEncoding === 'ASCII') {
            return $content;
        }

        return $this->convert($content, 'UTF-8', $targetEncoding);
    }

    /**
     * Convert content between encodings
     *
     * @param string $content Content to convert
     * @param string $fromEncoding Source encoding
     * @param string $toEncoding Target encoding
     *
     * @return string Converted content
     *
     * @throws \RuntimeException If conversion fails
     */
    public function convert($content, $fromEncoding, $toEncoding)
    {
        $fromEncoding = $this->detector->normalizeEncoding($fromEncoding);
        $toEncoding = $this->detector->normalizeEncoding($toEncoding);

        // Same encoding, no conversion needed
        if ($fromEncoding === $toEncoding) {
            return $content;
        }

        // Try mb_convert_encoding first (preferred)
        if (function_exists('mb_convert_encoding')) {
            $result = $this->convertWithMbstring($content, $fromEncoding, $toEncoding);
            if ($result !== false) {
                return $result;
            }
        }

        // Fallback to iconv
        if (function_exists('iconv')) {
            $result = $this->convertWithIconv($content, $fromEncoding, $toEncoding);
            if ($result !== false) {
                return $result;
            }
        }

        throw new \RuntimeException(sprintf(
            'Failed to convert encoding from %s to %s. ' .
            'Please ensure mbstring or iconv extension is installed.',
            $fromEncoding,
            $toEncoding
        ));
    }

    /**
     * Convert using mbstring
     *
     * @param string $content Content to convert
     * @param string $fromEncoding Source encoding
     * @param string $toEncoding Target encoding
     *
     * @return string|false Converted content or false on failure
     */
    private function convertWithMbstring($content, $fromEncoding, $toEncoding)
    {
        // Map encoding names for mbstring
        $mbFromEncoding = $this->getMbstringEncoding($fromEncoding);
        $mbToEncoding = $this->getMbstringEncoding($toEncoding);

        // Suppress warnings and handle errors
        $previousLevel = error_reporting(0);
        $result = mb_convert_encoding($content, $mbToEncoding, $mbFromEncoding);
        error_reporting($previousLevel);

        return $result;
    }

    /**
     * Convert using iconv
     *
     * @param string $content Content to convert
     * @param string $fromEncoding Source encoding
     * @param string $toEncoding Target encoding
     *
     * @return string|false Converted content or false on failure
     */
    private function convertWithIconv($content, $fromEncoding, $toEncoding)
    {
        // Map encoding names for iconv
        $iconvFromEncoding = $this->getIconvEncoding($fromEncoding);
        $iconvToEncoding = $this->getIconvEncoding($toEncoding);

        // Use //TRANSLIT to handle characters that can't be represented
        $previousLevel = error_reporting(0);
        $result = iconv($iconvFromEncoding, $iconvToEncoding . '//TRANSLIT', $content);
        error_reporting($previousLevel);

        return $result;
    }

    /**
     * Get mbstring-compatible encoding name
     *
     * @param string $encoding Encoding name
     *
     * @return string mbstring encoding name
     */
    private function getMbstringEncoding($encoding)
    {
        $map = array(
            'SJIS' => 'SJIS',
            'SJIS-win' => 'SJIS-win',
            'EUC-JP' => 'EUC-JP',
            'UTF-8' => 'UTF-8',
            'ISO-2022-JP' => 'ISO-2022-JP',
            'ASCII' => 'ASCII',
            'UTF-16BE' => 'UTF-16BE',
            'UTF-16LE' => 'UTF-16LE',
        );

        return isset($map[$encoding]) ? $map[$encoding] : $encoding;
    }

    /**
     * Get iconv-compatible encoding name
     *
     * @param string $encoding Encoding name
     *
     * @return string iconv encoding name
     */
    private function getIconvEncoding($encoding)
    {
        $map = array(
            'SJIS' => 'SHIFT_JIS',
            'SJIS-win' => 'CP932',
            'EUC-JP' => 'EUC-JP',
            'UTF-8' => 'UTF-8',
            'ISO-2022-JP' => 'ISO-2022-JP',
            'ASCII' => 'ASCII',
            'UTF-16BE' => 'UTF-16BE',
            'UTF-16LE' => 'UTF-16LE',
        );

        return isset($map[$encoding]) ? $map[$encoding] : $encoding;
    }

    /**
     * Check if conversion is available
     *
     * @return bool True if conversion functions are available
     */
    public function isAvailable()
    {
        return function_exists('mb_convert_encoding') || function_exists('iconv');
    }

    /**
     * Validate that content is valid in the specified encoding
     *
     * @param string $content Content to validate
     * @param string $encoding Expected encoding
     *
     * @return bool True if content is valid in the encoding
     */
    public function isValidEncoding($content, $encoding)
    {
        $encoding = $this->detector->normalizeEncoding($encoding);

        if (function_exists('mb_check_encoding')) {
            return mb_check_encoding($content, $this->getMbstringEncoding($encoding));
        }

        // Fallback: try to convert and check if result is valid
        if (function_exists('iconv')) {
            $iconvEncoding = $this->getIconvEncoding($encoding);
            $previousLevel = error_reporting(0);
            $result = iconv($iconvEncoding, $iconvEncoding, $content);
            error_reporting($previousLevel);
            return $result !== false;
        }

        // Cannot validate without mbstring or iconv
        return true;
    }
}
