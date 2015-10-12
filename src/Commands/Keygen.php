<?php
namespace Airship\Barge\Commands;

use \Airship\Barge as Base;
use \ZxcvbnPhp\Zxcvbn;
use \ParagonIE\Halite\Key;

class Keygen extends Base\Command
{
    public $essential = false;
    public $name = 'Key Generator';
    public $description = 'Generate a new signing key.';
    public $display = 3;
    
    /**
     * Execute the keygen command
     *
     * @param array $args - CLI arguments
     * @echo
     * @return null
     */
    public function fire(array $args = [])
    {
        if (count($this->config['vendors']) === 1) {
            $vendor = \count($args) > 0
                ? $args[0]
                : \array_keys($this->config['vendors'])[0];
        } else {
            $vendor = \count($args) > 0
                ? $args[0]
                : $this->prompt("Please enter the name of the vendor: ");
        }
        
        if (!\array_key_exists($vendor, $this->config['vendors'])) {
            echo 'Please authenticate before attempting to generate a key.', "\n";
            echo 'Run this command: ', $this->c['yellow'], 'barge login', $this->c[''], "\n";
            exit(255);
        }
        
        if (\count($this->config['vendors'][$vendor]['signing_keys']) === 0) {
            $key_type = 'master';
        } else {
            echo 'Please enter the key type you would like to generate (master, sub).', "\n";
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
                    case 'sub':
                    case 'subkey':
                    case 'secondary':
                        $key_type = 'sub';
                        break;
                    default:
                        echo 'Acceptable key types: master, sub', "\n";
                        $key_type = null;
                }
            } while (empty($key_type));
        }
        
        echo 'Generating a unique salt...', "\n";
        $salt = \Sodium\randombytes_buf(
            \Sodium\CRYPTO_PWHASH_SCRYPTSALSA208SHA256_SALTBYTES
        );
        
        $store_in_cloud = null;
        
        echo 'Do you wish to store the salt for generating your signing key in the Skyport?', "\n";
        echo 'This is a security-convenience trade-off. The default is NO.', "\n\n";
        echo $this->c['green'], 'Pro:', $this->c[''],
            ' It\'s there if you need it, and the salt alone is not enough for us to', "\n", 
            '     reproduce your signing key.', "\n";
        echo $this->c['red'], 'Con:', $this->c[''],
            ' If your salt is stored online, the security of your signing key depends', "\n", 
            '     entirely on your password.', "\n\n";
        
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
        $userInput = $this->getZxcvbnKeywords($vendor);
        
        // If we're storing in the cloud, our standards should be much higher.
        $min_score = $store_in_cloud ? 3 : 2;
        
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
        
        list(, $sign_public) = Key::deriveFromPassword(
            $password,
            $salt,
            Key::CRYPTO_SIGN
        );
        echo 'DONE!', "\n";
        
        // Wipe the password from memory
        \Sodium\memzero($password);
        
        // Store this in the configuration
        $new_key = [
            'store_in_cloud' => $store_in_cloud,
            'salt' => \Sodium\bin2hex($salt),
            'public_key' => \Sodium\bin2hex($sign_public->get()),
            'type' => $key_type
        ];
        
        // Save the configuration
        $this->config['vendors'][$vendor]['signing_keys'][] = $new_key;
        
        // Send the public kay (and, maybe, the salt) to the Skyport.
        $this->sendToSkyport($vendor, $new_key);
    }
    
    /**
     * Send information about the new key to our Skyport
     * 
     * @param string $vendor
     * @param array $data
     */
    protected function sendToSkyport($vendor, array $data = [])
    {
        $skyport = $this->getSkyport();
        
        $postData = [
            'token' => $this->getToken($vendor),
            'publickey' => $data['public_key'],
            'type' => $data['type']
        ];
        if ($data['store_in_cloud']) {
            $postData['stored_salt'] = $data['salt'];
        }
        
        $response = Base\HTTP::post(
            $skyport.'key/add',
            $postData
        );
        return \json_decode($response, true);
    }
    
    /**
     * Get a list of keywords (including vendor name) for Zxcvbn. This includes
     * the vendor name and some keywords relevant to Airship to demote obvious
     * password choices.
     * 
     * @param string $vendor_name
     * @return array
     */
    protected function getZxcvbnKeywords($vendor_name)
    {
        return [
            $vendor_name,
            'airship',
            'barge',
            'flotilla',
            'php 7',
            'libsodium',
            'NaCl',
            'crypto',
            'cryptography',
            'Halite',
            'scrypt',
            'argon2',
            'kdf',
            'paragon',
            'Paragon Initiative Enterprises'
        ];
    }
    
    /**
     * Display the usage information for this command.
     *
     * @param array $args - CLI arguments
     * @echo
     * @return null
     */
    public function usageInfo(array $args = [])
    {
        parent::usageInfo($args);
    }
}
