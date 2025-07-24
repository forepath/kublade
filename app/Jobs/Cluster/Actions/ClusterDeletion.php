<?php

declare(strict_types=1);

namespace App\Jobs\Cluster\Actions;

use App\Helpers\Kops\KopsDeployment;
use App\Jobs\Base\Job;
use App\Models\Kubernetes\Clusters\Cluster;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Class ClusterDeletion.
 *
 * This class is the action job for processing cluster deletion.
 *
 * @author Marcel Menk <marcel.menk@ipvx.io>
 */
class ClusterDeletion extends Job implements ShouldBeUnique
{
    public $cluster_id;

    public static $onQueue = 'cluster';

    /**
     * ClusterDeletion constructor.
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

        if ($release = KopsDeployment::delete($cluster)) {
            $cluster->delete();
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
            'job:cluster:action:ClusterDeletion',
            'job:cluster:action:ClusterDeletion:' . $this->cluster_id,
        ];
    }

    /**
     * Set a unique identifier to avoid duplicate queuing of the same task.
     *
     * @return string
     */
    public function uniqueId(): string
    {
        return 'cluster-deletion-' . $this->cluster_id;
    }

    /**
     * Set middleware to avoid job overlapping.
     */
    public function middleware()
    {
        return [new WithoutOverlapping('cluster')];
    }
}
