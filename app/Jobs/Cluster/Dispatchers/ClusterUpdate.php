<?php

declare(strict_types=1);

namespace App\Jobs\Cluster\Dispatchers;

use App\Jobs\Base\Job;
use App\Jobs\Cluster\Actions\ClusterUpdate as ClusterUpdateJob;
use App\Models\Kubernetes\Clusters\Cluster;
use Carbon\Carbon;

/**
 * Class ClusterUpdate.
 *
 * This class is the dispatcher job for processing cluster updates.
 *
 * @author Marcel Menk <marcel.menk@ipvx.io>
 */
class ClusterUpdate extends Job
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
            ->where('update', '=', true)
            ->where('delete', '=', false)
            ->whereNotNull('approved_at')
            ->each(function (Cluster $cluster) {
                $this->dispatch((new ClusterUpdateJob([
                    'cluster_id' => $cluster->id,
                ]))->onQueue('cluster'));

                $cluster->update([
                    'update'               => false,
                    'update_dispatched_at' => Carbon::now(),
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
            'job:cluster:dispatcher:ClusterUpdate',
        ];
    }
}
