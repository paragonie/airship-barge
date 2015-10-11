<?php
namespace Airship\Barge\Commands;

use Airship\Barge as Base;

class Login extends Base\Command
{
    public $essential = false;
    public $name = 'login';
    public $description = 'Authenticate to the Airship ATC service.';
    public $display = 2;
    
    /**
     * Execute the help command
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
        
        $skyport = $this->getSkyport();
        
        $result = \json_decode(
            Base\HTTP::post(
                $skyport.'login',
                [
                    'name' => $username,
                    'password' => $password
                ]
            ),
            true
        );
        if (isset($result['error'])) {
            echo $result['error'], "\n";
            exit(255);
        }
        $this->config['vendors'][$username] = $result;
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
        
    }
}