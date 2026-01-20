<?php

namespace PhpKnip\Tests\Unit\Cache;

use PHPUnit\Framework\TestCase;
use PhpKnip\Cache\CacheManager;

class CacheManagerTest extends TestCase
{
    private $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/php-knip-cache-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_dir($file)) {
                $this->removeDirectory($file);
            } else {
                unlink($file);
            }
        }
        rmdir($dir);
    }

    public function testConstructorCreatesDirectory()
    {
        $cacheDir = $this->tempDir . '/cache';
        $manager = new CacheManager($cacheDir, true);

        $this->assertDirectoryExists($cacheDir);
    }

    public function testDisabledCacheReturnsNull()
    {
        $manager = new CacheManager($this->tempDir . '/cache', false);

        $this->assertFalse($manager->isEnabled());
        $this->assertFalse($manager->isValid('/some/file.php'));
        $this->assertNull($manager->get('/some/file.php'));
    }

    public function testSetAndGet()
    {
        $cacheDir = $this->tempDir . '/cache';
        $manager = new CacheManager($cacheDir, true);

        // Create a test file
        $testFile = $this->tempDir . '/test.php';
        file_put_contents($testFile, '<?php class Test {}');

        $data = array(
            'symbols' => array(
                array('type' => 'class', 'name' => 'Test'),
            ),
            'references' => array(),
            'useStatements' => array(),
        );

        // Set cache
        $result = $manager->set($testFile, $data);
        $this->assertTrue($result);

        // Save metadata
        $manager->saveMetadata();

        // Get cache
        $cached = $manager->get($testFile);
        $this->assertNotNull($cached);
        $this->assertEquals($data, $cached);
    }

    public function testIsValidReturnsTrueForUnchangedFile()
    {
        $cacheDir = $this->tempDir . '/cache';
        $manager = new CacheManager($cacheDir, true);

        // Create a test file
        $testFile = $this->tempDir . '/test.php';
        file_put_contents($testFile, '<?php class Test {}');

        $data = array('symbols' => array(), 'references' => array());
        $manager->set($testFile, $data);
        $manager->saveMetadata();

        // Create new manager to simulate fresh start
        $manager2 = new CacheManager($cacheDir, true);
        $this->assertTrue($manager2->isValid($testFile));
    }

    public function testIsValidReturnsFalseForChangedFile()
    {
        $cacheDir = $this->tempDir . '/cache';
        $manager = new CacheManager($cacheDir, true);

        // Create a test file
        $testFile = $this->tempDir . '/test.php';
        file_put_contents($testFile, '<?php class Test {}');

        $data = array('symbols' => array(), 'references' => array());
        $manager->set($testFile, $data);
        $manager->saveMetadata();

        // Modify the file
        sleep(1); // Ensure mtime changes
        file_put_contents($testFile, '<?php class TestModified {}');

        // Create new manager to simulate fresh start
        $manager2 = new CacheManager($cacheDir, true);
        $this->assertFalse($manager2->isValid($testFile));
    }

    public function testIsValidReturnsFalseForDeletedFile()
    {
        $cacheDir = $this->tempDir . '/cache';
        $manager = new CacheManager($cacheDir, true);

        // Create a test file
        $testFile = $this->tempDir . '/test.php';
        file_put_contents($testFile, '<?php class Test {}');

        $data = array('symbols' => array(), 'references' => array());
        $manager->set($testFile, $data);
        $manager->saveMetadata();

        // Delete the file
        unlink($testFile);

        // Create new manager to simulate fresh start
        $manager2 = new CacheManager($cacheDir, true);
        $this->assertFalse($manager2->isValid($testFile));
    }

    public function testClear()
    {
        $cacheDir = $this->tempDir . '/cache';
        $manager = new CacheManager($cacheDir, true);

        // Create a test file and cache it
        $testFile = $this->tempDir . '/test.php';
        file_put_contents($testFile, '<?php class Test {}');

        $data = array('symbols' => array(), 'references' => array());
        $manager->set($testFile, $data);
        $manager->saveMetadata();

        // Clear cache
        $result = $manager->clear();
        $this->assertTrue($result);

        // Verify cache is empty
        $this->assertFalse($manager->isValid($testFile));
    }

    public function testGetStats()
    {
        $cacheDir = $this->tempDir . '/cache';
        $manager = new CacheManager($cacheDir, true);

        // Create and cache a test file
        $testFile = $this->tempDir . '/test.php';
        file_put_contents($testFile, '<?php class Test {}');

        $data = array('symbols' => array(), 'references' => array());
        $manager->set($testFile, $data);
        $manager->saveMetadata();

        $stats = $manager->getStats();

        $this->assertTrue($stats['enabled']);
        $this->assertEquals($cacheDir, $stats['directory']);
        $this->assertEquals(1, $stats['fileCount']);
        $this->assertGreaterThan(0, $stats['cacheSize']);
        $this->assertEquals(1, $stats['writes']);
    }

    public function testCacheVersionMismatchClearsCache()
    {
        $cacheDir = $this->tempDir . '/cache';
        mkdir($cacheDir, 0755, true);

        // Write meta.json with wrong version
        $meta = array(
            'version' => 999,
            'created' => time(),
            'files' => array(),
        );
        file_put_contents($cacheDir . '/meta.json', json_encode($meta));

        // Create manager - should clear cache due to version mismatch
        $manager = new CacheManager($cacheDir, true);

        $stats = $manager->getStats();
        $this->assertEquals(0, $stats['fileCount']);
    }

    public function testMultipleFiles()
    {
        $cacheDir = $this->tempDir . '/cache';
        $manager = new CacheManager($cacheDir, true);

        // Create multiple test files
        for ($i = 1; $i <= 3; $i++) {
            $testFile = $this->tempDir . '/test' . $i . '.php';
            file_put_contents($testFile, '<?php class Test' . $i . ' {}');

            $data = array(
                'symbols' => array(array('type' => 'class', 'name' => 'Test' . $i)),
                'references' => array(),
            );
            $manager->set($testFile, $data);
        }
        $manager->saveMetadata();

        $stats = $manager->getStats();
        $this->assertEquals(3, $stats['fileCount']);

        // Verify all files are valid
        for ($i = 1; $i <= 3; $i++) {
            $testFile = $this->tempDir . '/test' . $i . '.php';
            $this->assertTrue($manager->isValid($testFile));
        }
    }
}
