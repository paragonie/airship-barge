<?php
declare(strict_types=1);

use \Airship\Barge\Command;
use \Airship\Barge\Commands\Help;
use \ParagonIE\Halite\Halite;
/**
 * This script is the entry point for all Barge commands.
 */
\define('AIRSHIP_ROOT', __DIR__);
$homeDir = isset($_SERVER['HOME'])
    ? $_SERVER['HOME']
    : \posix_getpwuid(posix_getuid())['dir'];
define('AIRSHIP_USER_HOME', $homeDir);
define('AIRSHIP_LOCAL_CONFIG', AIRSHIP_USER_HOME . DIRECTORY_SEPARATOR . '.airship');

if (!\is_dir(AIRSHIP_LOCAL_CONFIG)) {
    \mkdir(AIRSHIP_LOCAL_CONFIG, 0700);
}

/**
 * 1. Register an autoloader for all the classes we use
 */
require __DIR__."/autoload.php";
require \dirname(__DIR__)."/vendor/autoload.php";

/**
 * 2. Load the configuration
 */
if (\is_readable(AIRSHIP_LOCAL_CONFIG."/config.json")) {
    // Allow people to edit the JSON config and define their own locations
    $config = \json_decode(
        \file_get_contents(AIRSHIP_LOCAL_CONFIG."/config.json"),
        true
    );
} else {
    // Sane defaults
    $config = [
        'skyports' => [
            [
                'url' =>
                    'https://airship.paragonie.com/atc/',
                'public_key' =>
                    '6386dabb7c57006fbbd5092d77d34398332f475f7340ae32d02cda6bc4295da6'
            ]

        ],
        'vendors' => []
    ];
}
if (!\extension_loaded('libsodium')) {
    // We need this
    die(
        "Please install libsodium and the libsodium-php extension from PECL\n\n".
        "\thttps://paragonie.com/book/pecl-libsodium/read/00-intro.md#installing-libsodium\n"
    );
}

/**
 * Let the user know precisely what's wrong, if anything is wrong.
 */
if (!Halite::isLibsodiumSetupCorrectly()) {
    // Easiest way to grab this info:
    \ob_start(); \phpinfo(); $data = \ob_get_clean();

    $version = '';
    foreach (\explode("\n", $data) as $line) {
        if (empty($line)) {
            continue;
        }
        if (\strpos($line, 'libsodium compiled version') !== false) {
            $version = \trim(\substr(\trim($line), -6));
            break;
        }
    }

    die(
        "Your libsodium is not setup correctly. Please make sure you have at least:\n\n" .
        "\tlibsodium     v1.0.10 (Installed: " . \Sodium\version_string() .")\n" .
        "\tlibsodium-php v1.0.6  (Installed: " . $version . ")\n"
    );
}

/**
 * 3. Process the CLI parameters
 */
$showAll = true;
if ($argc < 2) {
    // Default behavior: Display the help menu
    $argv[1] = 'help';
    $showAll = false;
    $argc = 2;
}


// Create a little cache for the Help command, if applicable. Doesn't contain objects.
$commands = [];

foreach (\glob(__DIR__.'/Commands/*.php') as $file) {
    // Let's build a queue of all the file names
    
    // Grab the filename from the Commands directory:
    $className = \preg_replace('#.*/([A-Za-z0-9_]+)\.php$#', '$1', $file);
    $index = \strtolower($className);
    
    // Append to $commands array
    $commands[$index] = $className;

    if ($argv[1] !== 'help') {
        // If this is the command the user passed...
        if ($index === $argv[1]) {
            // Instantiate this object
            $exec = Command::getCommandStatic($className);
            // Store the relevant storage devices in the command, in case they're needed
            $exec->storeConfig($config);
            // Execute it, passing the extra parameters to the command's fire() method
            try {
                $exec->fire(
                    \array_values(
                        \array_slice($argv, 2)
                    )
                );
            } catch (\Exception $e) {
                echo $e->getMessage(), "\n";
                $code = $e->getCode();
                echo $e->getTraceAsString(). "\n";
                exit($code > 0 ? $code : 255);
            }
            $exec->saveConfig();
            exit(0);
        }
    }
}

/**
 * 4. If all else fails, fall back to the help class...
 */
$help = new Help($commands);
$help->showAll = $showAll;
$help->storeConfig($config);
$help->fire(
    \array_values(
        \array_slice($argv, 2)
    )
);
$help->saveConfig();
exit(0);