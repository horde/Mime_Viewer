<?php

declare(strict_types=1);

namespace Horde\Mime\Viewer\Test;

use Horde_Compress;
use Horde_Compress_Tar;
use Horde_Compress_Zip;
use Horde_Mime_Part;
use Horde_Mime_Viewer_Ooo;
use Horde_Mime_Viewer_Rar;
use Horde_Mime_Viewer_Tgz;
use Horde_Mime_Viewer_Tnef;
use Horde_Mime_Viewer_Zip;
use PHPUnit\Framework\TestCase;
use Horde_Compress_Rar;

/**
 * Tests the public render() contract of each compress-based MIME viewer.
 *
 * Each viewer takes a Horde_Mime_Part with archive content, internally uses
 * Horde_Compress to decompress, and returns structured output. The tests
 * prove: given known input, the output structure and key data are stable.
 * @coversNothing
 */
class CompressViewerTest extends TestCase
{
    /**
     * Zip viewer: render('info') returns HTML table listing archive contents.
     */
    public function testZipViewerRenderInfo(): void
    {
        $zip = new Horde_Compress_Zip();
        $archive = $zip->compress([
            ['data' => 'hello world', 'name' => 'greeting.txt'],
            ['data' => '<?php echo 1;', 'name' => 'script.php'],
        ]);

        $part = new Horde_Mime_Part();
        $part->setType('application/zip');
        $part->setName('test.zip');
        $part->setContents($archive);

        $viewer = new Horde_Mime_Viewer_Zip($part, ['charset' => 'UTF-8']);
        $result = $viewer->render('info');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $output = current($result);
        $this->assertArrayHasKey('data', $output);
        $this->assertArrayHasKey('type', $output);
        $this->assertStringContainsString('text/html', $output['type']);
        $this->assertStringContainsString('greeting.txt', $output['data']);
        $this->assertStringContainsString('script.php', $output['data']);
        $this->assertStringContainsString('File&nbsp;Count:&nbsp;2&nbsp;files', $output['data']);
    }

    /**
     * Tgz viewer: render('info') returns HTML table listing tar contents.
     */
    public function testTgzViewerRenderInfo(): void
    {
        $tar = new Horde_Compress_Tar();
        $archive = $tar->compress([
            ['data' => 'tar content one', 'name' => 'file1.txt'],
            ['data' => 'tar content two', 'name' => 'file2.txt'],
        ]);

        $part = new Horde_Mime_Part();
        $part->setType('application/x-tar');
        $part->setName('test.tar');
        $part->setContents($archive);

        $viewer = new Horde_Mime_Viewer_Tgz($part, ['charset' => 'UTF-8']);
        $result = $viewer->render('info');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $output = current($result);
        $this->assertArrayHasKey('data', $output);
        $this->assertStringContainsString('text/html', $output['type']);
        $this->assertStringContainsString('file1.txt', $output['data']);
        $this->assertStringContainsString('file2.txt', $output['data']);
        $this->assertStringContainsString('File&nbsp;Count:&nbsp;2&nbsp;files', $output['data']);
    }

    /**
     * Tnef viewer: getEmbeddedMimeParts() returns multipart/mixed with attachments.
     */
    public function testTnefViewerReturnsEmbeddedParts(): void
    {
        $tnefBinary = base64_decode(
            file_get_contents(__DIR__ . '/files/TnefAttachments.txt')
        );

        $part = new Horde_Mime_Part();
        $part->setType('application/ms-tnef');
        $part->setName('winmail.dat');
        $part->setContents($tnefBinary);

        $viewer = new TestableTnefViewer($part, []);
        $result = $viewer->getEmbedded();

        $this->assertInstanceOf(Horde_Mime_Part::class, $result);
        $this->assertEquals('multipart/mixed', $result->getType());

        $parts = [];
        foreach ($result as $child) {
            if ($child !== $result) {
                $parts[] = $child;
            }
        }
        $this->assertGreaterThanOrEqual(2, count($parts));
        $this->assertEquals('image/jpeg', $parts[1]->getType());
        $this->assertEquals('hasselhoff_birthday.jpg', $parts[1]->getName());
    }

    /**
     * Ooo viewer: render('full') returns HTML from content.xml inside ZIP.
     * Tests without injecting a zip object (exercises factory path).
     */
    public function testOooViewerRenderFull(): void
    {
        $part = new Horde_Mime_Part();
        $part->setType('application/vnd.oasis.opendocument.text');
        $part->setContents(file_get_contents(__DIR__ . '/files/xss.odt'));

        $viewer = new Horde_Mime_Viewer_Ooo($part, []);
        $result = @$viewer->render('full');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $output = current($result);
        $this->assertArrayHasKey('data', $output);
        $this->assertStringContainsString('text/html', $output['type']);
    }

    /**
     * Rar viewer: render('info') given a pre-configured rar object.
     * RAR archives can't be programmatically created, so we inject a mock.
     */
    public function testRarViewerRenderInfo(): void
    {
        $mockRar = $this->createMock(Horde_Compress_Rar::class);
        $mockRar->method('decompress')->willReturn([
            [
                'name' => 'document.pdf',
                'size' => 12345,
                'csize' => 10000,
                'date' => 1609459200,
                'method' => 'Store',
                'attr' => '....A',
            ],
        ]);

        $part = new Horde_Mime_Part();
        $part->setType('application/x-rar-compressed');
        $part->setName('archive.rar');
        $part->setContents('dummy rar content');

        $viewer = new Horde_Mime_Viewer_Rar($part, [
            'charset' => 'UTF-8',
            'rar' => $mockRar,
        ]);
        $result = $viewer->render('info');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $output = current($result);
        $this->assertArrayHasKey('data', $output);
        $this->assertStringContainsString('document.pdf', $output['data']);
        $this->assertStringContainsString('File&nbsp;Count:&nbsp;1&nbsp;file', $output['data']);
    }
}

/**
 * Test subclass exposing the protected _getEmbeddedMimeParts().
 */
class TestableTnefViewer extends Horde_Mime_Viewer_Tnef
{
    public function getEmbedded(): ?Horde_Mime_Part
    {
        return $this->_getEmbeddedMimeParts();
    }
}
