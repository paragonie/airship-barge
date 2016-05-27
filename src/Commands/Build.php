<?php
declare(strict_types=1);
namespace Airship\Barge\Commands;

use \Airship\Barge as Base;

class Build extends Base\Command
{
    public $essential = true;
    public $name = 'Build';
    public $description = 'Build the Cabin, Gadget, or Motif in the current directory.';
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

        // Cabins
        if (\is_readable($path.'/cabin.json')) {
            $manifest = \json_decode(
                \file_get_contents($path.'/cabin.json'),
                true
            );
            $commit = $this->getGitCommitHash($path);
            $manifest['commit'] = !empty($commit)
                ? $commit
                : null;
            return $this->buildCabin($path, $manifest);
        }

        // Gadgets
        if (\is_readable($path.'/gadget.json')) {
            $manifest = \json_decode(
                \file_get_contents($path.'/gadget.json'),
                true
            );
            $commit = $this->getGitCommitHash($path);
            $manifest['commit'] = !empty($commit)
                ? $commit
                : null;
            return $this->buildGadget($path, $manifest);
        }

        // Motifs
        if (\is_readable($path.'/src/motif.json')) {
            $manifest = \json_decode(
                \file_get_contents($path.'/src/motif.json'),
                true
            );
            $commit = $this->getGitCommitHash($path);
            $manifest['commit'] = !empty($commit)
                ? $commit
                : null;
            return $this->buildMotif($path, $manifest);
        }
        echo 'Unknown project type!', "\n";
        exit(255);
    }

    /**
     * Build a Cabin
     *
     * @param string $path
     * @param array $manifest
     */
    protected function buildCabin(
        string $path,
        array $manifest = []
    ) {
        // Step One -- Let's build our .phar file
        $pharName = $manifest['supplier'].'.'.$manifest['name'].'.phar';
        try {
            \copy($path.'/cabin.json', $path.'/src/manifest.json');
            if (\file_exists($path.'/dist/'.$pharName)) {
                \unlink($path.'/dist/'.$pharName);
            }
            if (\file_exists($path.'/dist/'.$pharName.'.ed25519.sig')) {
                \unlink($path.'/dist/'.$pharName.'.ed25519.sig');
            }
            $phar = new \Phar(
                $path.'/dist/'.$pharName,
                \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::KEY_AS_FILENAME,
                $pharName
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
        echo 'Cabin built.', "\n",
            $path.'/dist/'.$pharName, "\n",
        'Don\'t forget to sign it!', "\n";
        exit(0); // Return a success flag
    }

    /**
     * Build a Gadget
     *
     * @param string $path
     * @param array $manifest
     */
    protected function buildGadget(
        string $path,
        array $manifest = []
    ) {
        // Step One -- Let's build our .phar file
        $pharName = $manifest['supplier'].'.'.$manifest['name'].'.phar';
        try {
            if (\file_exists($path.'/dist/'.$pharName)) {
                \unlink($path.'/dist/'.$pharName);
            }
            if (\file_exists($path.'/dist/'.$pharName.'.ed25519.sig')) {
                \unlink($path.'/dist/'.$pharName.'.ed25519.sig');
            }
            $phar = new \Phar(
                $path.'/dist/'.$pharName,
                \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::KEY_AS_FILENAME,
                $pharName
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
            $path.'/dist/'.$pharName, "\n",
        'Don\'t forget to sign it!', "\n";
        exit(0); // Return a success flag
    }

    /**
     * Build a Motif
     *
     * @param string $path
     * @param array $manifest
     */
    protected function buildMotif(
        string $path,
        array $manifest = []
    ) {
        // Step One -- Let's build our .zip file
        $zipName = $manifest['supplier'].'.'.$manifest['name'].'.zip';
        if (\file_exists($path.'/dist/'.$zipName)) {
            \unlink($path.'/dist/'.$zipName);
        }
        if (\file_exists($path.'/dist/'.$zipName.'.ed25519.sig')) {
            \unlink($path.'/dist/'.$zipName.'.ed25519.sig');
        }
        $zip = new \ZipArchive();
        $flags = \ZipArchive::CREATE | \ZipArchive::OVERWRITE;

        // Open the zip for writing
        if ($zip->open($path.'/dist/'.$zipName, $flags) !== true) {
            echo 'Could not open .zip', "\n";
            exit(255); // Return an error flag
        }
        $zipOpts = [
            'remove_all_path' => true
        ];
        $currentDir = \getcwd();
        \chdir($path . '/src/');
        $zip->addGlob('*.json', 0, $zipOpts);
        $zip->addGlob('*/*', 0, $zipOpts);
        \chdir($currentDir);
        $zip->setArchiveComment(\json_encode($manifest));
        if (!$zip->close()) {
            echo 'Zip archive unsuccessful', "\n";
            exit(255);
        }
        echo 'Motif built.', "\n",
            $path.'/dist/'.$zipName, "\n",
        'Don\'t forget to sign it!', "\n";
        exit(0); // Return a success flag
    }
}
