<?php
namespace Airship\Barge\Commands;

use Airship\Barge as Base;

class Push extends Base\Command
{
    public $essential = true;
    public $name = 'Push';
    public $description = 'Push an update to your Gadget.';
    public $display = 3;
    
    /**
     * Execute the login command
     *
     * @param array $args - CLI arguments
     * @echo
     * @return null
     */
    public function fire(array $args = [])
    {
        $path = \count($args) > 0
            ? $args[0]
            : \getcwd();
        
        if (!\is_readable($path.'/gadget.json')) {
            die("Could not find gadget.json");
        }
        if (\is_readable($path.'/gadget.json')) {
            $manifest = \json_decode(
                \file_get_contents($path.'/gadget.json'),
                true
            );
            if ($this->versionCheck($manifest)) {
                $this->pushGadget(
                    $path,
                    $manifest,
                    \array_slice($args, 1)
                );
            }
        }
    }
    
    protected function pushGadget($path, array $manifest = [], array $args = [])
    {
        $pharname = $manifest['vendor'].'--'.$manifest['name'].'.phar';
        $signature = $pharname.'.ed25519.sig';
        
        $skyport = $this->getSkyport();
        
        $result = \json_decode(
            Base\HTTP::post(
                $skyport.'upload',
                [
                    'token' => $this->getToken($manifest['vendor']),
                    'vendor' => $manifest['vendor'],
                    'package' => $manifest['name'],
                    'version' => isset($manifest['version'])
                        ? $manifest['version']
                        : '',
                    'phar' => new \CURLFile(
                        $path.'/dist/'.$pharname,
                        'application/octet-stream'
                    ),
                    'signature' => new \CURLFile(
                        $path.'/dist/'.$signature,
                        'application/octet-stream'
                    )
                ]
            ),
            true
        );
        
        var_dump($result);
    }
    
    /**
     * Check that we've updated our version string since the last push.
     * 
     * @param array $manifest
     * @return boolean
     */
    protected function versionCheck(array $manifest = [])
    {
        $skyport = $this->getSkyport();
        
        $result = \json_decode(
            Base\HTTP::post(
                $skyport.'upload/'.$manifest['vendor'].'/'.$manifest['name'],
                [
                    'token' => $this->getToken($manifest['vendor'])
                ]
            )
        );
        if (isset($result['latest'])) {
            if ($result['latest'] !== $manifest['version']) {
                return true;
            }
            while (true) {
                echo 'The current version you are trying to push, ', 
                    $this->c['yellow'], $manifest['version'], $this->c[''], ",\n";
                echo 'is already in the system. (The latest version pushed is ',
                    $this->c['yellow'], $result['version'], $this->c[''], ".\n\n";
                
                // Get and process the user's response
                $choice = $this->prompt('Push a new release anyway? (y/N)');
                switch ($choice) {
                    case 'YES':
                    case 'yes':
                    case 'Y':
                    case 'y':
                        return true;
                    case 'N':
                    case 'NO':
                    case 'n':
                    case 'no':
                    case '': // Just pressing enter means "don't push it"!
                        return false;
                    default:
                        echo "\n", $this->c['yellow'], 'Invalid response. Please enter yes or no.', $this->c[''], "\n";
                }
            }
        }
        // No latest version to be found? Just let it go through.
        return true;
    }
}