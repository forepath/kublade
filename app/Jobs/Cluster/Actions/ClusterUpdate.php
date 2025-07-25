<?php

declare(strict_types=1);

namespace App\Jobs\Cluster\Actions;

use App\Helpers\Kops\KopsDeployment;
use App\Jobs\Base\Job;
use App\Models\Kubernetes\Clusters\Cluster;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Class ClusterUpdate.
 *
 * This class is the action job for processing cluster updates.
 *
 * @author Marcel Menk <marcel.menk@ipvx.io>
 */
class ClusterUpdate extends Job implements ShouldBeUnique
{
    public $cluster_id;

    public static $onQueue = 'cluster';

    /**
     * ClusterUpdate constructor.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->cluster_id = $data['cluster_id'];
    }

    /**
     * Execute job algorithm.
     */
    public function handle()
    {
        $cluster = Cluster::find($this->cluster_id);

        if (!$cluster) {
            return;
        }

        $publicData = [];
        $secretData = [];

        $cluster->clusterData->each(function ($data) use (&$publicData) {
            $publicData[$data->key] = $data->value;
        });

        $cluster->clusterSecretData->each(function ($data) use (&$secretData) {
            $secretData[$data->key] = $data->value;
        });

        if ($kubeconfig = KopsDeployment::generate($cluster, $publicData, $secretData, true)) {
            $cluster->k8sCredentials->update([
                'kubeconfig' => $kubeconfig,
            ]);

            $cluster->update([
                'updated_at' => Carbon::now(),
            ]);
        }
    }

    /**
     * Define tags which the job can be identified by.
     *
     * @return array
     */
    public function tags(): array
    {
        return [
            'job',
            'job:cluster',
            'job:cluster:action',
            'job:cluster:action:ClusterUpdate',
            'job:cluster:action:ClusterUpdate:' . $this->cluster_id,
        ];
    }

    /**
     * Set a unique identifier to avoid duplicate queuing of the same task.
     *
     * @return string
     */
    public function uniqueId(): string
    {
        return 'cluster-update-' . $this->cluster_id;
    }

    /**
     * Set middleware to avoid job overlapping.
     */
    public function middleware()
    {
        return [new WithoutOverlapping('cluster')];
    }
}
