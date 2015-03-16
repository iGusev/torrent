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

use League\Torrent\Torrent;

class Decoder
{

    /**
     * @param $data
     *
     * @return array|bool|int|string
     */
    public static function decode_data(& $data)
    {
        switch (FileSystem::char($data)) {
            case 'i':
                $data = substr($data, 1);
                return self::decode_integer($data);
            case 'l':
                $data = substr($data, 1);
                return self::decode_list($data);
            case 'd':
                $data = substr($data, 1);
                return self::decode_dictionary($data);
            default:
                return self::decode_string($data);
        }
    }

    /**
     * @param $data
     *
     * @return array|bool
     */
    public static function decode_dictionary(& $data)
    {
        $dictionary = array();
        $previous = null;
        while (($char = FileSystem::char($data)) != 'e') {
            if ($char === false) {
                throw new \Exception('Unterminated dictionary');
            }
            if (!ctype_digit($char)) {
                throw new \Exception('Invalid dictionary key');
            }
            $key = self::decode_string($data);
            if (isset($dictionary[$key])) {
                throw new \Exception('Duplicate dictionary key');
            }
            if ($key < $previous) {
                throw new \Exception('Missorted dictionary key');
            }
            $dictionary[$key] = self::decode_data($data);
            $previous = $key;
        }
        $data = substr($data, 1);
        return $dictionary;
    }

    /**
     * @param $data
     *
     * @return array|bool
     */
    public static function decode_list(& $data)
    {
        $list = array();
        while (($char = FileSystem::char($data)) != 'e') {
            if ($char === false) {
                throw new Exception('Unterminated list');
            }

            $list[] = self::decode_data($data);
        }
        $data = substr($data, 1);
        return $list;
    }

    /**
     * @param $data
     *
     * @return bool|string
     */
    public static function decode_string(& $data)
    {
        if (FileSystem::char($data) === '0' && substr($data, 1, 1) != ':') {
            throw new Exception('Invalid string length, leading zero');
        }
        if (!$colon = @strpos($data, ':')) {
            throw new Exception('Invalid string length, colon not found');
        }
        $length = intval(substr($data, 0, $colon));
        if ($length + $colon + 1 > strlen($data)) {
            throw new Exception('Invalid string, input too short for string length');
        }
        $string = substr($data, $colon + 1, $length);
        $data = substr($data, $colon + $length + 1);
        return $string;
    }

    /**
     * @param $data
     *
     * @return int
     */
    public static function decode_integer(& $data)
    {
        $start = 0;
        $end = strpos($data, 'e');
        if (FileSystem::char($data) == '-') {
            $start++;
        }
        if (substr($data, $start, 1) == '0' && $end > $start + 1) {
            throw new Exception('Leading zero in integer');
        }
        if (!ctype_digit(substr($data, $start, $start ? $end - 1 : $end))) {
            throw new Exception('Non-digit characters in integer');
        }
        $integer = substr($data, 0, $end);
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

        return (array) Decoder::decode_data($data);
    }
}