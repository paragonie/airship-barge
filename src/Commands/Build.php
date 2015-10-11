<?php
namespace Airship\Barge\Commands;

use Airship\Barge as Base;

class Build extends Base\Command
{
    public $essential = true;
    public $name = 'build';
    public $description = 'Build and Sign the Gear or Gadget in the current directory.';
    public $display = 1;
    
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
            $this->buildGadget(
                $path,
                $manifest,
                \array_slice($args, 1)
            );
        }
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
    
    /**
     * Build a Gadget
     * 
     * @param string $path
     * @param array $manifest
     * @param array $args
     */
    protected function buildGadget($path, $manifest, array $args = [])
    {
        // Step One -- Let's build our .phar file
        $pharname = $manifest['vendor'].'-'.$manifest['name'].'.phar';
        try {
            $phar = new \Phar(
                $path.'/dist/'.$pharname,
                \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::KEY_AS_FILENAME,
                $pharname
            );
        } catch (\UnexpectedValueException $e) {
            die('Could not open my.phar');
        }
        $phar->buildFromDirectory($path.'/src');
        $phar->setStub(
            $phar->createDefaultStub('src/autoload.php', 'src/autoload.php')
        );
        $phar->setMetadata($manifest);
        
        // Step Two -- Do we have the signing key?
        if (isset($this->config['vendors'][$manifest['vendor']])) {
            $vendor =& $this->config['vendors'][$manifest['vendor']];
            foreach ($vendor['signing_keys'] as $sign_key) {
                /** @todo 2015-10-10 - make key management, then come back */
            }
        }
    }
}
