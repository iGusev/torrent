<?php

namespace League\Torrent\Helper;

class FileSystem
{

    public static function filesize($filename)
    {
        if (is_file($filename)) {
            return (double) sprintf('%u', @filesize($filename));
        } else {
            if ($content_length = preg_grep($pattern = '#^Content-Length:\s+(\d+)$#i', (array) @get_headers($filename))
            ) {
                return (int) preg_replace($pattern, '$1', reset($content_length));
            }
        }
    }
}