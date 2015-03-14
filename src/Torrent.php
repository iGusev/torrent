<?php

namespace League\Torrent;

use League\Torrent\Helper\FileSystem;

/**
 * Class Torrent
 *
 * @package League\Torrent
 */
class Torrent
{

    /**
     *
     */
    const timeout = 30;

    /**
     * Optional comment
     *
     * @var string
     */
    protected $comment;

    /**
     * Info about the file(s) in the torrent
     *
     * @var array
     */
    protected $info;

    /**
     * @var array
     */
    protected static $_errors = array();

    /**
     * @param null $data
     * @param array $meta
     * @param int $piece_length
     */
    public function __construct($data = null, $meta = array(), $piece_length = 256)
    {
        if (is_null($data)) {
            return false;
        }
        if ($piece_length < 32 || $piece_length > 4096) {
            return self::set_error(new Exception('Invalid piece lenth, must be between 32 and 4096'));
        }
        if (is_string($meta)) {
            $meta = array('announce' => $meta);
        }
        if ($this->build($data, $piece_length * 1024)) {
            $this->touch();
        } else {
            $meta = array_merge($meta, $this->decode($data));
        }
        foreach ($meta as $key => $value) {
            $this->{$key} = $value;
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->encode($this);
    }

    /**
     * @return bool
     */
    public function error()
    {
        return empty(self::$_errors) ?
            false :
            self::$_errors[0]->getMessage();
    }

    /**
     * @return array|bool
     */
    public function errors()
    {
        return empty(self::$_errors) ?
            false :
            self::$_errors;
    }

    /**
     * @param null $announce
     *
     * @return array|mixed|null|string
     */
    public function announce($announce = null)
    {
        if (is_null($announce)) {
            return !isset($this->{'announce-list'}) ?
                isset($this->announce) ? $this->announce : null :
                $this->{'announce-list'};
        }
        $this->touch();
        if (is_string($announce) && isset($this->announce)) {
            return $this->{'announce-list'} = self::announce_list(isset($this->{'announce-list'}) ? $this->{'announce-list'} : $this->announce,
                $announce);
        }
        unset($this->{'announce-list'});
        if (is_array($announce) || is_object($announce)) {
            if (($this->announce = self::first_announce($announce)) && count($announce) > 1) {
                return $this->{'announce-list'} = self::announce_list($announce);
            } else {
                return $this->announce;
            }
        }
        if (!isset($this->announce) && $announce) {
            return $this->announce = (string) $announce;
        }
        unset($this->announce);
    }

    /**
     * @param null $comment
     *
     * @return null|string
     */
    public function comment($comment = null)
    {
        return is_null($comment) ?
            isset($this->comment) ? $this->comment : null :
            $this->touch($this->comment = (string) $comment);
    }

    /**
     * @param null $name
     *
     * @return null
     */
    public function name($name = null)
    {
        return is_null($name) ?
            isset($this->info['name']) ? $this->info['name'] : null :
            $this->touch($this->info['name'] = (string) $name);
    }

    /**
     * @param null $private
     *
     * @return bool|null
     */
    public function is_private($private = null)
    {
        return is_null($private) ?
            !empty($this->info['private']) :
            $this->touch($this->info['private'] = $private ? 1 : 0);
    }

    /**
     * @param null $urls
     *
     * @return null
     */
    public function url_list($urls = null)
    {
        return is_null($urls) ?
            isset($this->{'url-list'}) ? $this->{'url-list'} : null :
            $this->touch($this->{'url-list'} = is_string($urls) ? $urls : (array) $urls);
    }

    /**
     * @param null $urls
     *
     * @return null
     */
    public function httpseeds($urls = null)
    {
        return is_null($urls) ?
            isset($this->httpseeds) ? $this->httpseeds : null :
            $this->touch($this->httpseeds = (array) $urls);
    }

    /**
     * @return null
     */
    public function piece_length()
    {
        return isset($this->info['piece length']) ?
            $this->info['piece length'] :
            null;
    }

    /**
     * @return null|string
     */
    public function hash_info()
    {
        return isset($this->info) ?
            sha1(self::encode($this->info)) :
            null;
    }

    /**
     * @param null $precision
     *
     * @return array
     */
    public function content($precision = null)
    {
        $files = array();
        if (isset($this->info['files']) && is_array($this->info['files'])) {
            foreach ($this->info['files'] as $file) {
                $files[self::path($file['path'], $this->info['name'])] = $precision ?
                    FileSystem::format($file['length'], $precision) :
                    $file['length'];
            }
        } elseif (isset($this->info['name'])) {
            $files[$this->info['name']] = $precision ?
                FileSystem::format($this->info['length'], $precision) :
                $this->info['length'];
        }
        return $files;
    }

    /**
     * @return array
     */
    public function offset()
    {
        $files = array();
        $size = 0;
        if (isset($this->info['files']) && is_array($this->info['files'])) {
            foreach ($this->info['files'] as $file) {
                $files[self::path($file['path'], $this->info['name'])] = array(
                    'startpiece' => floor($size / $this->info['piece length']),
                    'offset' => fmod($size, $this->info['piece length']),
                    'size' => $size += $file['length'],
                    'endpiece' => floor($size / $this->info['piece length'])
                );
            }
        } elseif (isset($this->info['name'])) {
            $files[$this->info['name']] = array(
                'startpiece' => 0,
                'offset' => 0,
                'size' => $this->info['length'],
                'endpiece' => floor($this->info['length'] / $this->info['piece length'])
            );
        }
        return $files;
    }

    /**
     * @param null $precision
     *
     * @return int|string
     */
    public function size($precision = null)
    {
        $size = 0;
        if (isset($this->info['files']) && is_array($this->info['files'])) {
            foreach ($this->info['files'] as $file) {
                $size += $file['length'];
            }
        } elseif (isset($this->info['name'])) {
            $size = $this->info['length'];
        }
        return is_null($precision) ?
            $size :
            FileSystem::format($size, $precision);
    }

    /**
     * @param null $announce
     * @param null $hash_info
     * @param int $timeout
     *
     * @return array|bool
     */
    public function scrape($announce = null, $hash_info = null, $timeout = self::timeout)
    {
        $packed_hash = urlencode(pack('H*', $hash_info ? $hash_info : $this->hash_info()));
        $handles = $scrape = array();
        if (!function_exists('curl_multi_init')) {
            return self::set_error(new Exception('Install CURL with "curl_multi_init" enabled'));
        }
        $curl = curl_multi_init();
        foreach ((array) ($announce ? $announce : $this->announce()) as $tier) {
            foreach ((array) $tier as $tracker) {
                $tracker = str_ireplace(array('udp://', '/announce', ':80/'), array(
                    'http://',
                    '/scrape',
                    '/'
                ), $tracker);
                if (isset($handles[$tracker])) {
                    continue;
                }
                $handles[$tracker] = curl_init($tracker . '?info_hash=' . $packed_hash);
                curl_setopt($handles[$tracker], CURLOPT_RETURNTRANSFER, true);
                curl_setopt($handles[$tracker], CURLOPT_TIMEOUT, $timeout);
                curl_multi_add_handle($curl, $handles[$tracker]);
            }
        }
        do {
            while (($state = curl_multi_exec($curl, $running)) == CURLM_CALL_MULTI_PERFORM) {
                ;
            }
            if ($state != CURLM_OK) {
                continue;
            }
            while ($done = curl_multi_info_read($curl)) {
                $info = curl_getinfo($done['handle']);
                $tracker = explode('?', $info['url'], 2);
                $tracker = array_shift($tracker);
                if (empty($info['http_code'])) {
                    $scrape[$tracker] = self::set_error(new Exception('Tracker request timeout (' . $timeout . 's)'),
                        true);
                    continue;
                } elseif ($info['http_code'] != 200) {
                    $scrape[$tracker] = self::set_error(new Exception('Tracker request failed (' . $info['http_code'] . ' code)'),
                        true);
                    continue;
                }
                $data = curl_multi_getcontent($done['handle']);
                $stats = self::decode_data($data);
                curl_multi_remove_handle($curl, $done['handle']);
                $scrape[$tracker] = empty($stats['files']) ?
                    self::set_error(new Exception('Empty scrape data'), true) :
                    array_shift($stats['files']) + (empty($stats['flags']) ? array() : $stats['flags']);
            }
        } while ($running);
        curl_multi_close($curl);
        return $scrape;
    }

    /**
     * @param null $filename
     *
     * @return int
     */
    public function save($filename = null)
    {
        return file_put_contents(is_null($filename) ? $this->info['name'] . '.torrent' : $filename,
            $this->encode($this));
    }

    /**
     * @param bool $html
     *
     * @return string
     */
    public function magnet($html = true)
    {
        $ampersand = $html ? '&amp;' : '&';
        return sprintf('magnet:?xt=urn:btih:%2$s%1$sdn=%3$s%1$sxl=%4$d%1$str=%5$s', $ampersand, $this->hash_info(),
            urlencode($this->name()), $this->size(), implode($ampersand . 'tr=', self::untier($this->announce())));
    }

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
     * @param $string
     *
     * @return string
     */
    private static function encode_string($string)
    {
        return strlen($string) . ':' . $string;
    }

    /**
     * @param $integer
     *
     * @return string
     */
    private static function encode_integer($integer)
    {
        return 'i' . $integer . 'e';
    }

    /**
     * @param $array
     *
     * @return string
     */
    private static function encode_array($array)
    {
        if (self::is_list($array)) {
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

    /**
     * @param $string
     *
     * @return array
     */
    protected static function decode($string)
    {
        $data = is_file($string) || self::url_exists($string) ?
            self::file_get_contents($string) :
            $string;
        return (array) self::decode_data($data);
    }

    /**
     * @param $data
     *
     * @return array|bool|int|string
     */
    private static function decode_data(& $data)
    {
        switch (self::char($data)) {
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
    private static function decode_dictionary(& $data)
    {
        $dictionary = array();
        $previous = null;
        while (($char = self::char($data)) != 'e') {
            if ($char === false) {
                return self::set_error(new Exception('Unterminated dictionary'));
            }
            if (!ctype_digit($char)) {
                return self::set_error(new Exception('Invalid dictionary key'));
            }
            $key = self::decode_string($data);
            if (isset($dictionary[$key])) {
                return self::set_error(new Exception('Duplicate dictionary key'));
            }
            if ($key < $previous) {
                return self::set_error(new Exception('Missorted dictionary key'));
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
    private static function decode_list(& $data)
    {
        $list = array();
        while (($char = self::char($data)) != 'e') {
            if ($char === false) {
                return self::set_error(new Exception('Unterminated list'));
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
    private static function decode_string(& $data)
    {
        if (self::char($data) === '0' && substr($data, 1, 1) != ':') {
            self::set_error(new Exception('Invalid string length, leading zero'));
        }
        if (!$colon = @strpos($data, ':')) {
            return self::set_error(new Exception('Invalid string length, colon not found'));
        }
        $length = intval(substr($data, 0, $colon));
        if ($length + $colon + 1 > strlen($data)) {
            return self::set_error(new Exception('Invalid string, input too short for string length'));
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
    private static function decode_integer(& $data)
    {
        $start = 0;
        $end = strpos($data, 'e');
        if ($end === 0) {
            self::set_error(new Exception('Empty integer'));
        }
        if (self::char($data) == '-') {
            $start++;
        }
        if (substr($data, $start, 1) == '0' && $end > $start + 1) {
            self::set_error(new Exception('Leading zero in integer'));
        }
        if (!ctype_digit(substr($data, $start, $start ? $end - 1 : $end))) {
            self::set_error(new Exception('Non-digit characters in integer'));
        }
        $integer = substr($data, 0, $end);
        $data = substr($data, $end + 1);
        return 0 + $integer;
    }

    /**
     * @param $data
     * @param $piece_length
     *
     * @return array|bool
     */
    protected function build($data, $piece_length)
    {
        if (is_null($data)) {
            return false;
        } elseif (is_array($data) && self::is_list($data)) {
            return $this->info = $this->files($data, $piece_length);
        } elseif (is_dir($data)) {
            return $this->info = $this->folder($data, $piece_length);
        } elseif ((is_file($data) || self::url_exists($data)) && !self::is_torrent($data)) {
            return $this->info = $this->file($data, $piece_length);
        } else {
            return false;
        }
    }

    /**
     * @param null $void
     *
     * @return null
     */
    protected function touch($void = null)
    {
        $this->{'created by'} = 'Torrent RW PHP Class - http://github.com/adriengibrat/torrent-rw';
        $this->{'creation date'} = time();
        return $void;
    }

    /**
     * @param $exception
     * @param bool $message
     *
     * @return bool
     */
    protected static function set_error($exception, $message = false)
    {
        return (array_unshift(self::$_errors, $exception) && $message) ? $exception->getMessage() : false;
    }

    /**
     * @param $announce
     * @param array $merge
     *
     * @return array
     */
    protected static function announce_list($announce, $merge = array())
    {
        return array_map(create_function('$a', 'return (array) $a;'), array_merge((array) $announce, (array) $merge));
    }

    /**
     * @param $announce
     *
     * @return array|mixed
     */
    protected static function first_announce($announce)
    {
        while (is_array($announce)) {
            $announce = reset($announce);
        }
        return $announce;
    }


    /**
     * @param $path
     * @param $folder
     *
     * @return string
     */
    protected static function path($path, $folder)
    {
        array_unshift($path, $folder);
        return join(DIRECTORY_SEPARATOR, $path);
    }

    /**
     * @param $array
     *
     * @return bool
     */
    protected static function is_list($array)
    {
        foreach (array_keys($array) as $key) {
            if (!is_int($key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param $handle
     * @param $piece_length
     * @param bool $last
     *
     * @return bool|string
     */
    private function pieces($handle, $piece_length, $last = true)
    {
        static $piece, $length;
        if (empty($length)) {
            $length = $piece_length;
        }
        $pieces = null;
        while (!feof($handle)) {
            if (($length = strlen($piece .= fread($handle, $length))) == $piece_length) {
                $pieces .= FileSystem::pack($piece);
            } elseif (($length = $piece_length - $length) < 0) {
                return self::set_error(new Exception('Invalid piece length!'));
            }
        }
        fclose($handle);
        return $pieces . ($last && $piece ? FileSystem::pack($piece) : null);
    }

    /**
     * @param $file
     * @param $piece_length
     *
     * @return array|bool
     */
    private function file($file, $piece_length)
    {
        if (!$handle = self::fopen($file, $size = FileSystem::filesize($file))) {
            return self::set_error(new Exception('Failed to open file: "' . $file . '"'));
        }
        if (self::is_url($file)) {
            $this->url_list($file);
        }
        $path = explode(DIRECTORY_SEPARATOR, $file);
        return array(
            'length' => $size,
            'name' => end($path),
            'piece length' => $piece_length,
            'pieces' => $this->pieces($handle, $piece_length)
        );
    }

    /**
     * @param $files
     * @param $piece_length
     *
     * @return array
     */
    private function files($files, $piece_length)
    {
        if (!self::is_url(current($files))) {
            $files = array_map('realpath', $files);
        }
        sort($files);
        usort($files,
            create_function('$a,$b', 'return strrpos($a,DIRECTORY_SEPARATOR)-strrpos($b,DIRECTORY_SEPARATOR);'));
        $first = current($files);
        $root = dirname($first);
        if ($url = self::is_url($first)) {
            $this->url_list(dirname($root) . DIRECTORY_SEPARATOR);
        }
        $path = explode(DIRECTORY_SEPARATOR, dirname($url ? $first : realpath($first)));
        $pieces = null;
        $info_files = array();
        $count = count($files) - 1;
        foreach ($files as $i => $file) {
            if ($path != array_intersect_assoc($file_path = explode(DIRECTORY_SEPARATOR, $file), $path)) {
                self::set_error(new Exception('Files must be in the same folder: "' . $file . '" discarded'));
                continue;
            }
            if (!$handle = self::fopen($file, $filesize = FileSystem::filesize($file))) {
                self::set_error(new Exception('Failed to open file: "' . $file . '" discarded'));
                continue;
            }
            $pieces .= $this->pieces($handle, $piece_length, $count == $i);
            $info_files[] = array(
                'length' => $filesize,
                'path' => array_diff($file_path, $path)
            );
        }
        return array(
            'files' => $info_files,
            'name' => end($path),
            'piece length' => $piece_length,
            'pieces' => $pieces
        );
    }

    /**
     * @param $dir
     * @param $piece_length
     *
     * @return array
     */
    private function folder($dir, $piece_length)
    {
        return $this->files(self::scandir($dir), $piece_length);
    }

    /**
     * @param $data
     *
     * @return bool|string
     */
    private static function char($data)
    {
        return empty($data) ?
            false :
            substr($data, 0, 1);
    }


    /**
     * @param $file
     * @param null $size
     *
     * @return bool|resource
     */
    public static function fopen($file, $size = null)
    {
        if ((is_null($size) ? FileSystem::filesize($file) : $size) <= 2 * pow(1024, 3)) {
            return fopen($file, 'r');
        } elseif (PHP_OS != 'Linux') {
            return self::set_error(new Exception('File size is greater than 2GB. This is only supported under Linux'));
        } elseif (!is_readable($file)) {
            return false;
        } else {
            return popen('cat ' . escapeshellarg(realpath($file)), 'r');
        }
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
        return self::is_url($url) ?
            (bool) FileSystem::filesize($url) :
            false;
    }

    /**
     * @param $file
     * @param int $timeout
     *
     * @return bool
     */
    public static function is_torrent($file, $timeout = self::timeout)
    {
        return ($start = self::file_get_contents($file, $timeout, 0, 11))
        && $start === 'd8:announce'
        || $start === 'd10:created'
        || $start === 'd13:creatio'
        || substr($start, 0, 7) === 'd4:info'
        || substr($start, 0, 3) === 'd9:'; // @see https://github.com/adriengibrat/torrent-rw/pull/17
    }

    /**
     * @param $file
     * @param int $timeout
     * @param null $offset
     * @param null $length
     *
     * @return bool|mixed|string
     */
    public static function file_get_contents($file, $timeout = self::timeout, $offset = null, $length = null)
    {
        if (is_file($file) || ini_get('allow_url_fopen')) {
            $context = !is_file($file) && $timeout ?
                stream_context_create(array('http' => array('timeout' => $timeout))) :
                null;
            return !is_null($offset) ? $length ?
                @file_get_contents($file, false, $context, $offset, $length) :
                @file_get_contents($file, false, $context, $offset) :
                @file_get_contents($file, false, $context);
        } elseif (!function_exists('curl_init')) {
            return self::set_error(new Exception('Install CURL or enable "allow_url_fopen"'));
        }
        $handle = curl_init($file);
        if ($timeout) {
            curl_setopt($handle, CURLOPT_TIMEOUT, $timeout);
        }
        if ($offset || $length) {
            curl_setopt($handle, CURLOPT_RANGE, $offset . '-' . ($length ? $offset + $length - 1 : null));
        }
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
        $content = curl_exec($handle);
        $size = curl_getinfo($handle, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        curl_close($handle);
        return ($offset && $size == -1) || ($length && $length != $size) ? $length ?
            substr($content, $offset, $length) :
            substr($content, $offset) :
            $content;
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
                $list = array_merge($list, self::untier($tier)) :
                array_push($list, $tier);
        }
        return $list;
    }
}