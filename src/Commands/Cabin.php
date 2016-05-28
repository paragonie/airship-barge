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
        // For lazy autoloading...
        $mainDir = $this->upperFirst($supplier) . '__' .$this->upperFirst($project_name);
        $ns = \implode('\\', [
            'Airship',
            'Cabin',
            $mainDir
        ]);
        
        \mkdir($basePath.'/'.$mainDir, 0755);
        \mkdir($basePath.'/'.$mainDir.'/dist/', 0755);
        \mkdir($basePath.'/'.$mainDir.'/src/', 0755);

        \mkdir($basePath.'/'.$mainDir.'/src/Blueprint', 0755);
        \mkdir($basePath.'/'.$mainDir.'/src/config', 0755);
        \mkdir($basePath.'/'.$mainDir.'/src/config/editor_templates', 0755);
        \mkdir($basePath.'/'.$mainDir.'/src/config/templates', 0755);
        \mkdir($basePath.'/'.$mainDir.'/src/Exceptions', 0755);
        \mkdir($basePath.'/'.$mainDir.'/src/Gadgets', 0755);
        \mkdir($basePath.'/'.$mainDir.'/src/Landing', 0755);
        \mkdir($basePath.'/'.$mainDir.'/src/Lens', 0755);
        \mkdir($basePath.'/'.$mainDir.'/src/Queries', 0755);
        \mkdir($basePath.'/'.$mainDir.'/src/public', 0755);
        \mkdir($basePath.'/'.$mainDir.'/src/Updates', 0755);

        // Basic cabin.json
        \file_put_contents(
            $basePath.'/'.$mainDir.'/cabin.json',
            \json_encode(
                [
                    'name' =>
                        $project_name,
                    'namespace' =>
                        $ns,
                    'route_fallback' =>
                        null,
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
            $basePath.'/'.$mainDir.'/composer.json',
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

        // Configuration templates
        \file_put_contents(
            $basePath.'/'.$mainDir.'/src/config/editor_templates/cabin_config.twig',
            '{# This affects the Cabin configuration screen on Bridge. #}'
        );
        \file_put_contents(
            $basePath.'/'.$mainDir.'/src/config/templates/config.twig',
            '{# This is used to update config/'.$mainDir.'/cabin.json #}'
        );

        // Some example scripts
        \file_put_contents(
            $basePath.'/'.$mainDir.'/src/Blueprint/init_gear.php',
            '<?php'."\n".
            'use \\Airship\\Engine\\Gears;'."\n".
            'namespace '.$ns.'\\Blueprint;'."\n\n".
            'if (!\\class_exists(\'BlueprintGear\')) {'."\n".
            '    Gears::extract(\'Blueprint\', \'BlueprintGear\', __NAMESPACE__);'."\n".
            '}'."\n\n"
        );
        \file_put_contents(
            $basePath.'/'.$mainDir.'/src/Blueprint/Example.php',
            '<?php'."\n".
            'namespace '.$ns.'\\Blueprint;'."\n\n".
            'require_once __DIR__."/init_gear.php";'."\n\n".
            'class Example extends BlueprintGear'."\n".
            '{'."\n".
            '    public function getData()'."\n".
            '    {'."\n".
            '        return $this->db->run("SELECT * FROM table");'."\n".
            '    }'."\n".
            '}'."\n\n"
        );
        // Some example scripts
        \file_put_contents(
            $basePath.'/'.$mainDir.'/src/Landing/init_gear.php',
            '<?php'."\n".
            'use \\Airship\\Engine\\Gears;'."\n".
            'namespace '.$ns.'\\Landing;'."\n\n".
            'if (!\\class_exists(\'LandingGear\')) {'."\n".
            '    Gears::extract(\'Landing\', \'LandingGear\', __NAMESPACE__);'."\n".
            '}'."\n\n"
        );
        \file_put_contents(
            $basePath.'/'.$mainDir.'/src/Landing/Example.php',
            '<?php'."\n".
            'namespace '.$ns.'\\Landing;'."\n\n".
            'require_once __DIR__."/init_gear.php";'."\n\n".
            'class Example extends LandingGear'."\n".
            '{'."\n".
            '    public function index()'."\n".
            '    {'."\n".
            '        $this->view("example", ["test" => "Hello world!"]);'."\n".
            '    }'."\n".
            '}'."\n\n"
        );
        \file_put_contents(
            $basePath.'/'.$mainDir.'/src/Lens/example.twig',
            '{{ test }}'
        );

        // Example code for an automatic update trigger.
        \file_put_contents(
            $basePath.'/'.$mainDir.'/src/Updates/release-0-0-1.php',
            '<?php'."\n".
            '$db = \Airship\get_database();'."\n".
            '$db->insert("my_table", ['. "\n".
            '    "column" => "value"'."\n".
            '];'."\n\n"
        );

        // Auto-update trigger
        \file_put_contents(
            $basePath.'/'.$mainDir.'/src/update_trigger.php',
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

    /**
     * Domain-specific variant of PHP's native ucfirst()
     *
     * @param string $string
     * @return string
     */
    protected function upperFirst(string $string = '')
    {
        return \trim(parent::upperFirst($string), '_');
    }
}
