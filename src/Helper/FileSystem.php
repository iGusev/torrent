<?php

namespace League\Torrent\Helper;

use League\Torrent\Torrent;

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

    /**
     * @param $url
     *
     * @return int
     */
    public static function is_url($url)
    {
        return preg_match('#^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$#i', $url);
    }

    /**
     * @param $url
     *
     * @return bool
     */
    public static function url_exists($url)
    {
        return FileSystem::is_url($url) ?
            (bool) FileSystem::filesize($url) :
            false;
    }

    /**
     * @param $announces
     *
     * @return array
     */
    public static function untier($announces)
    {
        $list = array();
        foreach ((array) $announces as $tier) {
            is_array($tier) ?
                $list = array_merge($list, FileSystem::untier($tier)) :
                array_push($list, $tier);
        }
        return $list;
    }

    /**
     * @param $array
     *
     * @return bool
     */
    public static function is_list($array)
    {
        foreach (array_keys($array) as $key) {
            if (!is_int($key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param $path
     * @param $folder
     *
     * @return string
     */
    public static function path($path, $folder)
    {
        array_unshift($path, $folder);
        return join(DIRECTORY_SEPARATOR, $path);
    }

    /**
     * @param $dir
     *
     * @return array
     */
    public static function scandir($dir)
    {
        $paths = array();
        foreach (scandir($dir) as $item) {
            if ($item != '.' && $item != '..') {
                if (is_dir($path = realpath($dir . DIRECTORY_SEPARATOR . $item))) {
                    $paths = array_merge(self::scandir($path), $paths);
                } else {
                    $paths[] = $path;
                }
            }
        }
        return $paths;
    }

    /**
     * @param $data
     *
     * @return bool|string
     */
    public static function char($data)
    {
        return empty($data) ?
            false :
            substr($data, 0, 1);
    }
}