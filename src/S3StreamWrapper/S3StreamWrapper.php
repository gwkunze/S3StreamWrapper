<?php
/**
 * Copyright (c) 2013 Gijs Kunze
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace S3StreamWrapper;

use Aws\S3\Exception\NoSuchKeyException;
use Aws\S3\S3Client;
use Guzzle\Http\EntityBody;
use Guzzle\Http\EntityBodyInterface;
use Guzzle\Service\Resource\Model;

class S3StreamWrapper
{
    private static $clientClass = "Aws\\S3\\S3Client";

    public $context;

    const STAT_DIR = 0040777;
    const STAT_FILE = 0100777;

    /**
     * @var S3Client
     */
    private $client;

    public static function register()
    {
        if (in_array("s3", stream_get_wrappers())) {
            return;
        }
        stream_wrapper_register("s3", "S3StreamWrapper\\S3StreamWrapper", STREAM_IS_URL);
    }

    public static function unregister()
    {
        if (!in_array("s3", stream_get_wrappers())) {
            return;
        }
        stream_wrapper_unregister("s3");
    }

    public static function setClientClass($class = "Aws\\S3\\S3Client")
    {
        self::$clientClass = $class;
    }

    public static function getClientClass()
    {
        return self::$clientClass;
    }

    public function __construct()
    {
    }

    public function __destruct()
    {
    }

    private function getOptions()
    {
        $context = $this->context;
        if ($context === null) {
            $context = stream_context_get_default();
        }
        $options = stream_context_get_options($context);
        return $options['s3'];
    }

    private function getSeparator()
    {
        $options = $this->getOptions();
        if (isset($options['separator'])) {
            return $options['separator'];
        }
        return "/";
    }

    private function getClient()
    {
        if (empty($this->client)) {
            $this->client = call_user_func(array(self::$clientClass, 'factory'), $this->getOptions());
        }
        return $this->client;
    }

    private function parsePath($path, $dir = false)
    {
        $parsed = parse_url($path);

        $bucket = $parsed['host'];
        $path = isset($parsed['path']) ? ltrim($parsed['path'], '/') : '';

        if (!$dir) {
            $path = rtrim($path, $this->getSeparator());
        }

        return array('bucket' => $bucket, 'path' => $path);
    }

    private $dir_list = null;
    private $dir_list_options = null;
    private $dir_list_marker = null;
    private $dir_list_has_more = false;

    /**
     * @return bool
     */
    public function dir_closedir()
    {
        $this->dir_list = null;
        $this->dir_list_options = null;
        $this->dir_list_marker = null;
        $this->dir_list_has_more = false;
    }

    /**
     * @param string $path
     * @param int $options
     * @return bool
     */
    public function dir_opendir($path, $options)
    {
        $parsed = $this->parsePath($path, true);

        $this->dir_list_options = array(
            'Bucket' => $parsed['bucket'],
            'Delimiter' => $this->getSeparator(),
        );

        if (strlen($parsed['path']) > 0) {
            $this->dir_list_options['Prefix'] = $parsed['path'];
        }

        $this->dir_list_has_more = true;

        return true;
    }

    /**
     * @return string
     */
    public function dir_readdir()
    {
        if (is_array($this->dir_list) && count($this->dir_list) > 0) {
            return array_shift($this->dir_list);
        }

        if ($this->dir_list_has_more) {
            $client = $this->getClient();
            $options = $this->dir_list_options;
            if ($this->dir_list_marker !== null) {
                $options['Marker'] = $this->dir_list_marker;
            }
            /** @var $response Model */
            $response = $client->listObjects($options);

            if ($response->get("IsTruncated")) {
                $this->dir_list_has_more = true;
                $this->dir_list_marker = $response->get("NextMarker");
            } else {
                $this->dir_list_has_more = false;
            }
            $contents = $response->get("Contents");
            $this->dir_list = array();
            if(is_array($contents)) {
                foreach ($contents as $file) {
                    $this->dir_list[] = $file['Key'];
                }
            }
            $prefixes = $response->get("CommonPrefixes");
            if(is_array($prefixes)) {
                foreach ($prefixes as $dir) {
                    $this->dir_list[] = $dir['Prefix'];
                }
            }

            return $this->dir_readdir();
        }

        return false;
    }

    /**
     * @return bool
     */
    public function dir_rewinddir()
    {
        $this->dir_list_has_more = true;
        $this->dir_list = array();
        $this->dir_list_marker = null;
    }

    /**
     * @param string $path
     * @param int $mode
     * @param int $options
     * @return bool
     */
    public function mkdir($path, $mode, $options)
    {
        $parsed = $this->parsePath($path);

        $object = array(
            'Body' => '',
            'Bucket' => $parsed['bucket'],
            'Key' => $parsed['path'] . $this->getSeparator(),
        );

        if (isset($options['acl'])) {
            $object['ACL'] = $options['acl'];
        }

        $client = $this->getClient();
        $client->putObject($object);

        return true;
    }

    /**
     * @param string $path_from
     * @param string $path_to
     * @throws \Exception
     * @return bool
     */
    public function rename($path_from, $path_to)
    {
        // Pretty complex
        // TODO: Sane implementation
        trigger_error("Rename has not been implemented (yet)", E_USER_ERROR);
    }

    /**
     * @param string $path
     * @param int $options
     * @return bool
     */
    public function rmdir($path, $options)
    {
        $parsed = $this->parsePath($path);

        $client = $this->getClient();

        try {
            $result = $client->headObject(array(
                'Bucket' => $parsed['bucket'],
                'Key' => $parsed['path'] . $this->getSeparator(),
            ));
        } catch (NoSuchKeyException $e) {
            return false;
        }

        if($result['ContentLength'] != 0) {
            return false;
        }

        $client->deleteObject(array(
            'Bucket' => $parsed['bucket'],
            'Key' => $parsed['path'] . $this->getSeparator(),
        ));

        return true;
    }

    /**
     * @param int $cast_as
     * @return resource
     */
    public function stream_cast($cast_as)
    {
        return false;
    }

    /**
     * Close the stream
     */
    public function stream_close()
    {
        if ($this->data == null) {
            return;
        }

        $this->stream_flush();

        $this->data = null;
        $this->path = null;
        $this->save = false;
        $this->dirty = null;
        $this->metadata = array();
    }

    /**
     * @return bool
     */
    public function stream_eof()
    {
        if ($this->data === null) {
            return true;
        }

        return $this->data->ftell() === $this->data->getSize();
    }

    /**
     * @return bool
     */
    public function stream_flush()
    {
        if ($this->save === false || $this->dirty == false || $this->data === null) {
            return;
        }

        $client = $this->getClient();

        $options = $this->getOptions();

        $object = array(
            'Bucket' => $this->path['bucket'],
            'Key' => $this->path['path'],
            'Body' => $this->data,
        );

        if (isset($options['acl'])) {
            $object['ACL'] = $options['acl'];
        }

        if (isset($options['ContentType'])) {
            $object['ContentType'] = $options['ContentType'];
        } else {
            $object['ContentType'] = MimeType::getMimeType($this->path['path'], $this->data);
        }

        $headers = array('CacheControl', 'ContentDisposition', 'ContentEncoding', 'ContentLanguage', 'Expires');
        foreach ($headers as $header) {
            if (isset($options[$header])) {
                $object[$header] = $options[$header];
            }
        }

        if (isset($options['metadata'])) {
            $object['Metadata'] = $options['metadata'];
        }

        $client->putObject($object);

        $this->dirty = false;
    }

    /**
     * @param string $operation (mode)
     * @return bool
     */
    public function stream_lock($operation)
    {
        return false;
    }

    private $path = null;
    /** @var EntityBodyInterface */
    private $data = null;
    private $save = false;
    private $dirty = false;
    private $metadata = array();

    /**
     * @param string $path
     * @param string $mode
     * @param int $options
     * @param string $opened_path
     * @throws \Exception
     * @return bool
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        // Load the file?
        $load = false;
        // Position of the filepointer? start or end
        $pos = 'start';
        // check if file exists, error if not exists
        $check = false;
        // Allow writing?
        $write = false;

        $mode = preg_replace("/b/", "", $mode);

        switch ($mode) {
            case "r":
                $load = true;
                $check = true;
                break;
            case "r+":
                $load = true;
                $write = true;
                break;
            case "w":
                $write = true;
                break;
            case "w+":
                $write = true;
                $load = true;
                break;
            case "a":
            case "a+":
                $load = true;
                $write = true;
                $pos = "end";
                break;
            case "x":
            case "x+":
                $check = true;
                $write = true;
                break;
            case "c":
            case "c+":
                trigger_error("Mode 'c' and 'c+' Not Supported in S3 Stream Wrapper (yet?)", E_USER_WARNING);
                return false;
            default:
                trigger_error("Invalid mode $mode", E_USER_WARNING);
                return false;
        }

        $this->data = null;
        $this->save = $write;
        $this->dirty = true;
        $this->path = $this->parsePath($path);

        $client = $this->getClient();

        if ($check && !$client->doesObjectExist($this->path['bucket'], $this->path['path'])) {
            trigger_error("File $path does not exist, can't open with mode $mode", E_USER_WARNING);
            return false;
        }

        if ($load) {
            $options = array(
                'Bucket' => $this->path['bucket'],
                'Key' => $this->path['path'],
            );
            /** @var $response Model */
            $response = $client->getObject($options);
            $this->data = $response['Body'];
            if ($pos === "end") {
                $this->data->seek(0, SEEK_END);
            } else {
                $this->data->seek(0, SEEK_SET);
            }
            $this->metadata = $response;
            $this->dirty = false;
        } else {
            $this->data = EntityBody::factory("");
        }

        return true;
    }

    /**
     * @param int $count
     * @return string
     */
    public function stream_read($count)
    {
        if ($this->data === null) {
            return false;
        }
        return $this->data->read($count);
    }

    /**
     * @param int $offset
     * @param int $whence
     * @return bool
     */
    public function stream_seek($offset, $whence = SEEK_SET)
    {
        if ($this->data === null) {
            return false;
        }

        return $this->data->seek($offset, $whence);
    }

    /**
     * @param int $option
     * @param int $arg1
     * @param int $arg2
     * @return bool
     */
    public function stream_set_option($option, $arg1, $arg2)
    {
        return false;
    }

    /**
     * @return array
     */
    public function stream_stat()
    {
        return $this->stat(self::STAT_FILE, $this->data->getSize(), time());
    }

    /**
     * @return int
     */
    public function stream_tell()
    {
        if ($this->data === null) {
            return 0;
        }
        return $this->data->ftell();
    }

    /**
     * @param int $new_size
     * @return bool
     */
    public function stream_truncate($new_size)
    {
        if ($this->data === null) {
            return false;
        }
        $this->data->setSize($new_size);
        return true;
    }

    /**
     * @param string $data
     * @return int
     */
    public function stream_write($data)
    {
        if ($this->data === null) {
            return 0;
        }
        $this->dirty = true;
        return $this->data->write($data);
    }

    /**
     * @param string $path
     * @return bool
     */
    public function unlink($path)
    {
        $parsed = $this->parsePath($path);

        $client = $this->getClient();

        $options = array(
            'Bucket' => $parsed['bucket'],
            'Key' => $parsed['path'],
        );

        $client->deleteObject($options);
    }

    /**
     * @param string $path
     * @param int $flags
     * @return array
     */
    public function url_stat($path, $flags)
    {
        $parsed = $this->parsePath($path, true);

        if ($parsed['path'] == "") {
            // Root is a directory
            return $this->stat(self::STAT_DIR, 0, time());
        }

        $options = array(
            'Bucket' => $parsed['bucket'],
            'Key' => $parsed['path'],
        );

        $client = $this->getClient();

        try {
            /** @var $response Model */
            $response = $client->headObject($options);
            // Path points to a file
            if ($parsed['path'][strlen($parsed['path']) - 1] == $this->getSeparator() && $response['ContentLength'] == 0) {
                return $this->stat(self::STAT_DIR, 0, strtotime($response['LastModified']));
            }
            return $this->stat(self::STAT_FILE, (int)$response['ContentLength'], strtotime($response['LastModified']));
        } catch (NoSuchKeyException $e) {
            // File not found, might be a directory
            $options = array(
                'Bucket' => $parsed['bucket'],
                'Prefix' => rtrim($parsed['path'], $this->getSeparator()) . $this->getSeparator(),
                'MaxKeys' => 1,
            );

            $result = $client->listObjects($options);
            if (count($result['Contents']) + count($result['CommonPrefixes'])) {
                return $this->stat(self::STAT_DIR, 0, time());
            }

            return false;
        }
    }

    private function stat($permission, $size, $mtime)
    {
        $data = array(
            'dev' => 0,
            'ino' => 0,
            'mode' => $permission,
            'nlink' => 1,
            'uid' => 0,
            'gid' => 0,
            'rdev' => 0,
            'size' => $size,
            'atime' => $mtime,
            'mtime' => $mtime,
            'ctime' => $mtime,
            'blksize' => -1,
            'blocks' => -1,
        );

        $result = array();
        foreach ($data as $key => $value) {
            $result[] = $value;
        }
        foreach ($data as $key => $value) {
            $result[$key] = $value;
        }

        return $result;
    }
}
