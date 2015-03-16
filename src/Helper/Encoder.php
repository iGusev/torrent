<?php

namespace League\Torrent\Helper;

use League\Torrent\Torrent;

class Encoder
{

    /**
     * @param $mixed
     *
     * @return string
     */
    public static function encode($mixed)
    {
        switch (gettype($mixed)) {
            case 'integer':
            case 'double':
                return self::encodeInteger($mixed);
            case 'object':
                return self::encodeArray(get_object_vars($mixed));
            case 'array':
                return self::encodeArray($mixed);
            default:
                return self::encodeString((string) $mixed);
        }
    }

    /**
     * @param string $string
     *
     * @return string
     */
    public static function encodeString($string)
    {
        return strlen($string) . ':' . $string;
    }

    /**
     * @param $integer
     *
     * @return string
     */
    public static function encodeInteger($integer)
    {
        return 'i' . $integer . 'e';
    }

    /**
     * @param $array
     *
     * @return string
     */
    public static function encodeArray($array)
    {
        if (FileSystem::is_list($array)) {
            $return = 'l';
            foreach ($array as $value) {
                $return .= self::encode($value);
            }
        } else {
            ksort($array, SORT_STRING);
            $return = 'd';
            foreach ($array as $key => $value) {
                $return .= self::encode(strval($key)) . self::encode($value);
            }
        }
        return $return . 'e';
    }
}