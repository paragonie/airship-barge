<?php
declare(strict_types=1);
namespace Airship\Barge\Commands;

/**
 * Class Gadget
 *
 * Create a new gadget
 *
 * @package Airship\Barge\Commands
 */
class Gadget extends Proto\Init
{
    public $essential = true;
    public $name = 'Gadget';
    public $description = 'Create a new Airship Gadget project.';
    public $display = 2;

    /**
     * @param string $supplier
     * @param string $project_name
     * @param string $basePath
     * @param string $description
     * @param array  $extra
     * @return bool
     */
    protected function createSkeleton(
        string $supplier,
        string $project_name,
        string $basePath,
        string $description,
        array  $extra = []
    ): bool {
        // Create the basic structure
        if (!\is_dir($basePath)) {
            \mkdir($basePath, 0755);
        }
        \mkdir($basePath.'/'.$project_name, 0755);
        \mkdir($basePath.'/'.$project_name.'/dist/', 0755);
        \mkdir($basePath.'/'.$project_name.'/src/', 0755);

        \mkdir($basePath.'/'.$project_name.'/src/Blueprint', 0755);
        \mkdir($basePath.'/'.$project_name.'/src/Landing', 0755);
        \mkdir($basePath.'/'.$project_name.'/src/Lens', 0755);
        \mkdir($basePath.'/'.$project_name.'/src/Updates', 0755);

        // Basic gadget.json
        \file_put_contents(
            $basePath.'/'.$project_name.'/gadget.json',
            \json_encode(
                [
                    'name' =>
                        $project_name,
                    'cabin' =>
                        !empty($extra['cabin'])
                            ? $extra['cabin']
                            : null,
                    'version' =>
                        '0.0.1',
                    'description' =>
                        $description,
                    'blueprints' =>
                        [],
                    'landings' =>
                        [],
                    'routes' =>
                        [],
                    'supplier' =>
                        $supplier
                ],
                JSON_PRETTY_PRINT
            )
        );

        // Basic composer.json
        \file_put_contents(
            $basePath.'/'.$project_name.'/composer.json',
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
            $basePath.'/'.$project_name.'/autoload.php',
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
            $basePath.'/'.$project_name.'/src/Blueprint/Example.php',
            '<?php'."\n".
            'use \\Airship\\Engine\\Gears;'."\n".
            'namespace '.$ns.';'."\n\n".
            'if (!\\class_exists(\'BlueprintGear\')) {'."\n".
            '    Gears::extract(\'Blueprint\', \'BlueprintGear\', __NAMESPACE__);'."\n".
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
            $basePath.'/'.$project_name.'/src/Landing/Example.php',
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
            $basePath.'/'.$project_name.'/src/Lens/example.twig',
            '{{ test }}'
        );

        // Example code for an automatic update trigger.
        \file_put_contents(
            $basePath.'/'.$project_name.'/src/Updates/release-0-0-1.php',
            '<?php'."\n".
            '$db = \Airship\get_database();'."\n".
            '$db->insert("my_table", ['. "\n".
            '    "column" => "value"'."\n".
            '];'."\n\n"
        );

        // Auto-update trigger
        \file_put_contents(
            $basePath.'/'.$project_name.'/src/update_trigger.php',
            '<?php' . "\n" .
            '$metadata = \Airship\loadJSON(\dirname(__DIR__) . "/gadget.json");'."\n".
            'if (\\Airship\\expand_version($previous_metadata[\'version\']) <= \\Airship\\expand_version(\'0.0.1\')) {'."\n".
            '    require_once __DIR__."/Updates/release-0-0-1.php'."\n".
            '}'."\n\n"
        );
        return true;
    }

    /**
     * Prompt the user for information specific to this project.
     *
     * @return array
     */
    protected function getExtraData(): array
    {
        echo 'Is this for a specific cabin?', "\n";
        echo 'If yes, enter a fully qualified cabin name below. (Leave blank for a universal gadget.)', "\n";
        echo 'For example: ', $this->c['yellow'], 'paragonie/bridge', $this->c[''], "\n";

        $cabin_split = [];
        do {
            $cabin = $this->prompt('Cabin Name: ');
            if (empty($cabin)) {
                return [];
            } elseif (\preg_match('#^([A-Za-z0-9_\-]+)/([A-Za-z0-9_\-]+)$#', $cabin, $m)) {
                $cabin_split = [
                    'supplier' => $m[1],
                    'cabin' => $m[2]
                ];
            } else {
                echo 'Invalid characters and/or invalid format! Use supplier/cabin_name.', "\n";
                $cabin = '';
            }
        } while (empty($cabin));

        return [
            'cabin' =>
                $cabin,
            'cabin_split' =>
                $cabin_split
        ];
    }
}