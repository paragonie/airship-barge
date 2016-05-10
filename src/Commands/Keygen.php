<?php
declare(strict_types=1);
namespace Airship\Barge\Commands;

use \Airship\Barge as Base;
use \ZxcvbnPhp\Zxcvbn;
use \ParagonIE\Halite\KeyFactory;
use \ParagonIE\Halite\Asymmetric\Crypto as Asymmetric;

class Keygen extends Base\Command
{
    public $essential = false;
    public $name = 'Key Generator';
    public $description = 'Generate a new signing key.';
    public $display = 4;
    
    /**
     * Execute the keygen command
     *
     * @param array $args - CLI arguments
     * @echo
     * @return null
     */
    public function fire(array $args = [])
    {
        if (count($this->config['suppliers']) === 1) {
            $supplier = \count($args) > 0
                ? $args[0]
                : \array_keys($this->config['suppliers'])[0];
        } else {
            $supplier = \count($args) > 0
                ? $args[0]
                : $this->prompt("Please enter the name of the supplier: ");
        }
        
        if (!\array_key_exists($supplier, $this->config['suppliers'])) {
            echo 'Please authenticate before attempting to generate a key.', "\n";
            echo 'Run this command: ', $this->c['yellow'], 'barge login', $this->c[''], "\n";
            exit(255);
        }
        
        if (\count($this->config['suppliers'][$supplier]['signing_keys']) === 0) {
            $has_master = false;
            $key_type = 'master';
        } else {
            $has_master = true;
            echo 'Please enter the key type you would like to generate (master, signing).', "\n";
            do {
                $key_type = $this->prompt('Key type: ');
                switch ($key_type) {
                    case 'm':
                    case 'main':
                    case 'master':
                    case 'primary':
                        $key_type = 'master';
                        break;
                    case 's':
                    case 'secondary':
                    case 'sub':
                    case 'subkey':
                    case 'signing':
                        $key_type = 'signing';
                        break;
                    default:
                        echo 'Acceptable key types: master, signing', "\n";
                        $key_type = null;
                }
            } while (empty($key_type));
        }
        
        echo 'Generating a unique salt...', "\n";
        $salt = \random_bytes(\Sodium\CRYPTO_PWHASH_SALTBYTES);
        
        $store_in_cloud = null;
        
        echo 'Do you wish to store the salt for generating your signing key in the Skyport?', "\n";
        echo 'This is a security-convenience trade-off. The default is NO.', "\n\n";
        echo $this->c['green'], 'Pro:', $this->c[''],
            ' It\'s there if you need it, and the salt alone is not enough for us to', "\n", 
            '     reproduce your signing key.', "\n";
        echo $this->c['red'], 'Con:', $this->c[''],
            ' If your salt is stored online, the security of your signing key depends', "\n", 
            '     entirely on your password.', "\n\n";

        // Iterate until we get a valid response
        while ($store_in_cloud === null) {
            $choice = $this->prompt('Store salt in the Skyport? (y/N): ');
            switch ($choice) {
                case 'YES':
                case 'yes':
                case 'Y':
                case 'y':
                    $store_in_cloud = true;
                    break;
                case 'N':
                case 'NO':
                case 'n':
                case 'no':
                case '': // Just pressing enter means "don't store it"!
                    $store_in_cloud = false;
                    break;
                default:
                    echo "\n", $this->c['yellow'], 'Invalid response. Please enter yes or no.', $this->c[''], "\n";
            }
        }
        
        $zxcvbn = new Zxcvbn();
        $userInput = $this->getZxcvbnKeywords($supplier);
        
        // If we're storing in the cloud, our standards should be much higher.
        $min_score = $store_in_cloud
            ? 3
            : 2;
        
        do {
            // Next, let's get a password.
            echo 'Please enter a strong password to use for your signing key.', "\n";
            $password = $this->silentPrompt("Password:");
            
            // Use zxcvbn to assess password strength
            $strength = $zxcvbn->passwordStrength($password, $userInput);
            if ($strength['score'] < $min_score) {
                echo $this->c['yellow'], 
                    'Sorry, that password is not strong enough. Try making ',
                    'your password longer and use a wider variety of characters.', 
                    $this->c[''],
                    "\n";
                $password = false;
            }
        } while (empty($password));
        
        echo 'Generating signing key...';

        if ($key_type === 'master') {
            $sign_level = KeyFactory::SENSITIVE;
        } else {
            $sign_level = KeyFactory::MODERATE;
        }
        $keyPair = KeyFactory::deriveSignatureKeyPair(
            $password,
            $salt,
            false,
            $sign_level
        );
        $sign_public = $keyPair->getPublicKey();
        echo 'DONE!', "\n";
        
        // Wipe the password from memory
        \Sodium\memzero($password);
        
        // Store this in the configuration
        $new_key = [
            'date_generated' => \date('Y-m-d\TH:i:s'),
            'store_in_cloud' => $store_in_cloud,
            'salt' => \Sodium\bin2hex($salt),
                // See sendToSkyport(); the salt isn't sent unless you explicitly
                // opt for it to be sent.
            'public_key' => \Sodium\bin2hex($sign_public->getRawKeyMaterial()),
            'type' => $key_type
        ];

        // This is the message we are signing.
        $message = \json_encode(
            [
                'action' =>
                    'CREATE',
                'date_generated' =>
                    $new_key['date_generated'],
                'public_key' =>
                    $new_key['public_key'],
                'supplier' =>
                    $supplier,
                'type' =>
                    $new_key['type']
            ]
        );

        if ($has_master) {
            list($masterSig, $masterPubKey) = $this->signNewKeyWithMasterKey($supplier, $message);
        } else {
            // This is our first key, so we don't need it.
            $masterSig = '';
            $masterPubKey = '';
        }
        
        // Save the configuration
        $this->config['suppliers'][$supplier]['signing_keys'][] = $new_key;
        
        // Send the public kay (and, maybe, the salt) to the Skyport.
        $this->sendToSkyport($supplier, $new_key, $message, $masterSig, $masterPubKey);
    }

