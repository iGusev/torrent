<?php

namespace League\Torrent;

use League\Torrent\Helper\Decoder;
use League\Torrent\Helper\Encoder;
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
     * @var int
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
            throw new \Exception('Invalid piece lenth, must be between 32 and 4096');
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

    public static function createFromTorrentFile($filename, $meta = array())
    {
        return self::setMeta(new self(), file_get_contents($filename), $meta);
    }

    public static function createFromUrl($url, $meta = array())
    {
        if (!FileSystem::url_exists($url)) {
            throw new \InvalidArgumentException('Url is not valud');
        }

        return self::setMeta(new self(), self::downloadTorrent($url), $meta);
    }

    public static function createFromFilesList(array $list, $meta = array())
    {
        $instance = new self;
        if ($instance->build($list, 256 * 1024)) {
            $instance->touch();
        }

        return self::setMeta($instance, '', $meta);
    }

    public static function setMeta($instance, $data = '', $meta = array())
    {
        if(strlen($data))
            $meta = array_merge($meta, (array) Decoder::decode_data($data));

        foreach ($meta as $key => $value) {
            $instance->{$key} = $value;
        }

        return $instance;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return Encoder::encode($this);
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
     * @return string
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
            sha1(Encoder::encode($this->info)) :
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
                $files[FileSystem::path($file['path'], $this->info['name'])] = $precision ?
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
                $files[FileSystem::path($file['path'], $this->info['name'])] = array(
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
            throw new \Exception('Install CURL with "curl_multi_init" enabled');
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
                $stats = Decoder::decode_data($data);
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
            Encoder::encode($this));
    }

    /**
     * @param bool $html
     *
     * @return string
     */
    public function magnet($html = true)
    {
        $ampersand = $html ? '&amp;' : '&';
        return sprintf(
            'magnet:?xt=urn:btih:%2$s%1$sdn=%3$s%1$sxl=%4$d%1$str=%5$s',
            $ampersand,
            $this->hash_info(),
            urlencode($this->name()),
            $this->size(),
            implode($ampersand . 'tr=', FileSystem::untier($this->announce()))
        );
    }

    /**
     * @param $string
     *
     * @return array
     */
    protected static function decode($string)
    {

        if (is_file($string)) {
            $data = file_get_contents($string);
        } elseif (FileSystem::url_exists($string)) {
            $data = self::downloadTorrent($string);
        } else {
            $data = $string;
        }

        return (array) Decoder::decode_data($data);
    }

    /**
     * @param $data
     * @param integer $piece_length
     *
     * @return array|bool
     */
    protected function build($data, $piece_length)
    {
        if (is_null($data)) {
            return false;
        } elseif (is_array($data) && FileSystem::is_list($data)) {
            return $this->info = $this->files($data, $piece_length);
        } elseif (is_dir($data)) {
            return $this->info = $this->folder($data, $piece_length);
        } elseif (
            (is_file($data) && !self::is_torrent(file_get_contents($data)))
            || (FileSystem::url_exists($data) && !self::is_torrent(self::downloadTorrent($data)))
        ) {
            return $this->info = $this->file($data, $piece_length);
        }

        return false;
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
                throw new \Exception('Invalid piece length!');
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
            throw new \Exception('Failed to open file: "' . $file . '"');
        }
        if (FileSystem::is_url($file)) {
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
        if (!FileSystem::is_url(current($files))) {
            $files = array_map('realpath', $files);
        }
        sort($files);
        usort($files,
            create_function('$a,$b', 'return strrpos($a,DIRECTORY_SEPARATOR)-strrpos($b,DIRECTORY_SEPARATOR);'));
        $first = current($files);
        $root = dirname($first);
        if ($url = FileSystem::is_url($first)) {
            $this->url_list(dirname($root) . DIRECTORY_SEPARATOR);
        }
        $path = explode(DIRECTORY_SEPARATOR, dirname($url ? $first : realpath($first)));
        $pieces = null;
        $info_files = array();
        $count = count($files) - 1;
        foreach ($files as $i => $file) {
            if ($path != array_intersect_assoc($file_path = explode(DIRECTORY_SEPARATOR, $file), $path)) {
                throw new \Exception('Files must be in the same folder: "' . $file . '" discarded');
            }
            if (!$handle = self::fopen($file, $filesize = FileSystem::filesize($file))) {
                throw new \Exception('Failed to open file: "' . $file . '" discarded');
            }
            $pieces .= $this->pieces($handle, $piece_length, $count == $i);
            $info_files[] = array(
                'length' => $filesize,
                'path' => array_values(array_diff($file_path, $path))
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
        return $this->files(FileSystem::scandir($dir), $piece_length);
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
            throw new \Exception('File size is greater than 2GB. This is only supported under Linux');
        } elseif (!is_readable($file)) {
            return false;
        } else {
            return popen('cat ' . escapeshellarg(realpath($file)), 'r');
        }
    }

    /**
     * @param $file
     *
     * @return bool
     */
    public static function is_torrent($file)
    {
        $start = substr($file, 0, 11);

        return $start === 'd8:announce'
        || $start === 'd10:created'
        || $start === 'd13:creatio'
        || substr($start, 0, 7) === 'd4:info'
        || substr($start, 0, 3) === 'd9:';
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
}
