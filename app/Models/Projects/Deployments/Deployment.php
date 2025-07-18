<?php

declare(strict_types=1);

namespace App\Models\Projects\Deployments;

use App\Models\Kubernetes\Clusters\Cluster;
use App\Models\Kubernetes\Resources\Ns;
use App\Models\Kubernetes\Resources\PodLog;
use App\Models\Projects\Projects\Project;
use App\Models\Projects\Templates\Template;
use App\Traits\LogsActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * Class Deployment.
 *
 * This class is the model for deployments.
 *
 * @OA\Schema(
 *     schema="Deployment",
 *     type="object",
 *
 *     @OA\Property(property="id", type="string", format="uuid", example="123e4567-e89b-12d3-a456-426614174000"),
 *     @OA\Property(property="user_id", type="string", format="uuid", example="123e4567-e89b-12d3-a456-426614174000"),
 *     @OA\Property(property="project_id", type="string", format="uuid", example="123e4567-e89b-12d3-a456-426614174000"),
 *     @OA\Property(property="namespace_id", type="string", format="uuid", example="123e4567-e89b-12d3-a456-426614174000", nullable=true),
 *     @OA\Property(property="template_id", type="string", format="uuid", example="123e4567-e89b-12d3-a456-426614174000"),
 *     @OA\Property(property="cluster_id", type="string", format="uuid", example="123e4567-e89b-12d3-a456-426614174000"),
 *     @OA\Property(property="name", type="string", example="Deployment 1"),
 *     @OA\Property(property="uuid", type="string", format="uuid", example="123e4567-e89b-12d3-a456-426614174000"),
 *     @OA\Property(property="paused", type="boolean", example=false),
 *     @OA\Property(property="update", type="boolean", example=false),
 *     @OA\Property(property="delete", type="boolean", example=false),
 *     @OA\Property(property="deployed_at", type="string", format="date-time", example="2021-01-01 00:00:00", nullable=true),
 *     @OA\Property(property="deployment_updated_at", type="string", format="date-time", example="2021-01-01 00:00:00", nullable=true),
 *     @OA\Property(property="creation_dispatched_at", type="string", format="date-time", example="2021-01-01 00:00:00", nullable=true),
 *     @OA\Property(property="update_dispatched_at", type="string", format="date-time", example="2021-01-01 00:00:00", nullable=true),
 *     @OA\Property(property="deletion_dispatched_at", type="string", format="date-time", example="2021-01-01 00:00:00", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2021-01-01 00:00:00", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2021-01-01 00:00:00", nullable=true),
 *     @OA\Property(property="deleted_at", type="string", format="date-time", example="2021-01-01 00:00:00", nullable=true),
 * )
 *
 * @author Marcel Menk <marcel.menk@ipvx.io>
 *
 * @property string $id
 * @property string $user_id
 * @property string $project_id
 * @property string $template_id
 * @property string $name
 * @property string $uuid
 * @property bool   $paused
 * @property bool   $update
 * @property bool   $delete
 * @property Carbon $deployed_at
 * @property Carbon $deployment_updated_at
 * @property Carbon $creation_dispatched_at
 * @property Carbon $update_dispatched_at
 * @property Carbon $deletion_dispatched_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $deleted_at
 */
class Deployment extends Model
{
    use SoftDeletes;
    use HasUuids;
    use HasFactory;
    use LogsActivity;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'deployments';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var bool|string[]
     */
    protected $guarded = [
        'id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'paused'                 => 'boolean',
        'update'                 => 'boolean',
        'delete'                 => 'boolean',
        'approved_at'            => 'datetime',
        'deployed_at'            => 'datetime',
        'deployment_updated_at'  => 'datetime',
        'creation_dispatched_at' => 'datetime',
        'update_dispatched_at'   => 'datetime',
        'deletion_dispatched_at' => 'datetime',
    ];

    /**
     * Relation to project.
     *
     * @return HasOne
     */
    public function project(): HasOne
    {
        return $this->hasOne(Project::class, 'id', 'project_id');
    }

    /**
     * Relation to template.
     *
     * @return HasOne
     */
    public function template(): HasOne
    {
        return $this->hasOne(Template::class, 'id', 'template_id');
    }

    /**
     * Relation to deployment metrics.
     *
     * @return HasMany
     */
    public function metrics(): HasMany
    {
        return $this->hasMany(DeploymentMetric::class, 'deployment_id', 'id');
    }

