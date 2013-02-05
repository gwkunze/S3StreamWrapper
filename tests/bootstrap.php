<?php
/**
 * Copyright (c) 2013 Gijs Kunze
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
$loader = require_once __DIR__ . "/../vendor/autoload.php";
$loader->add('S3StreamWrapper\\', __DIR__);

$config_file = __DIR__ . "/config.ini";
if(!file_exists($config_file)) {
    $config_file = __DIR__ . "/config.ini.dist";
}
$GLOBALS['S3_TESTDATA'] = parse_ini_file($config_file);