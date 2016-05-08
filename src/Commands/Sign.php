<?php
namespace Airship\Barge\Commands;

use \Airship\Barge as Base;
use \ParagonIE\Halite\{
    File,
    KeyFactory,
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

        // Cabins:
        if (\is_readable($path.'/cabin.json')) {
            $manifest = \json_decode(
                \file_get_contents($path.'/cabin.json'),
                true
            );
            return $this->signCabin($manifest, $path);
        }

        // Gadgets:
        if (\is_readable($path.'/gadget.json')) {
            $manifest = \json_decode(
                \file_get_contents($path.'/gadget.json'),
                true
            );
            return $this->signGadget($manifest, $path);
        }

        // Motifs:
        if (\is_readable($path.'/motif.json')) {
            $manifest = \json_decode(
                \file_get_contents($path.'/motif.json'),
                true
            );
            return $this->signMotif($manifest, $path);
        }

        echo 'Could not find manifest file.', "\n";
        exit(255);
    }

    /**
     * Common signing process. User selects key, provides password.
     *
     * @param array $manifest
     * @return SignatureSecretKey
     * @throws \Exception
     */
    protected function signPreamble(array $manifest): SignatureSecretKey
    {
        $HTAB = \str_repeat(' ', \intdiv(self::TAB_SIZE, 2));

        $supplier_name = $manifest['supplier'];

        if (!\array_key_exists('suppliers', $this->config)) {
            echo 'You are not authenticated for any suppliers.', "\n";
            exit(255);
        }
        if (!\array_key_exists($supplier_name, $this->config['suppliers'])) {
            echo 'Check the supplier in gadget.json (', $supplier_name,
            '). Otherwise, you might need to log in.', "\n";
            exit(255);
        }

        $supplier = $this->config['suppliers'][$supplier_name];
        $numKeys = \count($supplier['signing_keys']);
        if ($numKeys > 1) {
            echo 'You have more than one signing key available.', "\n";

            $n = 1;
            $size = (int) \floor(
                \log($numKeys, 10)
            );
            $key_associations = $HTAB."ID\t Public Key\n";
            foreach ($supplier['signing_keys'] as $sign_key) {
                $_n = \str_pad($n, $size, ' ', STR_PAD_LEFT);
                $key_associations .= $HTAB . $_n . $HTAB . $sign_key['public_key'] . "\n";
                ++$n;
            }
            // Let's ascertain the user's key selection
            do {
                echo $key_associations;
                $choice = (int) $this->prompt('Enter the ID for the key you wish to use: ');
                if ($choice < 1 || $choice > $numKeys) {
                    $choice = null;
                }
            } while (empty($choice));
            $supplierKey = $supplier['signing_keys'][$choice - 1];
        } else {
            $supplierKey = $supplier['signing_keys'][0];
        }

        if (empty($supplierKey['salt'])) {
            echo 'Salt not found for this key.', "\n";
            exit(255);
        }
        $password = $this->silentPrompt('Enter Password for Signing Key:');

        // Derive and split the SignatureKeyPair from your password and salt
        $salt = \Sodium\hex2bin($supplierKey['salt']);
        switch ($supplierKey['type']) {
            case 'signing':
                $type = KeyFactory::MODERATE;
                break;
            case 'master':
                $type = KeyFactory::SENSITIVE;
                break;
            default:
                $type = KeyFactory::INTERACTIVE;
        }
        $keyPair = KeyFactory::deriveSignatureKeyPair(
            $password,
            $salt,
            false,
            $type
        );
            $sign_secret = $keyPair->getSecretKey();
            $sign_public = $keyPair->getPublicKey();

        \Sodium\memzero($password);

        // Check that the public key we derived from the password matches the one on file
        $pubKey = \Sodium\bin2hex($sign_public->getRawKeyMaterial());
        if (!\hash_equals($supplierKey['public_key'], $pubKey)) {
            // Zero the memory ASAP
            unset($sign_public);
            echo 'Invalid password for selected key', "\n";
            exit(255);
        }
        // Zero the memory ASAP
        unset($sign_public);

        return $sign_secret;
    }

    /**
     * Sign a cabin
     *
     * @param array $manifest
     * @param string $path
     */
    protected function signCabin(array $manifest, string $path)
    {
        $pharName = $manifest['supplier'].'.'.$manifest['name'].'.phar';
        $sign_secret = $this->signPreamble($manifest);

        // This is the actual signing part.
        $signature = File::sign(
            $path.'/dist/'.$pharName,
            $sign_secret
        );
        // We no longer need this, so unset it. Halite will zero the buffer for us.
        unset($sign_secret);

        $res = \file_put_contents(
            $path.'/dist/'.$pharName.'.ed25519.sig',
            $signature
        );
        if ($res !== false) {
            echo 'Signed: ', $path, '/dist/', $pharName, '.ed25519.sig', "\n";
            exit(0);
        }
    }
    
    /**
     * Sign a gadget
     * 
     * @param array $manifest
     * @param string $path
     */
    protected function signGadget(array $manifest, string $path)
    {
        $pharName = $manifest['supplier'].'.'.$manifest['name'].'.phar';
        $sign_secret = $this->signPreamble($manifest);

        // This is the actual signing part.
        $signature = File::sign(
            $path.'/dist/'.$pharName,
            $sign_secret
        );
        // We no longer need this, so unset it. Halite will zero the buffer for us.
        unset($sign_secret);
        
        $res = \file_put_contents(
            $path.'/dist/'.$pharName.'.ed25519.sig',
            $signature
        );
        if ($res !== false) {
            echo 'Signed: ', $path, '/dist/', $pharName, '.ed25519.sig', "\n";
            exit(0);
        }
    }

    /**
     * Sign a motif
     *
     * @param array $manifest
     * @param string $path
     */
    protected function signMotif(array $manifest, string $path)
    {
        $zipName = $manifest['supplier'].'.'.$manifest['name'].'.zip';
        $sign_secret = $this->signPreamble($manifest);

        // This is the actual signing part.
        $signature = File::sign(
            $path.'/dist/'.$zipName,
            $sign_secret
        );
        // We no longer need this, so unset it. Halite will zero the buffer for us.
        unset($sign_secret);

        $res = \file_put_contents(
            $path.'/dist/'.$zipName.'.ed25519.sig',
            $signature
        );
        if ($res !== false) {
            echo 'Signed: ', $path, '/dist/', $zipName, '.ed25519.sig', "\n";
            exit(0);
        }
    }
}
