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
        /*
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
         */
        $basepath = \count($args) > 0
            ? $args[0]
            : \getcwd();
        $supplier = 'test';
        
        echo 'What is the name of your project?', "\n";
        do {
            $project_name = $this->prompt('Enter a project name: ');
            if (preg_match('#^[\x00-\x20\*\."/\\\\\[\]:;\|=\.<>\$\x7f]+$#', $project_name)) {
                echo 'Project names cannot contain any of the following characters:', "\n\t",
                    '^ * " / \\ [ ] : ; | . < > $', "\n\n";
                $project_name = null;
            }
        } while (empty($project_name));
        
        $description = $this->prompt('Project description: ');
        if (empty($description)) {
            $description = 'Not provided';
        }
        
        // Create the basic structure
        if (!\is_dir($basepath)) {
            \mkdir($basepath, 0755);
        }
        \mkdir($basepath.'/'.$project_name, 0755);
        \mkdir($basepath.'/'.$project_name.'/dist/', 0755);
        \mkdir($basepath.'/'.$project_name.'/src/', 0755);
        
        \mkdir($basepath.'/'.$project_name.'/src/Blueprint', 0755);
        \mkdir($basepath.'/'.$project_name.'/src/Landing', 0755);
        \mkdir($basepath.'/'.$project_name.'/src/Lens', 0755);
        \mkdir($basepath.'/'.$project_name.'/src/Updates', 0755);
        
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
                    "    " . '"phar://' . $supplier . '.' . $project_name . '.phar/src/'. '"' . "\n".
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
        
        // Example code for an automatic update trigger.
        \file_put_contents(
            $basepath.'/'.$project_name.'/src/Updates/release-0-0-1.php',
            '<?php'."\n".
                '$db = \Airship\get_database();'."\n".
                '$db->insert("my_table", ['. "\n".
                '    "column" => "value"'."\n".
                '];'."\n\n"
        );
        
        // Auto-update trigger
        \file_put_contents(
            $basepath.'/'.$project_name.'/update_trigger.php',
            '<?php' . "\n" .
                '$metadata = \Airship\loadJSON(__DIR__."gadget.json");'."\n".
                'if (\\Airship\\expand_version($previous_metadata[\'version\']) <= \\Airship\\expand_version(\'0.0.1\')) {'."\n".
                '    require_once __DIR__."/src/Updates/release-0-0-1.php'."\n".
                '}'."\n\n"
        );
    }
    
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
