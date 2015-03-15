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
                return self::encode_integer($mixed);
            case 'object':
                $mixed = get_object_vars($mixed);
            case 'array':
                return self::encode_array($mixed);
            default:
                return self::encode_string((string) $mixed);
        }
    }

    /**
     * @param string $string
     *
     * @return string
     */
    public static function encode_string($string)
    {
        return strlen($string) . ':' . $string;
    }

    /**
     * @param $integer
     *
     * @return string
     */
    public static function encode_integer($integer)
    {
        return 'i' . $integer . 'e';
    }

    /**
     * @param $array
     *
     * @return string
     */
    public static function encode_array($array)
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