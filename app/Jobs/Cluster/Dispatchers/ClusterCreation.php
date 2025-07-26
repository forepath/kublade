<?php

declare(strict_types=1);

namespace App\Jobs\Cluster\Dispatchers;

use App\Jobs\Base\Job;
use App\Jobs\Cluster\Actions\ClusterCreation as ClusterCreationJob;
use App\Models\Kubernetes\Clusters\Cluster;
use Carbon\Carbon;

/**
 * Class ClusterCreation.
 *
 * This class is the dispatcher job for processing cluster creation.
 *
 * @author Marcel Menk <marcel.menk@ipvx.io>
 */
class ClusterCreation extends Job
{
    public static $onQueue = 'dispatchers';

    /**
     * Execute job algorithm.
     */
    public function handle()
    {
        Cluster::whereNotNull('template_id')
            ->whereNull('deployed_at')
            ->whereNull('creation_dispatched_at')
            ->whereNull('deletion_dispatched_at')
            ->where('delete', '=', false)
            ->whereNotNull('approved_at')
            ->each(function (Cluster $cluster) {
                $this->dispatch((new ClusterCreationJob([
                    'cluster_id' => $cluster->id,
                ]))->onQueue('cluster'));

                $cluster->update([
                    'creation_dispatched_at' => Carbon::now(),
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
            'job:cluster:dispatcher:ClusterCreation',
        ];
    }
}
