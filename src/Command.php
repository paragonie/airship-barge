<?php
declare(strict_types=1);
namespace Airship\Barge;

use ParagonIE\Halite\Asymmetric\SignaturePublicKey;

/**
 * Class Command
 *
 * This is the base class for all barge commands.
 *
 * @package Airship\Barge
 */
abstract class Command
{
    const TAB_SIZE = 8;
    
    public $essential = false;
    public $display = 65535;
    public $name = 'CommandName';
    public $description = 'CLI description';
    public $tag = [
        'color' => '',
        'text' => ''
    ];
    public static $cache = []; // Cache references to other commands
    public static $userConfig; // Current user's configuration
    
    protected $config = []; // config.json
    
    // Database adapter
    protected $db;
    
    // BASH COLORS
    protected $c = [
        '' => "\033[0;39m",
        'red'       => "\033[0;31m",
        'green'     => "\033[0;32m",
        'blue'      => "\033[1;34m",
        'cyan'      => "\033[1;36m",
        'silver'    => "\033[0;37m",
        'yellow'    => "\033[0;93m"
    ];
    
    /**
     * Execute a command
     */
    abstract public function fire(array $args = []);
    
    /**
     * Return a command
     * 
     * @param string $name
     * @param boolean $cache
     * @return Command
     */
    public function getCommandObject($name, $cache = true): Command
    {
        return self::getCommandStatic($name, $cache);
    }
    
    /**
     * Return a command (statically callable)
     * 
     * @param string $name
     * @param boolean $cache
     * @return Command
     */
    public static function getCommandStatic($name, $cache = true): Command
    {
        $_name = '\\Airship\\Barge\\Commands\\'.\ucfirst($name);
        if (!empty(self::$cache[$name])) {
            return self::$cache[$name];
        }
        if ($cache) {
            self::$cache[$name] = new $_name;
            return self::$cache[$name];
        }
        return new $_name;
    }

    /**
     * Grab the git commit hash
     *
     * @param string $projectRoot
     * @return string
     * @throws \Exception
     */
    public function getGitCommitHash(string $projectRoot): string
    {
        if (!\is_dir($projectRoot . '/.git')) {
            return '';
        }
        $command = "/usr/bin/env bash -c 'echo OK'";
        if (\rtrim(\shell_exec($command)) !== 'OK') {
            throw new \Exception("Can't invoke bash");
        }
        $dir = \getcwd();
        \chdir($projectRoot);
        $hash = \rtrim(\shell_exec("git rev-parse HEAD"));
        \chdir($dir);
        return $hash;
    }

    /**
     * Return the size of hte current terminal window
     *
     * @return array (int, int)
     */
    public function getScreenSize()
    {
        $output = [];
        \preg_match_all(
            "/rows.([0-9]+);.columns.([0-9]+);/",
            \strtolower(\exec('stty -a |grep columns')),
            $output
        );
        if (\sizeof($output) === 3) {
            return [
                'width' => $output[2][0],
                'height' => $output[1][0]
            ];
        }
        return [0, 0];
    }

    /**
     * Get a token for HTTP requests
     *
     * @param string $supplier
     * @return string|null
     */
    public function getToken($supplier)
    {
        if (!isset($this->config['suppliers'][$supplier])) {
            return null;
        }
        if (empty($this->config['suppliers'][$supplier]['token'])) {
            return null;
        }
        $v = $this->config['suppliers'][$supplier]['token'];
        return $v['selector'].':'.$v['validator'];
    }

    /**
     * @param $data
     */
    final public function storeConfig(array $data = [])
    {
        $this->config = $data;
    }

    /**
     * @return bool
     */
    final public function saveConfig(): bool
    {
        return \file_put_contents(
            AIRSHIP_LOCAL_CONFIG."/config.json",
            \json_encode($this->config, JSON_PRETTY_PRINT)
        ) !== false;
    }
    
    /**
     * Prompt the user for an input value
     * 
     * @param string $text
     * @return string
     */
    final protected function prompt(string $text = ''): string
    {
        static $fp = null;
        if ($fp === null) {
            $fp = \fopen('php://stdin', 'r');
        }
        echo $text;
        return \substr(\fgets($fp), 0, -1);
    }
    
    
    /**
     * Interactively prompts for input without echoing to the terminal.
     * Requires a bash shell or Windows and won't work with
     * safe_mode settings (Uses `shell_exec`)
     * 
     * @ref http://www.sitepoint.com/interactive-cli-password-prompt-in-php/
     */
    final protected function silentPrompt($text = "Enter Password:") 
    {
        if (\preg_match('/^win/i', PHP_OS)) {
            $vbscript = sys_get_temp_dir() . 'prompt_password.vbs';
            file_put_contents(
                $vbscript,
                'wscript.echo(InputBox("'. \addslashes($text) . '", "", "password here"))'
            );
            $command = "cscript //nologo " . \escapeshellarg($vbscript);
            $password = \rtrim(
                \shell_exec($command)
            );
            \unlink($vbscript);
            return $password;
        } else {
            $command = "/usr/bin/env bash -c 'echo OK'";
            if (\rtrim(\shell_exec($command)) !== 'OK') {
                throw new \Exception("Can't invoke bash");
            }
            $command = "/usr/bin/env bash -c 'read -s -p \"". addslashes($text). "\" mypassword && echo \$mypassword'";
            $password = \rtrim(\shell_exec($command));
            echo "\n";
            return $password;
        }
    }

    /**
     * @return array
     * @throws \Exception
     */
    final protected function getSkyport(): array
    {
        $sp = $this->config['skyports'];
        if (empty($sp)) {
            throw new \Exception("No skyports configured");
        }
        if (\count($sp) === 1) {
            $ret = \array_shift($sp);
            return [
                $ret['url'],
                new SignaturePublicKey(
                    \Sodium\hex2bin($ret['public_key'])
                )
            ];
        }
        $k = \array_keys($sp);
        $i = $k[\random_int(0, \count($sp) - 1)];
        $ret = $sp[$i];
        return [
            $ret['url'],
            new SignaturePublicKey(
                \Sodium\hex2bin($ret['public_key'])
            )
        ];
    }
    
    /**
     * Display the usage information for this command.
     *
     * @param array $args - CLI arguments
     * @echo
     */
    public function usageInfo(array $args = [])
    {
        $TAB = str_repeat(' ', self::TAB_SIZE);
        $HTAB = str_repeat(' ', ceil(self::TAB_SIZE / 2));
        
        echo $HTAB, 'Airship / Barge - ', $this->name, "\n\n";
        echo $TAB, $this->description, "\n\n";
    }
}
