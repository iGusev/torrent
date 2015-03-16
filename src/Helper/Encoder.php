<?php

namespace League\Torrent\Helper;

use League\Torrent\Torrent;

class Encoder
{
    /**
     * @param $object
     *
     * @return string
     */
    public static function encodeObject($object)
    {
        return self::encodeArray(get_object_vars($object));
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