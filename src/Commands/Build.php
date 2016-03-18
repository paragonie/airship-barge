<?php
declare(strict_types=1);
namespace Airship\Barge\Commands;

use \Airship\Barge as Base;

class Build extends Base\Command
{
    public $essential = true;
    public $name = 'Build';
    public $description = 'Build the Gear or Gadget in the current directory.';
    public $display = 2;
    
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
     * Build a Gadget
     * 
     * @param string $path
     * @param array $manifest
     * @param array $args
     */
    protected function buildGadget(
        string $path,
        array $manifest = [],
        array $args = []
    ) {
        // Step One -- Let's build our .phar file
        $pharname = $manifest['supplier'].'.'.$manifest['name'].'.phar';
        try {
            if (\file_exists($path.'/dist/'.$pharname)) {
                \unlink($path.'/dist/'.$pharname);
            }
            if (\file_exists($path.'/dist/'.$pharname.'.ed25519.sig')) {
                \unlink($path.'/dist/'.$pharname.'.ed25519.sig');
            }
            $phar = new \Phar(
                $path.'/dist/'.$pharname,
                \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::KEY_AS_FILENAME,
                $pharname
            );
        } catch (\UnexpectedValueException $e) {
            echo 'Could not open .phar', "\n";
            exit(255); // Return an error flag
        }
        $phar->buildFromDirectory($path);
        $phar->setStub(
            $phar->createDefaultStub('autoload.php', 'autoload.php')
        );
        $phar->setMetadata($manifest);
        echo 'Gadget built.', "\n",
            $path.'/dist/'.$pharname, "\n",
            'Don\'t forget to sign it!', "\n";
        exit(0); // Return a success flag
    }
}
