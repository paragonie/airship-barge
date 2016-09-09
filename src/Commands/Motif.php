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
    public $descriptionPrompt = 'Display name: ';
    public $display = 4;
    /**
     * Create a skeleton for a new Airship motif.
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
        \mkdir($basePath.'/'.$project_name.'/src/lens/', 0755);
        \mkdir($basePath.'/'.$project_name.'/src/public/', 0755);
        // Basic motif.json
        \file_put_contents(
            $basePath.'/'.$project_name.'/src/motif.json',
            \json_encode(
                [
                    'airship_major_version' =>
                        1,
                    'supplier' =>
                        $supplier,
                    'name' =>
                        $project_name,
                    'display_name' =>
                        $description,
                    'cabin' =>
                        !empty($extra['cabin'])
                            ? $extra['cabin']
                            : null,
                    'base_template' =>
                        null,
                    'css' => [
                        'style.css'
                    ],
                    'version' =>
                        '0.0.1'
                ],
                JSON_PRETTY_PRINT
            )
        );

        // Basic CSS file
        \file_put_contents(
            $basePath.'/'.$project_name.'/src/public/style.css',
            'body {' . "\n" .
            '    background-color: red;' . "\n".
            '}' . "\n"
        );

        // Base template
        // Basic CSS file
        \file_put_contents(
            $basePath.'/'.$project_name.'/src/lens/base-lens.twig',
            '{% extends "base.twig" %}' . "\n\n".
            '{% block footer %} No footer for you {% endblock %}'
        );
        return true;
    }

    /**
     * @return array
     */
    protected function getExtraData(): array
    {
        echo 'Is this for a specific cabin?', "\n";
        echo 'If yes, enter a fully qualified cabin name below. (Leave blank for a universal motif.)', "\n";
        echo 'For example: ',
        $this->c['yellow'], 'paragonie/Bridge', $this->c[''], ' or ',
        $this->c['yellow'], 'username/example-cabin', $this->c[''], "\n";

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