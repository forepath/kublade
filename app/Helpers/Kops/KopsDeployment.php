<?php

declare(strict_types=1);

namespace App\Helpers\Kops;

use App\Exceptions\KopsException;
use App\Helpers\Kubernetes\YamlFormatter;
use App\Models\Kubernetes\Clusters\Cluster;
use Exception;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Yaml\Yaml;

/**
 * Class KopsDeployment.
 *
 * This class is the helper for the Kops deployment.
 *
 * @author Marcel Menk <marcel.menk@ipvx.io>
 */
class KopsDeployment
{
    /**
     * Generate a deployment.
     *
     * @param Cluster $cluster
     * @param array   $data
     * @param array   $secretData
     * @param bool    $replaceExisting
     */
    public static function generate(Cluster $cluster, array $data = [], array $secretData = [], bool $replaceExisting = false)
    {
        if (Storage::disk('local')->exists($cluster->kopsPath) && !$replaceExisting) {
            throw new KopsException('Forbidden', 403);
        }

        if ($replaceExisting) {
            Storage::disk('local')->deleteDirectory($cluster->kopsPath);
        }

        $clusterDirectoryStatus = Storage::disk('local')->makeDirectory($cluster->kopsPath);

        if (!$clusterDirectoryStatus) {
            throw new KopsException('Server Error', 500);
        }

        $cluster->template->fullTree->each(function ($item) use ($cluster, $data, $secretData) {
            if ($item->type === 'file') {
                self::createFile($item, $cluster, $data, $secretData);
            } elseif ($item->type === 'folder') {
                self::createFolder($item, $cluster, $data, $secretData);
            }
        });

        // TODO: Properly order files and apply every file separately
        // TODO: Set proper s3 state storage backend
        $cmd    = ['kops', 'create', '-f', $cluster->kopsPath];
        $result = Process::run($cmd);

        if (!$result->successful()) {
            throw new KopsException('Failed to create cluster', 500);
        }
    }

    // TODO: Add a method to update a cluster

    /**
     * Delete a deployment.
     *
     * @param Cluster $cluster
     */
    public static function delete(Cluster $cluster)
    {
        if (!Storage::disk('local')->exists($cluster->kopsPath)) {
            throw new KopsException('Not Found', 404);
        }

        // TODO: Properly order files and apply every file separately
        // TODO: Set proper s3 state storage backend
        $cmd    = ['kops', 'delete', '-f', $cluster->kopsPath, '--yes'];
        $result = Process::run($cmd);

        if (!$result->successful()) {
            throw new KopsException('Failed to delete cluster', 500);
        }

        if (!Storage::disk('local')->deleteDirectory($cluster->kopsPath)) {
            throw new KopsException('Server Error', 500);
        }
    }

    /**
     * Create a folder.
     *
     * @param object  $item
     * @param Cluster $cluster
     * @param array   $data
     * @param array   $secretData
     * @param array   $portClaims
     */
    private static function createFolder(object $item, Cluster $cluster, array $data = [], array $secretData = [], array $portClaims = [])
    {
        $path = $cluster->kopsPath . $item->object->path;

        Storage::disk('local')->makeDirectory($path);

        $item->children?->each(function ($child) use ($cluster, $data, $secretData, $portClaims) {
            if ($child->type === 'file') {
                self::createFile($child, $cluster, $data, $secretData, $portClaims);
            } elseif ($child->type === 'folder') {
                self::createFolder($child, $cluster, $data, $secretData, $portClaims);
            }
        });
    }

    /**
     * Create a file.
     *
     * @param object  $item
     * @param Cluster $cluster
     * @param array   $data
     * @param array   $secretData
     * @param array   $portClaims
     */
    private static function createFile(object $item, Cluster $cluster, array $data = [], array $secretData = [], array $portClaims = [])
    {
        $path = $cluster->kopsPath . $item->object->path;

        try {
            $templateContent = Yaml::parse(
                Blade::render(
                    str_replace("\t", '  ', $item->object->content),
                    [
                        'data'   => $data,
                        'secret' => $secretData,
                    ],
                    false
                )
            );

            if (!$templateContent) {
                Storage::disk('local')->delete($path);

                return;
            }

            Storage::disk('local')->put($path, YamlFormatter::format(Yaml::dump($templateContent, 10, 2)));
        } catch (Exception $e) {
            throw new KopsException('Server Error', 500);
        }
    }
}