    /**
     * Sign the new key with our current master key
     *
     * @param string $supplier
     * @param string $messageToSign
     * @return string[]
     * @throws \Exception
     */
    protected function signNewKeyWithMasterKey(string $supplier, string $messageToSign): array
    {
        $master_keys = [];
        foreach ($this->config['suppliers'][$supplier]['signing_keys'] as $key) {
            if ($key['type'] === 'master' && !empty($key['salt'])) {
                $master_keys [] = $key;
            }
        }

        // This shouldn't happen, but just in case:
        if (empty($master_keys)) {
            throw new \Exception(
                'You cannot generate another key unless you already have a master key with the salt loaded locally.'
            );
        }

        // Select the correct master key.
        if (\count($master_keys) === 1) {
            $signingKey = $master_keys[0];
        } else {
            echo 'Select which master key to use:';
            do {
                foreach ($master_keys as $index => $key) {
                    echo ($index + 1), "\t", $key['public_key'], "\n";
                }
                $keyIndex = $this->prompt('Enter a number: ');
                if (empty($keyIndex)) {
                    // Okay, let's cancel.
                    throw new \Exception('Aborted.');
                }
                if ($keyIndex < 1 || $keyIndex > \count($master_keys)) {
                    $keyIndex = 0;
                    echo 'Please enter a number between 1 and ', \count($master_keys), ".\n";
                }
            } while ($keyIndex < 1);
            $signingKey = $master_keys[--$keyIndex];
        }

        $signature = '';
        $masterSalt = \Sodium\hex2bin($signingKey['salt']);
        do {
            $password = $this->silentPrompt('Enter the password for your master key: ');
            if (empty($password)) {
                // Okay, let's cancel.
                throw new \Exception('Aborted.');
            }
            $masterKeyPair = KeyFactory::deriveSignatureKeyPair(
                $password,
                $masterSalt
            );

            // We must verify the public key matches:
            $masterPublicKey = $masterKeyPair->getPublicKey();
            if (\hash_equals(
                $masterPublicKey->getRawKeyMaterial(),
                \Sodium\hex2bin($signingKey['public_key'])
            )) {
                $masterSecretKey = $masterKeyPair->getSecretKey();

                // Setting $signature exits the loop
                $signature = Asymmetric::sign(
                    $messageToSign,
                    $masterSecretKey
                );
            }
        } while (!$signature);

        // We are returning two strings:
        return [
            $signature,
            $signingKey['public_key']
        ];
    }
    
    /**
     * Send information about the new key to our Skyport
     * 
     * @param string $supplier
     * @param array $data
     * @param string $message
     * @param string $masterSignature
     * @param string $masterPublicKey
     * @return array
     */
    protected function sendToSkyport(
        string $supplier,
        array $data = [],
        string $message,
        string $masterSignature,
        string $masterPublicKey
    ): array {
        list ($skyport, $publicKey) = $this->getSkyport();
        
        $postData = [
            'token' => $this->getToken($supplier),
            'date_generated' => $data['date_generated'],
            'message' => $message,
            'publickey' => $data['public_key'],
            'type' => $data['type']
        ];

        // The user must opt in for this to be invoked:
        if ($data['store_in_cloud']) {
            $postData['stored_salt'] = $data['salt'];
        }

        // If this isn't our first key, we should be signing it with our master key.
        if ($masterPublicKey && $masterSignature) {
            // The skyport MUST make validate the master public key before checking
            // the signature.
            $postData['master'] = [
                // Only used for "which key?", don't trust this input
                'public_key' => $masterPublicKey,
                // Should validate date_generated and publickey
                'signature' => $masterSignature
            ];
        }
        
        return Base\HTTP::postSignedJSON(
            $skyport . 'key/add',
            $publicKey,
            $postData
        );
    }
    
    /**
     * Get a list of keywords (including supplier name) for Zxcvbn. This includes
     * the supplier name and some keywords relevant to Airship to demote obvious
     * password choices.
     * 
     * @param string $supplier_name
     * @return array
     */
    protected function getZxcvbnKeywords(
        string $supplier_name
    ): array {
        return [
            $supplier_name,
            'airship',
            'barge',
            'flotilla',
            'php 7',
            'libsodium',
            'sodium',
            'NaCl',
            'crypto',
            'cryptography',
            'Halite',
            'scrypt',
            'argon2',
            'argon2i',
            'kdf',
            'paragon',
            'Paragon Initiative Enterprises'
        ];
    }
}
