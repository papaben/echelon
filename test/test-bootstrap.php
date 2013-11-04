<?php
/**
 * Bootstrap the test environment
 */
error_reporting(E_ALL | E_STRICT);
$echelon_root = dirname(__DIR__);

require_once "$echelon_root/bootstrap.php";

Bart\Autoloader::register_autoload_path("$echelon_root/vendor/box/shmock/src");
Bart\Autoloader::register_autoload_path("$echelon_root/vendor/box/bart/test");
Bart\Autoloader::register_autoload_path("$echelon_root/test");

require_once 'log4php/Logger.php';
\Bart\Log4PHP::initForConsole('trace');

