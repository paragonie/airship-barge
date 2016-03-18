<?php
declare(strict_types=1);
namespace Airship\Barge\Commands\Proto;

use \Airship\Barge as Base;

abstract class Init extends Base\Command
{
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
        $basepath = \count($args) > 0
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
                '#^[\x00-\x20\*\."/\\\\\[\]:;\|=\.<>\$\x7f]+$#',
                $project_name
            )) {
                echo 'Project names cannot contain any of the following characters:', "\n\t",
                    '^ * " / \\ [ ] : ; | . < > $', "\n\n";
                $project_name = null;
            }
        } while (empty($project_name));
        
        $description = $this->prompt('Project description: ');
        if (empty($description)) {
            $description = 'Not provided';
        }
    }

    /**
     * @param string $supplier
     * @param string $project_name
     * @param string $basepath
     * @param string $description
     * @return bool
     */
    abstract protected function createSkeleton(
        string $supplier,
        string $project_name,
        string $basepath,
        string $description
    ): bool;
    
    /**
     * Domain-specfiic variant of PHP's native ucfirst()
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
