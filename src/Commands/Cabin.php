<?php
declare(strict_types=1);
namespace Airship\Barge\Commands;

/**
 * Class Cabin
 *
 * Create a new Cabin (website)
 *
 * @package Airship\Barge\Commands
 */
class Cabin extends Proto\Init
{
    public $essential = true;
    public $name = 'Cabin';
    public $description = 'Create a new Airship Cabin project.';
    public $display = 3;

    /**
     * Create a skeleton for a new Airship cabin.
     *
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
        \mkdir($basePath.'/'.$project_name.'/src/Exceptions', 0755);
        \mkdir($basePath.'/'.$project_name.'/src/Gadgets', 0755);
        \mkdir($basePath.'/'.$project_name.'/src/Landing', 0755);
        \mkdir($basePath.'/'.$project_name.'/src/Lens', 0755);
        \mkdir($basePath.'/'.$project_name.'/src/Queries', 0755);
        \mkdir($basePath.'/'.$project_name.'/src/public', 0755);
        \mkdir($basePath.'/'.$project_name.'/src/Updates', 0755);

        // Basic gadget.json
        \file_put_contents(
            $basePath.'/'.$project_name.'/cabin.json',
            \json_encode(
                [
                    'name' =>
                        $project_name,
                    'route_fallback' => null,
                    'description' =>
                        $description,
                    'routes' =>
                        [],
                    'supplier' =>
                        $supplier,
                    'version' =>
                        '0.0.1'
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
            '$metadata = \Airship\loadJSON(\dirname(__DIR__) . "/cabin.json");'."\n".
            'if (\\Airship\\expand_version($previous_metadata[\'version\']) <= \\Airship\\expand_version(\'0.0.1\')) {'."\n".
            '    require_once __DIR__."/Updates/release-0-0-1.php'."\n".
            '}'."\n\n"
        );
        return true;
    }

    /**
     * @return array
     */
    protected function getExtraData(): array
    {
        return [];
    }
}