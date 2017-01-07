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
    /**
     * @var bool
     */
    public $essential = true;

    /**
     * @var string
     */
    public $name = 'Cabin';

    /**
     * @var string
     */
    public $description = 'Create a new Airship Cabin project.';

    /**
     * @var int
     */
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
            \mkdir($basePath, 0775);
        }
        // For lazy autoloading...
        $mainDir = $this->makeNamespace($supplier, $project_name);
        $ns = \implode('\\', [
            'Airship',
            'Cabin',
            $mainDir
        ]);
        
        \mkdir($basePath.'/'.$mainDir, 0775);
        \mkdir($basePath.'/'.$mainDir.'/dist/', 0775);
        \mkdir($basePath.'/'.$mainDir.'/src/', 0775);

        \mkdir($basePath.'/'.$mainDir.'/src/Blueprint', 0775);
        \mkdir($basePath.'/'.$mainDir.'/src/config', 0777);
        \mkdir($basePath.'/'.$mainDir.'/src/config/editor_templates', 0775);
        \mkdir($basePath.'/'.$mainDir.'/src/config/templates', 0775);
        \mkdir($basePath.'/'.$mainDir.'/src/Exceptions', 0775);
        \mkdir($basePath.'/'.$mainDir.'/src/Gadgets', 0775);
        \mkdir($basePath.'/'.$mainDir.'/src/Landing', 0775);
        \mkdir($basePath.'/'.$mainDir.'/src/Lens', 0775);
        \mkdir($basePath.'/'.$mainDir.'/src/Queries', 0775);
        \mkdir($basePath.'/'.$mainDir.'/src/public', 0775);
        \mkdir($basePath.'/'.$mainDir.'/src/Updates', 0775);

        // Basic cabin.json
        \file_put_contents(
            $basePath.'/'.$mainDir.'/cabin.json',
            \json_encode(
                [
                    'airship_major_version' =>
                        0,
                    'name' =>
                        $project_name,
                    'namespace' =>
                        $ns,
                    'bundled-gadgets' =>
                        [],
                    'bundled-motifs' =>
                        [],
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
            'namespace '.$ns.'\\Blueprint;'."\n\n".
            'use \\Airship\\Engine\\Gears;'."\n\n".
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
            'namespace '.$ns.'\\Landing;'."\n\n".
            'use \\Airship\\Engine\\Gears;'."\n\n".
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
            '        $this->lens("example", ["test" => "Hello world!"]);'."\n".
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
            '<?php' . "\n" .
            'declare(strict_types=1);' . "\n\n" .
            '$db = \Airship\get_database();' . "\n" .
            '$db->insert("my_table", ['. "\n" .
            '    "column" => "value"' . "\n" .
            ']);' . "\n\n"
        );

        // Auto-update trigger
        \file_put_contents(
            $basePath.'/'.$mainDir.'/src/update_trigger.php',
            '<?php' . "\n" .
            '$metadata = \Airship\loadJSON(\dirname(__DIR__) . "/cabin.json");'."\n".
            'if (\\Airship\\expand_version($previous_metadata[\'version\']) <= \\Airship\\expand_version(\'0.0.1\')) {'."\n".
            '    require_once __DIR__."/Updates/release-0-0-1.php";'."\n".
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

    /**
     * some-test-user/cabin--for-the-win =>
     * Some_Test_User__Cabin_For_The_Win
     *
     * @param string $supplier
     * @param string $cabin
     * @return string
     */
    protected function makeNamespace(string $supplier, string $cabin): string
    {
        $supplier = \preg_replace('/[^A-Za-z0-9_]/', '_', $supplier);
        $exp = \explode('_', $supplier);
        $supplier = \implode('_', \array_map('ucfirst', $exp));
        $supplier = \preg_replace('/_{2,}/', '_', $supplier);

        $cabin = \preg_replace('/[^A-Za-z0-9_]/', '_', $cabin);
        $exp = \explode('_', $cabin);
        $cabin = \implode('_', \array_map('ucfirst', $exp));
        $cabin = \preg_replace('/_{2,}/', '_', $cabin);

        return \implode('__',
            [
                \trim($supplier, '_'),
                \trim($cabin, '_')
            ]
        );
    }
}
