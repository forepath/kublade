<?php

declare(strict_types=1);

namespace App\Jobs\Cluster\Dispatchers;

use App\Jobs\Base\Job;
use App\Jobs\Cluster\Actions\ClusterDeletion as ClusterDeletionJob;
use App\Models\Kubernetes\Clusters\Cluster;
use Carbon\Carbon;

/**
 * Class ClusterDeletion.
 *
 * This class is the dispatcher job for processing cluster deletion.
 *
 * @author Marcel Menk <marcel.menk@ipvx.io>
 */
class ClusterDeletion extends Job
{
    public static $onQueue = 'dispatchers';

    /**
     * Execute job algorithm.
     */
    public function handle()
    {
        Cluster::whereNotNull('template_id')
            ->whereNotNull('deployed_at')
            ->whereNotNull('creation_dispatched_at')
            ->whereNull('deletion_dispatched_at')
            ->where('delete', '=', true)
            ->each(function (Cluster $cluster) {
                $this->dispatch((new ClusterDeletionJob([
                    'cluster_id' => $cluster->id,
                ]))->onQueue('cluster'));

                $cluster->update([
                    'deletion_dispatched_at' => Carbon::now(),
                ]);
            });
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
            'job:cluster:dispatcher',
            'job:cluster:dispatcher:ClusterDeletion',
        ];
    }
}
