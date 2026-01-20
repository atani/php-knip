<?php
/**
 * Use Statement Fixer
 *
 * Removes unused use statements from PHP files
 */

namespace PhpKnip\Fixer;

use PhpKnip\Analyzer\Issue;

/**
 * Fixer for removing unused use statements
 */
class UseStatementFixer implements FixerInterface
{
    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'use-statement';
    }

    /**
     * @inheritDoc
     */
    public function getDescription()
    {
        return 'Removes unused use statements';
    }

    /**
     * @inheritDoc
     */
    public function getPriority()
    {
        return 100; // High priority - should run first
    }

    /**
     * @inheritDoc
     */
    public function canFix(Issue $issue)
    {
        return $issue->getType() === Issue::TYPE_UNUSED_USE
            && $issue->getFilePath() !== null
            && $issue->getLine() !== null;
    }

    /**
     * @inheritDoc
     */
    public function getSupportedTypes()
    {
        return array(Issue::TYPE_UNUSED_USE);
    }

    /**
     * @inheritDoc
     */
    public function fix(Issue $issue, $content)
    {
        $line = $issue->getLine();
        $symbolName = $issue->getSymbolName();

        if ($line === null || $symbolName === null) {
            return FixResult::failure($issue, 'Missing line number or symbol name');
        }

        $lines = explode("\n", $content);

        // Check if line exists
        $lineIndex = $line - 1; // Convert to 0-indexed
        if (!isset($lines[$lineIndex])) {
            return FixResult::failure($issue, 'Line ' . $line . ' does not exist');
        }

        $targetLine = $lines[$lineIndex];

        // Verify this is actually a use statement
        if (!$this->isUseStatement($targetLine)) {
            return FixResult::failure($issue, 'Line ' . $line . ' is not a use statement');
        }

        // Verify the use statement matches the symbol we're trying to remove
        if (!$this->matchesSymbol($targetLine, $symbolName)) {
            return FixResult::skipped($issue, 'Use statement does not match symbol');
        }

        // Remove the line
        $removedLine = $lines[$lineIndex];
        unset($lines[$lineIndex]);

        // Remove any trailing empty line if the previous line is also empty or a use statement
        $lines = array_values($lines); // Re-index array
        $lines = $this->cleanupEmptyLines($lines, $lineIndex);

        $newContent = implode("\n", $lines);

        $result = FixResult::success(
            $issue,
            $newContent,
            sprintf("Removed use statement '%s' from line %d", $symbolName, $line)
        );
        $result->setRemovedLines(array($line));

        return $result;
    }

    /**
     * Check if a line is a use statement
     *
     * @param string $line Line content
     *
     * @return bool
     */
    private function isUseStatement($line)
    {
        $trimmed = ltrim($line);
        return strpos($trimmed, 'use ') === 0;
    }

    /**
     * Check if the use statement matches the symbol name
     *
     * @param string $line Line content
     * @param string $symbolName Symbol name to match
     *
     * @return bool
     */
    private function matchesSymbol($line, $symbolName)
    {
        // Handle various use statement formats:
        // use Foo\Bar;
        // use Foo\Bar as Baz;
        // use function Foo\bar;
        // use const Foo\BAR;

        $trimmed = trim($line);

        // Remove trailing semicolon and whitespace
        $trimmed = rtrim($trimmed, ';');
        $trimmed = trim($trimmed);

        // Extract the imported name
        // Pattern: use [function|const] FullyQualifiedName [as Alias]
        $pattern = '/^use\s+(?:function\s+|const\s+)?([^\s;]+)(?:\s+as\s+(\w+))?$/';

        if (!preg_match($pattern, $trimmed, $matches)) {
            return false;
        }

        $importedName = $matches[1];
        $alias = isset($matches[2]) ? $matches[2] : null;

        // The symbol name in the issue might be just the short name (alias or last part)
        // or the full qualified name

        // Check if symbolName matches the alias
        if ($alias !== null && $alias === $symbolName) {
            return true;
        }

        // Check if symbolName matches the full qualified name
        if ($importedName === $symbolName) {
            return true;
        }

        // Check if symbolName matches the short name (last part)
        $shortName = $this->getShortName($importedName);
        if ($shortName === $symbolName) {
            return true;
        }

        // Check if the imported name ends with the symbol name (partial match)
        if ($this->endsWith($importedName, '\\' . $symbolName)) {
            return true;
        }

        return false;
    }

    /**
     * Get the short name (last part) from a fully qualified name
     *
     * @param string $fqn Fully qualified name
     *
     * @return string
     */
    private function getShortName($fqn)
    {
        $parts = explode('\\', $fqn);
        return end($parts);
    }

    /**
     * Check if string ends with another string
     *
     * @param string $haystack String to search in
     * @param string $needle String to search for
     *
     * @return bool
     */
    private function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length === 0) {
            return true;
        }
        return substr($haystack, -$length) === $needle;
    }

    /**
     * Clean up consecutive empty lines after removal
     *
     * @param array $lines Array of lines
     * @param int $removedIndex Index where line was removed
     *
     * @return array
     */
    private function cleanupEmptyLines(array $lines, $removedIndex)
    {
        // If we're at the start of use statements block and created consecutive empty lines, clean up
        // Check if we have consecutive empty lines
        $count = count($lines);

        for ($i = 0; $i < $count - 1; $i++) {
            // Skip if this line is not empty
            if (trim($lines[$i]) !== '') {
                continue;
            }

            // Check if next line is also empty
            if (isset($lines[$i + 1]) && trim($lines[$i + 1]) === '') {
                // Remove one of the consecutive empty lines
                array_splice($lines, $i, 1);
                $count--;
                $i--; // Re-check this position
            }
        }

        return $lines;
    }
}
