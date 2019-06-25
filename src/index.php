<?php

# const
define('SASEUL_DIR', dirname(__DIR__));
define('ROOT_DIR', __DIR__);

# autoload;
require_once("autoload.php");

# set ini;
session_start();
date_default_timezone_set('Asia/Seoul');
ini_set('memory_limit','1G');
header('Access-Control-Allow-Origin: *');

# render api;
$apiLoader = new \Saseul\Common\ApiLoader();
$apiLoader->main();

