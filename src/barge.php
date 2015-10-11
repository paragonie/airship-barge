<?php
/**
 * This script is the entry point for all Barge commands.
 */
define('AIRSHIP_ROOT', __DIR__);
$homedir = isset($_SERVER['HOME'])
    ? $_SERVER['HOME']
    : \posix_getpwuid(posix_getuid())['dir'];
define('AIRSHIP_USER_HOME', $homedir);
define('AIRSHIP_LOCAL_CONFIG', AIRSHIP_USER_HOME.'/.airship');

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
            'https://airship.paragonie.com/atc/'
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
    $classname = \preg_replace('#.*/([A-Za-z0-9_]+)\.php$#', '$1', $file);
    $index = \strtolower($classname);
    
    // Append to $commands array
    $commands[$index] = $classname;

    if ($argv[1] !== 'help') {
        // If this is the command the user passed...
        if ($index === $argv[1]) {
            // Instantiate this object
            $exec = \Airship\Barge\Command::getCommandStatic($classname);
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
$help = new \Airship\Barge\Commands\Help($commands);
$help->showAll = $showAll;
$help->storeConfig($config);
$help->fire(
    \array_values(
        \array_slice($argv, 2)
    )
);
$help->saveConfig();
exit(0);