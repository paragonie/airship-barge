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

    }

    /**
     * @return array
     */
    protected function getExtraData(): array
    {
        return [];
    }
}