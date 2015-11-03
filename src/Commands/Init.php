<?php
namespace Airship\Barge\Commands;

use Airship\Barge as Base;

class Init extends Base\Command
{
    public $essential = true;
    public $name = 'Initialize';
    public $description = 'Create a new Airship Gadget project.';
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
        do {
            $project_name = $this->prompt('Enter a project name: ');
            if (!preg_match('#^[A-Za-z0-9_-]+$#', $project_name) || strpos($project_name, '--') !== false) {
                echo 'Project names can only contain: ';
                
                echo ' * Uppercase letters (A-Z)', "\n";
                echo ' * Lowercase letters (a-z)', "\n";
                echo ' * Numeric digits (0-9)', "\n";
                echo ' * Underscores (_) and single dashes(-)', "\n";
                
                $project_name = null;
            }
        } while (empty($project_name));
        
        $description = $this->prompt('Project description: ');
        if (empty($description)) {
            $description = 'Not provided';
        }
        
        // Create the basic structure
        \mkdir($basepath, 0755);
        \mkdir($basepath.'/'.$project_name, 0755);
        \mkdir($basepath.'/'.$project_name.'/dist/', 0755);
        \mkdir($basepath.'/'.$project_name.'/src/', 0755);
        
        \mkdir($basepath.'/'.$project_name.'/src/Blueprint', 0755);
        \mkdir($basepath.'/'.$project_name.'/src/Landing', 0755);
        \mkdir($basepath.'/'.$project_name.'/src/Lens', 0755);
        
        // Basic gadget.json
        \file_put_contents(
            $basepath.'/'.$project_name.'/gadget.json',
            \json_encode(
                [
                    'name' => $project_name,
                    'version' => '0.0.1',
                    'description' => $description,
                    'blueprints' => [],
                    'landings' => [],
                    'routes' => [],
                    'supplier' => $supplier
                ],
                JSON_PRETTY_PRINT
            )
        );
        
        // Basic composer.json
        \file_put_contents(
            $basepath.'/'.$project_name.'/composer.json',
            \json_encode(
                [
                    'name' => $supplier.'/'.$project_name,
                    'description' => $description,
                    'require' => [
                        'php' => '^7.0.0'
                    ]
                ],
                JSON_PRETTY_PRINT
            )
        );
        
        // Basic autoloader
        \file_put_contents(
            $basepath.'/'.$project_name.'/autoload.php',
            '<?php' . "\n" .
                '\\Airship\\autoload(' . "\n".
                    "    " . '"\\\\'. $this->upperFirst($supplier) . '\\\\' . $this->upperFirst($project_name).'",' . "\n" .
                    "    " . '"phar://' . $supplier . '--' . $project_name . '.phar/src/'. '"' . "\n".
                ');' . "\n\n" .
                '\\Airship\\Engine\\Gadgets::loadCargo(' . "\n" .
                    "    " . '"example",' . " // Cargo placeholder\n" .
                    "    " . '"@' . $project_name . '"/example.twig' . "// Cargo path (relative to Lens)\n" .
                ');' . "\n\n"
        );
        $ns = $this->upperFirst($supplier) . '\\' . $this->upperFirst($project_name);
        
        // Some example scripts
        \file_put_contents(
            $basepath.'/'.$project_name.'/src/Blueprint/Example.php',
            '<?php'."\n".
                'namespace '.$ns.';'."\n\n".
                'if (!\\class_exists(\'BlueprintGear\')) {'."\n".
                '    \\Airship\\Engine\\Gears::extract(\'Blueprint\', \'BlueprintGear\', __NAMESPACE__);'."\n".
                '}'."\n\n".
                'class Example extends BlueprintGear'."\n".
                '{'."\n".
                '    public function getData()'."\n".
                '    {'."\n".
                '        return $this->db->run("SELECT * FROM table");'."\n".
                '    }'."\n".
                '}'."\n\n"
        );
        \file_put_contents(
            $basepath.'/'.$project_name.'/src/Landing/Example.php',
            '<?php'."\n".
                'namespace '.$ns.';'."\n\n".
                'if (!\\class_exists(\'LandingGear\')) {'."\n".
                '    \\Airship\\Engine\\Gears::extract(\'Landing\', \'LandingGear\', __NAMESPACE__);'."\n".
                '}'."\n\n".
                'class Example extends LandingGear'."\n".
                '{'."\n".
                '    public function index()'."\n".
                '    {'."\n".
                '        $this->view("example", ["test" => "Hello world!"]);'."\n".
                '    }'."\n".
                '}'."\n\n"
        );
        \file_put_contents(
            $basepath.'/'.$project_name.'/src/Lens/example.twig',
            '{{ test }}'
        );
    }
    
    /**
     * Domain-specfiic variant of PHP's native ucfirst()
     * 
     * @param string $string
     * @return string
     */
    protected function upperFirst($string)
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
