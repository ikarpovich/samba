<?php

namespace Samba;

class SambaStreamWrapper
{
    const PROTOCOL = 'smb';

    /**
     * @var SambaClient
     */
    protected $client;

    /**
     * @var resource
     */
    protected $stream;

    /**
     * @var SambaUrl
     */
    protected $url;

    /**
     * @var string
     */
    protected $mode;

    /**
     * @var string
     */
    protected $tmpfile;

    /**
     * @var bool
     */
    protected $need_flush = false;

    /**
     * @var array
     */
    public $dir = array();

    /**
     * @var int
     */
    protected $dir_index = -1;

    /**
     * @var resource
     */
    public $context;

    /**
     * @param SambaClient $client
     */
    public function __construct(SambaClient $client = null)
    {
        $this->client = $client;
    }

    /**
     * @return SambaClient
     */
    protected function client()
    {
        if (null === $this->client) {
            $this->client = new SambaClient();
        }
        return $this->client;
    }

    /**
     * @param string $path
     * @param int $options
     * @return bool
     */
    public function dir_opendir($path, $options)
    {
        $url = $this->client()->parseUrl($path);

        switch ($url->getType()) {
            case SambaUrl::TYPE_HOST:
                if ($output = $this->client()->look($url)) {
                    $this->dir = $output['disk'];
                    $this->dir_index = 0;
                } else {
                    throw new SambaException("dir_opendir(): list failed for host '{$url->getHost()}'");
                }
                break;
            case SambaUrl::TYPE_SHARE:
            case SambaUrl::TYPE_PATH:
                $output = $this->client()->dir($url, '\*');
                if (isset($output['info'])) {
                    $this->dir = array_keys($output['info']);
                    $this->dir_index = 0;
                } else {
                    $this->dir = array();
                    $this->dir_index = 0;
                }
                break;
            default:
                throw new SambaException('dir_opendir(): error in URL');
        }

        return true;
    }

    /**
     * @return string
     */
    public function dir_readdir()
    {
        return ($this->dir_index < count($this->dir)) ? $this->dir[$this->dir_index++] : false;
    }

    /**
     * @return bool
     */
    public function dir_rewinddir()
    {
        $this->dir_index = 0;
    }

    /**
     * @return bool
     */
    public function dir_closedir()
    {
        $this->dir = array();
        $this->dir_index = -1;

        return true;
    }

    /**
     * @param string $url
     * @param string $mode
     * @param int $options
     * @param string $opened_path
     * @return bool
     */
    public function stream_open($url, $mode, $options, &$opened_path)
    {
        $this->mode = $mode;
        $this->url = $purl = $this->client()->parseUrl($url);
        if (!$purl->isPath()) {
            throw new SambaException('stream_open(): error in URL');
        }
        switch ($mode) {
            case 'r':
            case 'r+':
            case 'rb':
            case 'a':
            case 'a+':
                $this->tmpfile = tempnam('/tmp', 'smb.down.');
                $this->client()->get($purl, $this->tmpfile);
                break;
            case 'w':
            case 'w+':
            case 'wb':
            case 'x':
            case 'x+':
                $this->tmpfile = tempnam('/tmp', 'smb.up.');
        }
        $this->stream = fopen($this->tmpfile, $mode);

        return true;
    }

    public function stream_close()
    {
        fclose($this->stream);
    }

    /**
     * @param int $count
     * @return string
     */
    public function stream_read($count)
    {
        return fread($this->stream, $count);
    }

    /**
     * @param string $data
     * @return int
     */
    public function stream_write($data)
    {
        $this->need_flush = true;

        return fwrite($this->stream, $data);
    }

    /**
     * @return bool
     */
    public function stream_eof()
    {
        return feof($this->stream);
    }

    /**
     * @return int
     */
    public function stream_tell()
    {
        return ftell($this->stream);
    }

    /**
     * @param int $offset
     * @param int $whence
     * @return int
     */
    public function stream_seek($offset, $whence = SEEK_SET)
    {
        return fseek($this->stream, $offset, $whence);
    }

    /**
     * @return bool
     */
    public function stream_flush()
    {
        if ($this->mode != 'r' && $this->need_flush) {
            $this->client()->put($this->url, $this->tmpfile);
            $this->need_flush = false;
        }
        return true;
    }

    /**
     * @return array
     */
    public function stream_stat()
    {
        return $this->url_stat($this->url->getUrl());
    }

    /**
     * @param string $path
     * @return array
     */
    public function unlink($path)
    {
        $url = $this->client()->parseUrl($path);
        return $this->client()->del($url);
    }

    /**
     * @param string $path_from
     * @param string $path_to
     * @return array
     */
    public function rename($path_from, $path_to)
    {
        $url_from = $this->client()->parseUrl($path_from);
        $url_to = $this->client()->parseUrl($path_to);

        return $this->client()->rename($url_from, $url_to);
    }

    /**
     * @param string $path
     * @param int $mode
     * @param int $options
     * @return bool
     */
    public function mkdir($path, $mode, $options)
    {
        $url = $this->client()->parseUrl($path);
        $this->client()->mkdir($url);
        return true;
    }

    /**
     * @param $path
     * @return bool
     */
    public function rmdir($path)
    {
        $url = $this->client()->parseUrl($path);
        $this->client()->rmdir($url);
        return true;
    }

    /**
     * @param string $path
     * @param int $flags
     * @return array
     */
    public function url_stat($path, $flags = STREAM_URL_STAT_LINK)
    {
        $url = $this->client()->parseUrl($path);
        try {
            $info = $this->client()->info($url);
            return $this->get_stat($info);
        } catch (SambaException $e) {
            if ($flags & STREAM_URL_STAT_QUIET) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * @param array $info
     * @return array
     */
    protected function get_stat(array $info)
    {
        $isFile = (strpos($info['attr'], 'D') === false);
        $stat = ($isFile) ? $this->get_file_stat() : $this->get_dir_stat();

        $stat[7] = $stat['size']
                 = $info['size'];

        $stat[8] = $stat[9]
                 = $stat[10]
                 = $stat['atime']
                 = $stat['mtime']
                 = $stat['ctime']
                 = $info['time'];
        return $stat;
    }

    /**
     * @return array
     */
    protected function get_dir_stat()
    {
        return stat('/tmp');
    }

    /**
     * @return array
     */
    protected function get_file_stat()
    {
        return stat('/etc/passwd');
    }

    public function __destruct()
    {
        if ($this->tmpfile != '') {
            if ($this->need_flush) {
                $this->stream_flush();
            }
            unlink($this->tmpfile);
        }
    }

    /**
     * @return bool
     */
    public static function register()
    {
        return stream_wrapper_register(static::PROTOCOL, get_called_class());
    }

    /**
     * @return bool
     */
    public static function unregister()
    {
        return stream_wrapper_unregister(static::PROTOCOL);
    }

    /**
     * @return bool
     */
    public static function is_registered()
    {
        return in_array(static::PROTOCOL, stream_get_wrappers());
    }
}
