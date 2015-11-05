<?php
namespace Airship\Barge\Commands;

use \Airship\Barge as Base;
use \ParagonIE\Halite\{
    Asymmetric\SignaturePublicKey,
    File
};

class Release extends Base\Command
{
    public $essential = true;
    public $name = 'Release';
    public $description = 'Release an update to your Gadget.';
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
            if ($this->signatureCheck($path, $manifest)) {
                if ($this->versionCheck($manifest)) {
                    $this->pushGadget(
                        $path,
                        $manifest,
                        \array_slice($args, 1)
                    );
                }
            } else {
                echo 'Invalid signature. Did you forget to sign it?', "\n";
            }
        }
    }
    
    /**
     * Push the gadget to the skyport
     * 
     * @param string $path
     * @param array $manifest
     * @param array $args
     */
    protected function pushGadget(
        string $path,
        array $manifest = [],
        array $args = []
    ) {
        $pharname = $manifest['supplier'].'--'.$manifest['name'].'.phar';
        $signature = $pharname.'.ed25519.sig';
        
        $skyport = $this->getSkyport();
        
        $result = \json_decode(
            Base\HTTP::post(
                $skyport.'upload',
                [
                    'token' => $this->getToken($manifest['supplier']),
                    'supplier' => $manifest['supplier'],
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
        
        if (isset($result['error'])) {
            echo $result['error'], "\n";
            exit(255);
        } else {
            var_dump($result);
        }
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
                $skyport.'upload/'.$manifest['supplier'].'/'.$manifest['name'],
                [
                    'token' => $this->getToken($manifest['supplier'])
                ]
            ),
            true
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
    
    /**
     * Check that the signature is valid for a given Phar
     * 
     * @param string $path
     * @param array $manifest
     * @return boolean
     */
    protected function signatureCheck(
        string $path,
        array $manifest = []
    ) {
        $supplier_name = $manifest['supplier'];
        $pharname = $supplier_name.'--'.$manifest['name'].'.phar';
        $signature = \file_get_contents($path.'/dist/'.$pharname.'.ed25519.sig');
        
        $supplier =& $this->config['suppliers'][$supplier_name];
        $numKeys = \count($supplier['signing_keys']);
        
        $verified = false;
        for ($i = 0; $i < $numKeys; ++$i) {
            // signing key
            $pubkey = new SignaturePublicKey(
                \Sodium\hex2bin($supplier['signing_keys'][$i]['public_key']),
                true
            );
            if (File::verifyFile($path.'/dist/'.$pharname, $pubkey, $signature)) {
                $verified = true;
            }
        }
        return $verified;
    }
}
