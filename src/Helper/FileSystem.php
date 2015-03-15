<?php

namespace League\Torrent\Helper;

use League\Torrent\Torrent;

class FileSystem
{
    /**
     *
     * @var int
     */
    const timeout = 30;

    /**
     * List of strings that can start a torrent file
     *
     * @static
     * @var array
     */
    protected static $torrentsChecks = array('d8:announce', 'd10:created', 'd13:creatio', 'd4:info', 'd9:');

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

    public static function downloadTorrent($url, $timeout = self::timeout)
    {
        if (ini_get('allow_url_fopen')) {
            return self::downloadViaStream($url, $timeout);
        } else {
            return self::downloadViaCurl($url, $timeout);
        }
    }

    public static function downloadViaStream($url, $timeout = self::timeout)
    {
        if (!ini_get('allow_url_fopen')) {
            throw new \Exception('Install CURL or enable "allow_url_fopen"');
        }

        return file_get_contents($url, false, stream_context_create(array('http' => array('timeout' => $timeout))));
    }

    public static function downloadViaCurl($url, $timeout = self::timeout)
    {
        if (!function_exists('curl_init')) {
            throw new \Exception('Install CURL or enable "allow_url_fopen"');
        }
        $handle = curl_init($url);
        if ($timeout) {
            curl_setopt($handle, CURLOPT_TIMEOUT, $timeout);
        }
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
        $content = curl_exec($handle);
        curl_close($handle);
        return $content;
    }

    /**
     * @param $file
     *
     * @return bool
     */
    public static function isTorrent($file)
    {
        $start = substr($file, 0, 11);
        $check = false;
        foreach (self::$torrentsChecks as $value) {
            if (0 === strpos($start, $value)) {
                $check = true;
                continue;
            }
        }

        return $check;
    }
}