    /**
     * Relation to deployment data.
     *
     * @return HasMany
     */
    public function deploymentData(): HasMany
    {
        return $this->hasMany(DeploymentData::class, 'deployment_id', 'id');
    }

    /**
     * Relation to deployment secret data.
     *
     * @return HasMany
     */
    public function deploymentSecretData(): HasMany
    {
        return $this->hasMany(DeploymentSecretData::class, 'deployment_id', 'id');
    }

    /**
     * Relation to deployment namespaces.
     *
     * @return HasMany
     */
    public function namespaces(): HasMany
    {
        return $this->hasMany(Ns::class, 'id', 'namespace_id');
    }

    /**
     * Relation to deployment.
     *
     * @return HasOne
     */
    public function limit(): HasOne
    {
        return $this->hasOne(DeploymentLimit::class, 'deployment_id', 'id');
    }

    /**
     * Relation to cluster.
     *
     * @return HasOne
     */
    public function cluster(): HasOne
    {
        return $this->hasOne(Cluster::class, 'id', 'cluster_id');
    }

    /**
     * Relation to reserved ports.
     *
     * @return HasMany
     */
    public function ports(): HasMany
    {
        return $this->hasMany(ReservedPort::class, 'deployment_id', 'id');
    }

    /**
     * Relation to ingress rules as source.
     *
     * @return HasMany
     */
    public function ingressAsSource(): HasMany
    {
        return $this->hasMany(DeploymentLink::class, 'source_deployment_id', 'id');
    }

    /**
     * Relation to ingress rules as source.
     *
     * @return HasMany
     */
    public function ingressAsTarget(): HasMany
    {
        return $this->hasMany(DeploymentLink::class, 'target_deployment_id', 'id');
    }

    /**
     * Relation to commits.
     *
     * @return HasMany
     */
    public function commits(): HasMany
    {
        return $this->hasMany(DeploymentCommit::class, 'deployment_id', 'id')
            ->orderByDesc('created_at');
    }

    /**
     * Get the status attribute.
     *
     * @return string
     */
    public function getStatusAttribute(): string
    {
        if ($this->delete) {
            return '<span class="badge bg-danger">' . __('Deleting') . '</span>';
        }

        if (! $this->approved_at) {
            return '<span class="badge bg-primary">' . __('Approving') . '</span>';
        }

        if ($this->update) {
            return '<span class="badge bg-warning text-body">' . __('Updating') . '</span>';
        }

        if ($this->deployed_at) {
            return '<span class="badge bg-success">' . __('Deployed') . '</span>';
        }

        return '<span class="badge bg-info">' . __('Pending') . '</span>';
    }

    /**
     * Get the status attribute.
     *
     * @return string
     */
    public function getSimpleStatusAttribute(): string
    {
        if ($this->delete) {
            return __('Deleting');
        }

        if (! $this->approved_at) {
            return __('Approving');
        }

        if ($this->update) {
            return __('Updating');
        }

        if ($this->deployed_at) {
            return __('Deployed');
        }

        return __('Pending');
    }

    /**
     * Get the path attribute.
     *
     * @return string
     */
    public function getPathAttribute(): string
    {
        return $this->cluster->repositoryDeploymentPath . $this->uuid;
    }

    /**
     * Get the statistics attribute.
     *
     * @return array
     */
    public function getStatisticsAttribute(): array | null
    {
        $deploymentMetric = $this->metrics()->orderByDesc('created_at')->first();

        if (! $deploymentMetric) {
            return null;
        }

        return [
            'cpu'     => $deploymentMetric->cpu_core_usage * 100,
            'memory'  => $deploymentMetric->memory_bytes / 1024 / 1024,
            'storage' => $deploymentMetric->storage_bytes / 1024 / 1024,
        ];
    }

    /**
     * Get the logs attribute.
     *
     * @return Collection
     */
    public function getLogsAttribute(): Collection
    {
        return PodLog::whereHas('pod', function ($query) {
            $query->whereHas('namespace', function ($query) {
                $query->where('id', $this->namespace_id);
            });
        })
            ->select('id', 'pod_id', 'created_at', 'updated_at', 'deleted_at')
            ->get() ?? collect();
    }

    /**
     * Get the network policies attribute.
     *
     * @return Collection
     */
    public function getNetworkPoliciesAttribute(): Collection
    {
        return $this->ingressAsSource->merge($this->ingressAsTarget);
    }
}
