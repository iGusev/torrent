<?php

namespace League\Torrent\Test;

use League\Torrent\Torrent;

class TorrentTest extends \PHPUnit_Framework_TestCase
{

    public function testCreateFromFile()
    {
        $torrent = new Torrent('/Users/iGusev/torrent/tests/files/test2.torrent');
        $torrent2 = Torrent::createFromFile('/Users/iGusev/torrent/tests/files/test2.torrent');

        $this->assertEquals($torrent, $torrent2);
    }
    /**
     * Test that true does in fact equal true
     */
    public function testTrueIsTrue()
    {
        $this->assertTrue(true);
    }
}