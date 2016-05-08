<?php
declare(strict_types=1);
namespace Airship\Barge\Commands;

/**
 * Class Motif
 *
 * Create a new Motif (theme)
 *
 * @package Airship\Barge\Commands
 */
class Motif extends Proto\Init
{
    public $essential = true;
    public $name = 'Motif';
    public $description = 'Create a new Airship Motif project.';
    public $display = 4;
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