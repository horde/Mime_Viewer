<?php

/**
 * Copyright 2022 Horde LLC (http://www.horde.org/)
 *
 * @category   Horde
 * @copyright  2022 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Mime
 * @subpackage UnitTests
 */

namespace Horde\Mime\Viewer\Test;

use Horde\Test\TestCase;

use Horde_Mime_Part;
use Horde_Mime_Viewer_Ooo;
use Horde_Compress_Zip;

class OooTest extends TestCase
{
    protected function assertStringDoesNotContainsString(string $haystack, string $needle)
    {
        $this->assertThat(
            $haystack,
            $this->logicalNot($this->stringContains($needle))
        );
    }

    public function testXssVulnerability()
    {
        $mimePart = new Horde_Mime_Part();
        $mimePart->setContents(file_get_contents(__DIR__ . '/files/xss.odt'));
        $viewer = new Horde_Mime_Viewer_Ooo(
            $mimePart,
            ['zip' => new Horde_Compress_Zip()]
        );
        $html = current(@$viewer->render('full'));

        $this->assertStringDoesNotContainsString(
            $html['data'],
            "<script>alert('xss demonstration');</script>"
        );
        $this->assertStringDoesNotContainsString(
            $html['data'],
            "javascript:alert('xss')"
        );
    }
}
