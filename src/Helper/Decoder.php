<?php
/**
 * Game-Insight
 *
 * @package     Game-Insight/torrent
 * @author      Ilya Gusev <mail@igusev.ru>
 * @copyright   Copyright (c) 2015
 * @link        https://github.com/Game-Insight/torrent
 * @created     15.03.2015 03:16
 */

namespace League\Torrent\Helper;


/**
 * Class Decoder
 *
 * @todo: validations for decoders
 * @package League\Torrent\Helper
 */
class Decoder
{

    /**
     * @param $data
     *
     * @return array|bool|int|string
     */
    public static function decodeData(& $data)
    {
        switch (FileSystem::char($data)) {
            case 'i':
                $data = substr($data, 1);
                return self::decodeInteger($data);
            case 'l':
                $data = substr($data, 1);
                return self::decodeList($data);
            case 'd':
                $data = substr($data, 1);
                return self::decodeDictionary($data);
            default:
                return self::decodeString($data);
        }
    }

    /**
     * @param $data
     *
     * @return array|bool
     */
    public static function decodeDictionary(& $data)
    {
        $dictionary = array();
        while (($char = FileSystem::char($data)) != 'e') {
            $key = self::decodeString($data);

            $dictionary[$key] = self::decodeData($data);
        }
        $data = substr($data, 1);
        return $dictionary;
    }

    /**
     * @param $data
     *
     * @return array|bool
     */
    public static function decodeList(& $data)
    {
        $list = array();
        while (($char = FileSystem::char($data)) != 'e') {
            $list[] = self::decodeData($data);
        }
        $data = substr($data, 1);
        return $list;
    }

    /**
     * @param $data
     *
     * @return bool|string
     */
    public static function decodeString(& $data)
    {
        $colon = strpos($data, ':');
        $length = intval(substr($data, 0, $colon));

        $string = substr($data, $colon + 1, $length);
        $data = substr($data, $colon + $length + 1);
        return $string;
    }

    /**
     * @param $data
     *
     * @return int
     */
    public static function decodeInteger(& $data)
    {
        $start = 0;
        $end = strpos($data, 'e');
        if (FileSystem::char($data) == '-') {
            $start++;
        }
        $integer = (int) substr($data, 0, $end);
        $data = substr($data, $end + 1);
        return 0 + $integer;
    }

    /**
     * @param $string
     *
     * @return array
     */
    public static function decode($string)
    {
        if (is_file($string)) {
            $data = file_get_contents($string);
        } elseif (FileSystem::url_exists($string)) {
            $data = FileSystem::downloadTorrent($string);
        } else {
            $data = $string;
        }

        return (array) Decoder::decodeData($data);
    }
}