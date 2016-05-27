<?php
declare(strict_types=1);
namespace Airship\Barge\Commands;

use \Airship\Barge as Base;
use \ParagonIE\Halite\{
    Asymmetric\SignaturePublicKey,
    File
};

/**
 * Class Release
 *
 * Given a built and signed package, send it to the server so
 * it may be distributed to the Airships that use it.
 *
 * @package Airship\Barge\Commands
 */
class Release extends Base\Command
{
    public $essential = true;
    public $name = 'Release';
    public $description = 'Release your project for availability in Airship projects.';
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

        // Cabins:
        if (\is_readable($path.'/cabin.json')) {
            $manifest = \json_decode(
                \file_get_contents($path . '/cabin.json'),
                true
            );
            if ($this->pharSignatureCheck($path, $manifest)) {
                if ($this->versionCheck($manifest, 'cabin')) {
                    $this->pushCabin($path, $manifest);
                } else {
                    echo 'Aborted.', "\n";
                    exit(0);
                }
            } else {
                echo 'Invalid signature. Did you forget to sign it?', "\n";
                exit(255);
            }
        }

        // Gadgets:
        if (\is_readable($path.'/gadget.json')) {
            $manifest = \json_decode(
                \file_get_contents($path.'/gadget.json'),
                true
            );
            if ($this->pharSignatureCheck($path, $manifest)) {
                if ($this->versionCheck($manifest, 'gadget')) {
                    $this->pushGadget($path, $manifest);
                } else {
                    echo 'Aborted.', "\n";
                    exit(0);
                }
            } else {
                echo 'Invalid signature. Did you forget to sign it?', "\n";
                exit(255);
            }
        }

        // Motifs:
        if (\is_readable($path.'/src/motif.json')) {
            $manifest = \json_decode(
                \file_get_contents($path . '/src/motif.json'),
                true
            );
            if ($this->zipSignatureCheck($path, $manifest)) {
                if ($this->versionCheck($manifest, 'motif')) {
                    $this->pushMotif($path, $manifest);
                } else {
                    echo 'Aborted.', "\n";
                    exit(0);
                }
            } else {
                echo 'Invalid signature. Did you forget to sign it?', "\n";
                exit(255);
            }
        }

