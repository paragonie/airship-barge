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
     * @param string $supplier
     * @param string $project_name
     * @param string $basepath
     * @param string $description
     * @return bool
     */
    protected function createSkeleton(
        string $supplier,
        string $project_name,
        string $basepath,
        string $description
    ): bool {

    }
}