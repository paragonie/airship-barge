<?php
declare(strict_types=1);
namespace Airship\Barge\Commands\Proto;

use \Airship\Barge as Base;

/**
 * Class Init
 *
 * Base class for initializing a new project.
 *
 * @package Airship\Barge\Commands\Proto
 */
abstract class Init extends Base\Command
{
    /**
     * @var string
     */
    public $descriptionPrompt = 'Project description: ';

    /**
     * Execute the build command
     *
     * @param array $args - CLI arguments
     * @echo
     * @return null
     */
    public function fire(array $args = [])
    {
        if (!\array_key_exists('suppliers', $this->config)) {
            die("Please login first!\n");
        }
        $basePath = \count($args) > 0
            ? $args[0]
            : \getcwd();

        if (count($this->config['suppliers']) === 1) {
            $supplier = \count($args) > 1
                ? $args[1]
                : \array_keys($this->config['suppliers'])[0];
        } else {
            $supplier = \count($args) > 1
                ? $args[1]
                : $this->prompt("Please enter the name of the supplier: ");
        }

        echo 'What is the name of your project?', "\n";

        // Loop until the user gets it right or presses Ctrl+C
        do {
            $project_name = $this->prompt('Enter a project name: ');
            if (\preg_match(
                '#^[\x00-\x20\*\."/\\\\\[\]\{\}:;\|=\.<>\$\x7f]+$#',
                $project_name
            )) {
                echo 'Project names cannot contain any of the following characters:', "\n\t",
                    '^ * " / \\ [ ] { } : ; | . < > $', "\n\n";
                $project_name = null;
            }
        } while (empty($project_name));
        
        $description = $this->prompt($this->descriptionPrompt);
        if (empty($description)) {
            $description = 'Not provided';
        }

        // Each project type has its own implementation:
        $extra = $this->getExtraData();

        // We finish by creating a skeleton:
        return $this->createSkeleton(
            $supplier,
            $project_name,
            $basePath,
            $description,
            $extra
        );
    }

    /**
     * @param string $supplier
     * @param string $project_name
     * @param string $basePath
     * @param string $description
     * @param array  $extra
     * @return bool
     */
    abstract protected function createSkeleton(
        string $supplier,
        string $project_name,
        string $basePath,
        string $description,
        array  $extra = []
    ): bool;

    /**
     * Prompt the user for information specific to this project.
     *
     * @return array
     */
    abstract protected function getExtraData(): array;
    
    /**
     * Domain-specific variant of PHP's native ucfirst()
     * 
     * @param string $string
     * @return string
     */
    protected function upperFirst(string $string = '')
    {
        $string[0] = \strtoupper($string[0]);
        for ($i = 0; $i < \strlen($string); ++$i) {
            if ($string[$i] === '-' || $string[$i] === '_') {
                $string[$i] = '_';
                ++$i;
                if (\preg_match('#[a-z]#', $string[$i])) {
                    $string[$i] = \strtoupper($string[$i]);
                }
            }
        }
        return $string;
    }
}
