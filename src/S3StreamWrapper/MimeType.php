<?php

/**
 * Copyright (c) 2013 Gijs Kunze
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace S3StreamWrapper;


use Guzzle\Http\EntityBodyInterface;

class MimeType {
    private static $types = null;

    public static function getMimeType($path, EntityBodyInterface $data = null) {
        // TODO: Implement magic functionality

        if(self::$types === null) {
            self::loadMimeFile();
        }

        $info = pathinfo($path);

        $ext = strtolower($info['extension']);

        if(isset(self::$types[$ext])) {
            return self::$types[$ext];
        }

        return "binary/octet-stream";
    }

    private static function loadMimeFile() {
        $file = __DIR__ . "/../../data/mime.json";

        if(file_exists($file)) {
            $data = file_get_contents($file);
            $types = json_decode($data, true);
            if(is_array($types)) {
                self::$types = $types;
            } else {
                self::$types = array();
            }
        }
    }
}