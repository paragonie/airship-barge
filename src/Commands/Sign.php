<?php
namespace Airship\Barge\Commands;

use \Airship\Barge as Base;
use \ParagonIE\Halite\{
    File,
    KeyFactory,
    SignatureKeyPair,
    Asymmetric\SignaturePublicKey,
    Asymmetric\SignatureSecretKey
};

class Sign extends Base\Command
{
    public $essential = true;
    public $name = 'Sign';
    public $description = 'Digitally sign the current Gadget.';
    public $display = 3;
    
    /**
     * Execute the build command
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
            $this->signGadget(
                $manifest,
                $path,
                \array_slice($args, 1)
            );
        }
    }
    
    /**
     * Sign a gadget
     * 
     * @param array $manifest
     * @param string $path
     * @param array $args
     */
    protected function signGadget(array $manifest, string $path, array $args = [])
    {
        $HTAB = str_repeat(' ', ceil(self::TAB_SIZE / 2));
        
        $pharname = $manifest['supplier'].'.'.$manifest['name'].'.phar';
        $supplier_name = $manifest['supplier'];
        
        $supplier =& $this->config['suppliers'][$supplier_name];
        $numKeys = \count($supplier['signing_keys']);
        if ($numKeys > 1) {
            echo 'You have more than one signing key available.', "\n";

            $n = 1;
            $size = (int) \floor(\log($numKeys, 10));
            $key_associations = $HTAB."ID\t Public Key\n";
            foreach ($supplier['signing_keys'] as $sign_key) {
                $_n = \str_pad($n, $size, ' ', STR_PAD_LEFT);
                $key_associations .= $HTAB.$_n.$HTAB.$sign_key['public_key']."\n";
                ++$n;
            }
            // Let's ascertain the user's key selection
            do {
                echo $key_associations;
                $choice = (int) $this->prompt('Enter the ID for the key you wish to use: ');
                if ($choice < 1 || $choice > $numKeys) {
                    $choice = null;
                }
            } while(empty($choice));
            $skey = $supplier['signing_keys'][$choice - 1];
        } else {
            $skey = $supplier['signing_keys'][0];
        }
        
        if (empty($skey['salt'])) {
            echo 'Salt not found for this key.', "\n";
            exit(255);
        }
        
        $password = $this->silentPrompt('Enter Password for Signing Key:');
        
        $salt = \Sodium\hex2bin($skey['salt']);
        $keypair = KeyFactory::deriveSignatureKeyPair($password, $salt);
            $sign_secret = $keypair->getSecretKey();
            $sign_public = $keypair->getPublicKey();
        
        $pubkey = \Sodium\bin2hex($sign_public->get());
        if ($skey['public_key'] !== $pubkey) {
            echo 'Invalid password', "\n";
            exit(255);
        }
        $signature = File::signFile($path.'/dist/'.$pharname, $sign_secret);
        
        $res = \file_put_contents(
            $path.'/dist/'.$pharname.'.ed25519.sig',
            $signature
        );
        if ($res !== false) {
            echo 'Signed: ', $path, '/dist/', $pharname, '.ed25519.sig', "\n";
            exit(0);
        }
    }
}
