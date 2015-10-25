<?php
namespace Airship\Barge\Commands;

use \Airship\Barge as Base;
use \ParagonIE\Halite\File;
use \ParagonIE\Halite\Key;

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
    
    protected function signGadget($manifest, $path, array $args = [])
    {
        $TAB = str_repeat(' ', self::TAB_SIZE);
        $HTAB = str_repeat(' ', ceil(self::TAB_SIZE / 2));
        
        $pharname = $manifest['vendor'].'--'.$manifest['name'].'.phar';
        $vendor_name = $manifest['vendor'];
        
        $vendor =& $this->config['vendors'][$vendor_name];
        $numKeys = \count($vendor['signing_keys']);
        if ($numKeys > 1) {
            echo 'You have more than one signing key available.';

            $n = 1;
            $size = (int) \floor(\log($numKeys, 10));
            $key_associations = $HTAB."ID\tPublic Key\n";
            foreach ($vendor['signing_keys'] as $sign_key) {
                $_n = \str_pad($n, $size, ' ', STR_PAD_LEFT);
                $key_associations .= $HTAB.$_n.$HTAB.$sign_key['public_key']."\n";
                ++$n;
            }
            // Let's ascertain the user's key selection
            do {
                $choice = (int) $this->prompt('Enter the ID for the key you wish to use: ');
                if ($choice < 1 || $choice > $numKeys) {
                    $choice = null;
                }
            } while(empty($choice));
            $skey = $vendor['signing_keys'][$choice - 1];
        } else {
            $skey = $vendor['signing_keys'][0];
        }
        
        if (empty($skey['salt'])) {
            echo 'Salt not found for this key.', "\n";
            exit(255);
        }
        
        $password = $this->silentPrompt('Enter Password for Signing Key:');
        
        $salt = \Sodium\hex2bin($skey['salt']);
        list($sign_secret, $sign_public) = Key::deriveFromPassword(
            $password,
            $salt,
            Key::CRYPTO_SIGN
        );
        $pubkey = \Sodium\bin2hex($sign_public->get());
        if ($skey['public_key'] !== $pubkey) {
            echo 'Invalid password', "\n";
            exit(255);
        }
        $signature = File::signFile($path.'/dist/'.$pharname, $sign_secret);
        
        return \file_put_contents(
            $path.'/dist/'.$pharname.'.ed25519.sig',
            $signature
        );
    }
}
