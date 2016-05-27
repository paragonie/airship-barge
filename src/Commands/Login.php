<?php
declare(strict_types=1);
namespace Airship\Barge\Commands;

use Airship\Barge as Base;

/**
 * Class Login
 *
 * Log in, get stored public keys and salts.
 *
 * @package Airship\Barge\Commands
 */
class Login extends Base\Command
{
    public $essential = false;
    public $name = 'Login';
    public $description = 'Authenticate to the Skyport.';
    public $display = 2;
    
    /**
     * Execute the login command
     *
     * @param array $args - CLI arguments
     * @echo
     * @return null
     */
    public function fire(array $args = [])
    {
        $username = \count($args) > 0
            ? $args[0]
            : $this->prompt("Please enter your username: ");
        
        $password = $this->silentPrompt("Password:");
        
        list ($skyport, $publicKey) = $this->getSkyport();

        // This should be sent over HTTPS only.
        $result = Base\HTTP::postSignedJSON(
            $skyport.'login',
            $publicKey,
            [
                'name' => $username,
                'password' => $password
            ]
        );

        // Wipe from memory as soon as we're done using it.
        \Sodium\memzero($password);

        if (isset($result['error'])) {
            echo $result['error'], "\n";
            exit(255);
        }

        // Let's store the result in our local config
        if (!\array_key_exists('suppliers', $this->config)) {
            $this->config['suppliers'] = [];
        }
        if (\array_key_exists($username, $this->config['suppliers'])) {
            $this->config['suppliers'][$username]['token'] = $result['token'];
            foreach ($result['signing_keys'] as $res_key) {
                $found = false;
                foreach ($this->config['suppliers'][$username]['signing_keys'] as $key) {
                    if ($key['public_key'] === $res_key['public_key']) {
                        $found = true;
                        break;
                    }
                }
                // If we loaded our salt into the skyport, import it:
                if (!$found && isset($res_key['salt'])) {
                    $this->config['suppliers'][$username]['signing_keys'][] = $res_key;
                }
            }
            echo 'Authentication successful', "\n";
        } else {
            $this->config['suppliers'][$username] = $result;
        }
    }
}
