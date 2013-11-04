<?php
/**
 * Bootstraps the common module
 */
$echelon = __DIR__;

$bart_src = "$echelon/vendor/box/bart/src";
$bart_common = "$bart_src/Bart/bart-common.php";

if (!file_exists($bart_common))
{
	echo <<<HELP
Cannot find required Bart code in the local path.
Have you run `composer install`?

See documentation on Bart and Composer

HELP;

	exit(1);
}

require_once $bart_common;

\Bart\Autoloader::register_autoload_path($bart_src);
\Bart\Autoloader::register_autoload_path("$echelon/src");

