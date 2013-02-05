<?php
/**
 * Copyright (c) 2013 Gijs Kunze
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
require_once __DIR__ . "/../vendor/autoload.php";


function main() {
    $files = array("/etc/mime.types", "http://svn.apache.org/repos/asf/httpd/httpd/branches/2.0.x/docs/conf/mime.types");

    foreach($files as $file) {
        if(file_exists($file)) {
            writeMimeJson(file_get_contents($file), __DIR__ . '/../data/mime.json');
            return;
        }
    }
}

function writeMimeJson($data, $destination) {
    $lines = array_map("trim", explode("\n", $data));

    $json = array();

    foreach($lines as $line) {
        // Remove comments
        $line = preg_replace("/#.*$/", "", $line);

        if(preg_match("/^\\s*(\\S+\\/\\S+)\\s+([\\w\\s]+)\\s*$/", $line, $m)) {
            $mime = $m[1];

            if(trim($m[2]) != "") {
                $exts = preg_split("/\\s+/", $m[2]);

                foreach($exts as $ext) {
                    $json[$ext] = $mime;
                }
            }
        }
    }

    file_put_contents($destination, json_encode($json));
}

main();