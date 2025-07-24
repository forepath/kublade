<?php

declare(strict_types=1);

namespace App\Helpers\Kops;

use App\Exceptions\KopsException;
use App\Helpers\Kubernetes\YamlFormatter;
use App\Models\Kubernetes\Clusters\Cluster;
use App\Models\Kubernetes\Clusters\ClusterData;
use App\Models\Kubernetes\Clusters\ClusterSecretData;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LaravelS3Server\Models\S3AccessCredential;
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

            $s3Credentials = S3AccessCredential::where('access_key_id', $cluster->id)->first();
        } else {
            $s3Credentials = S3AccessCredential::create([
                'access_key_id'     => $cluster->id,
                'secret_access_key' => Str::random(32),
                'description'       => 'Kops state store for cluster ' . $cluster->id,
                'bucket'            => $cluster->id,
            ]);
        }

        $clusterDirectoryStatus = Storage::disk('local')->makeDirectory($cluster->kopsPath);

        if (!$clusterDirectoryStatus) {
            throw new KopsException('Server Error', 500);
        }

        $files = collect();

        $cluster->template->fullTree->each(function ($item) use ($cluster, $data, $secretData, &$files) {
            if ($item->type === 'file') {
                $files->push(self::createFile($item, $cluster, $data, $secretData));
            } elseif ($item->type === 'folder') {
                $files->concat(self::createFolder($item, $cluster, $data, $secretData));
            }
        });

        $files->filter()->sortBy('object.sort')->each(function ($file) use ($cluster, $replaceExisting, $s3Credentials) {
            if ($replaceExisting) {
                $cmd = ['kops', 'replace', '-f', $cluster->kopsPath . $file->object->path, '--force'];
            } else {
                $cmd = ['kops', 'create', '-f', $cluster->kopsPath . $file->object->path];
            }

            $result = Process::env([
                'S3_ENDPOINT'         => config('app.url') . '/s3',
                'S3_FORCE_PATH_STYLE' => 'true',
                'KOPS_STATE_STORE'    => 's3://' . $cluster->id,
                ...($s3Credentials ? [
                    'AWS_ACCESS_KEY_ID'     => $s3Credentials->access_key_id,
                    'AWS_SECRET_ACCESS_KEY' => $s3Credentials->secret_access_key,
                ] : []),
            ])->run($cmd);

            if (!$result->successful()) {
                throw new KopsException('Failed to create cluster', 500);
            }
        });
    }

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

        $s3Credentials = S3AccessCredential::where('access_key_id', $cluster->id)->first();
        $files         = collect();

        $cluster->template->fullTree->each(function ($item) use ($cluster, &$files) {
            if ($item->type === 'file') {
                $files->push(
                    self::createFile(
                        $item,
                        $cluster,
                        $cluster->clusterData->mapWithKeys(function (ClusterData $data) {
                            return [$data->key => $data->value];
                        })->toArray(),
                        $cluster->clusterSecretData->mapWithKeys(function (ClusterSecretData $data) {
                            return [$data->key => $data->value];
                        })->toArray()
                    )
                );
            } elseif ($item->type === 'folder') {
                $files->concat(
                    self::createFolder(
                        $item,
                        $cluster,
                        $cluster->clusterData->mapWithKeys(function (ClusterData $data) {
                            return [$data->key => $data->value];
                        })->toArray(),
                        $cluster->clusterSecretData->mapWithKeys(function (ClusterSecretData $data) {
                            return [$data->key => $data->value];
                        })->toArray()
                    )
                );
            }
        });

        $files->filter()->sortBy('object.sort')->each(function ($file) use ($cluster, $s3Credentials) {
            $cmd    = ['kops', 'delete', '-f', $cluster->kopsPath . $file->object->path, '--yes'];
            $result = Process::env([
                'S3_ENDPOINT'         => config('app.url') . '/s3',
                'S3_FORCE_PATH_STYLE' => 'true',
                'KOPS_STATE_STORE'    => 's3://' . $cluster->id,
                ...($s3Credentials ? [
                    'AWS_ACCESS_KEY_ID'     => $s3Credentials->access_key_id,
                    'AWS_SECRET_ACCESS_KEY' => $s3Credentials->secret_access_key,
                ] : []),
            ])->run($cmd);

            if (!$result->successful()) {
                throw new KopsException('Failed to delete cluster', 500);
            }
        });

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
     *
     * @return Collection<object|null>
     */
    private static function createFolder(object $item, Cluster $cluster, array $data = [], array $secretData = []): Collection
    {
        $files = collect();
        $path  = $cluster->kopsPath . $item->object->path;

        Storage::disk('local')->makeDirectory($path);

        $item->children?->each(function ($child) use ($cluster, $data, $secretData, &$files) {
            if ($child->type === 'file') {
                $files->push(self::createFile($child, $cluster, $data, $secretData));
            } elseif ($child->type === 'folder') {
                $files->concat(self::createFolder($child, $cluster, $data, $secretData));
            }
        });

        return $files;
    }

    /**
     * Create a file.
     *
     * @param object  $item
     * @param Cluster $cluster
     * @param array   $data
     * @param array   $secretData
     *
     * @return object|null
     */
    private static function createFile(object $item, Cluster $cluster, array $data = [], array $secretData = [])
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

            return $item;
        } catch (Exception $e) {
            throw new KopsException('Server Error', 500);
        }

        return null;
    }
}
