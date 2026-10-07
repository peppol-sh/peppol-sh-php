<?php

declare(strict_types=1);

// `composer test` runs this file. It starts PHPUnit with the configuration
// file for the installed major version: the XML schema of PHPUnit 9.6 (the
// last version for PHP 7.4 and 8.0) and that of PHPUnit 10.5 and later have
// no common set of attributes that is strict in both.
//
//     composer test -- --filter WebhookTest

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

$version = \PHPUnit\Runner\Version::id();
$config = version_compare($version, '10.0.0', '<') ? 'phpunit9.xml.dist' : 'phpunit.xml.dist';

$command = [PHP_BINARY, $root . '/vendor/phpunit/phpunit/phpunit', '--configuration', $root . '/' . $config];
$arguments = $_SERVER['argv'] ?? [];
foreach (array_slice(is_array($arguments) ? $arguments : [], 1) as $argument) {
    if (is_string($argument)) {
        $command[] = $argument;
    }
}

$process = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $root);
if (!is_resource($process)) {
    fwrite(STDERR, 'Could not start PHPUnit.' . PHP_EOL);
    exit(1);
}

exit(proc_close($process));