        echo 'Could not find manifest file.', "\n";
        exit(255);
    }
    /**
     * Push the gadget to the skyport
     *
     * @param string $path
     * @param array $manifest
     */
    protected function pushCabin(
        string $path,
        array $manifest = []
    ) {
        $pharName = $manifest['supplier'].'.'.$manifest['name'].'.phar';
        $signature = $pharName.'.ed25519.sig';

        list ($skyport, $publicKey) = $this->getSkyport();

        $result = Base\HTTP::postSignedJSON(
            $skyport.'upload',
            $publicKey,
            [
                'token' => $this->getToken($manifest['supplier']),
                'supplier' => $manifest['supplier'],
                'package' => $manifest['name'],
                'version' => isset($manifest['version'])
                    ? $manifest['version']
                    : '',
                'type' => 'cabin',
                'phar' => new \CURLFile(
                    $path.'/dist/'.$pharName,
                    'application/octet-stream',
                    $pharName
                ),
                'signature' => new \CURLFile(
                    $path.'/dist/'.$signature,
                    'application/octet-stream',
                    $signature
                )
            ]
        );

        if (isset($result['error'])) {
            echo $result['error'], "\n";
            exit(255);
        }

        echo 'Transfer complete.', "\n";
        exit(0);
    }
    
    /**
     * Push the gadget to the skyport
     * 
     * @param string $path
     * @param array $manifest
     */
    protected function pushGadget(
        string $path,
        array $manifest = []
    ) {
        $pharName = $manifest['supplier'].'.'.$manifest['name'].'.phar';
        $signature = $pharName.'.ed25519.sig';

        list ($skyport, $publicKey) = $this->getSkyport();

        $result = Base\HTTP::postSignedJSON(
            $skyport.'upload',
            $publicKey,
            [
                'token' => $this->getToken($manifest['supplier']),
                'supplier' => $manifest['supplier'],
                'package' => $manifest['name'],
                'version' => isset($manifest['version'])
                    ? $manifest['version']
                    : '',
                'type' => 'gadget',
                'phar' => new \CURLFile(
                    $path.'/dist/'.$pharName,
                    'application/octet-stream',
                    $pharName
                ),
                'signature' => new \CURLFile(
                    $path.'/dist/'.$signature,
                    'application/octet-stream',
                    $signature
                )
            ]
        );
        
        if (isset($result['error'])) {
            echo $this->c['red'], 'Server error:', $this->c[''], "\n";
            echo $result['error'], "\n";
            exit(255);
        }

        echo 'Transfer complete.', "\n";
        exit(0);
    }
    
    /**
     * Push the gadget to the skyport
     *
     * @param string $path
     * @param array $manifest
     */
    protected function pushMotif(
        string $path,
        array $manifest = []
    ) {
        $zipName = $manifest['supplier'].'.'.$manifest['name'].'.zip';
        $signature = $zipName.'.ed25519.sig';

        list ($skyport, $publicKey) = $this->getSkyport();

        $result = Base\HTTP::postSignedJSON(
            $skyport.'upload',
            $publicKey,
            [
                'token' => $this->getToken($manifest['supplier']),
                'supplier' => $manifest['supplier'],
                'package' => $manifest['name'],
                'version' => isset($manifest['version'])
                    ? $manifest['version']
                    : '',
                'type' => 'motif',
                'zip' => new \CURLFile(
                    $path.'/dist/'.$zipName,
                    'application/octet-stream',
                    $zipName
                ),
                'signature' => new \CURLFile(
                    $path.'/dist/'.$signature,
                    'application/octet-stream',
                    $signature
                )
            ]
        );

        if (isset($result['error'])) {
            echo $result['error'], "\n";
            exit(255);
        }

        echo 'Transfer complete.', "\n";
        exit(0);
    }
    
    /**
     * Check that we've updated our version string since the last push.
     * 
     * @param array $manifest
     * @return boolean
     */
    protected function versionCheck(array $manifest = [], string $type = '')
    {
        list ($skyport, $publicKey) = $this->getSkyport();
        
        $result = Base\HTTP::postSignedJSON(
            $skyport.'package/'.$manifest['supplier'].'/'.$manifest['name'].'/version',
            $publicKey,
            [
                'type' => $type,
                'token' => $this->getToken($manifest['supplier'])
            ]
        );
        if (isset($result['latest'])) {
            if ($result['latest'] !== $manifest['version']) {
                return true;
            }
            while (true) {
                echo 'The current version you are trying to push, ', 
                    $this->c['yellow'], $manifest['version'], $this->c[''], ",\n";
                echo 'is already in the system. (The latest version pushed is ',
                    $this->c['yellow'], $result['latest'], $this->c[''], ".\n\n";

                echo 'A forced push is not likely to succeed.', "\n";
                
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
     * @return bool
     */
    protected function pharSignatureCheck(
        string $path,
        array $manifest = []
    ): bool {
        $supplier_name = $manifest['supplier'];
        $pharName = $supplier_name.'.'.$manifest['name'].'.phar';
        $signature = \file_get_contents($path.'/dist/'.$pharName.'.ed25519.sig');

        $supplier =& $this->config['suppliers'][$supplier_name];
        $numKeys = \count($supplier['signing_keys']);

        $verified = false;
        for ($i = 0; $i < $numKeys; ++$i) {
            // signing key
            $publicKey = new SignaturePublicKey(
                \Sodium\hex2bin($supplier['signing_keys'][$i]['public_key']),
                true
            );
            if (File::verify($path.'/dist/'.$pharName, $publicKey, $signature)) {
                $verified = true;
            }
        }
        return $verified;
    }
    /**
     * Check that the signature is valid for a given Phar
     *
     * @param string $path
     * @param array $manifest
     * @return bool
     */
    protected function zipSignatureCheck(
        string $path,
        array $manifest = []
    ): bool {
        $supplier_name = $manifest['supplier'];
        $zipName = $supplier_name.'.'.$manifest['name'].'.zip';
        $signature = \file_get_contents($path.'/dist/'.$zipName.'.ed25519.sig');

        $supplier =& $this->config['suppliers'][$supplier_name];
        $numKeys = \count($supplier['signing_keys']);

        $verified = false;
        for ($i = 0; $i < $numKeys; ++$i) {
            // signing key
            $publicKey = new SignaturePublicKey(
                \Sodium\hex2bin($supplier['signing_keys'][$i]['public_key']),
                true
            );
            if (File::verify($path.'/dist/'.$zipName, $publicKey, $signature)) {
                $verified = true;
            }
        }
        return $verified;
    }
}
