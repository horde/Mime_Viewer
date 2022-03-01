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

/**
 * Tests for the Horde_Mime_Viewer_Ooo class.
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @copyright  2022 Horde LLC
 * @internal
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Mime
 * @subpackage UnitTests
 */
class Horde_Mime_MimeTest extends \PHPUnit\Framework\TestCase
{

    public function testXssVulnerability()
    {
        $mimePart = new Horde_Mime_Part();
        $mimePart->setContents(file_get_contents(__DIR__ . '/xss.odt'));
        $viewer = new Horde_Mime_Viewer_Ooo(
            $mimePart,
            array('zip' => new Horde_Compress_Zip())
        );
        $html = current(@$viewer->render('full'));

        $this->assertNotContains("<script>alert('xss demonstration');</script>", $html['data']);
        $this->assertNotContains("javascript:alert('xss')", $html['data']);
    }

}
