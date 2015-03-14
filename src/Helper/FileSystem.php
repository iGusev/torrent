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

    /**
     * @param $size
     * @param int $precision
     *
     * @return string
     */
    public static function format($size, $precision = 2)
    {
        $units = array('octets', 'Ko', 'Mo', 'Go', 'To');
        while (($next = next($units)) && $size > 1024) {
            $size /= 1024;
        }
        return round($size, $precision) . ' ' . ($next ? prev($units) : end($units));
    }

    /**
     * pack data hash to binary
     *
     * @param $data
     *
     * @return string
     */
    public static function pack(& $data)
    {
        return pack('H*', sha1($data)) . ($data = null);
    }
}