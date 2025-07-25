<?php

declare(strict_types=1);

namespace App\Models\Kubernetes\Clusters;

use App\Models\Projects\Templates\TemplateEnvironmentVariable;
use App\Traits\LogsActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Sagalbot\Encryptable\Encryptable;

/**
 * Class ClusterSecretData.
 *
 * This class is the model for cluster data key value pairs.
 *
 * @author Marcel Menk <marcel.menk@ipvx.io>
 *
 * @property string $id
 * @property string $cluster_id
 * @property string $template_environment_variable_id
 * @property string $value
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $deleted_at
 */
class ClusterEnvironmentVariable extends Model
{
    use SoftDeletes;
    use HasUuids;
    use Encryptable;
    use HasFactory;
    use LogsActivity;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cluster_env_variables';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var bool|string[]
     */
    protected $guarded = [
        'id',
    ];

    /**
     * The attributes that should be encrypted.
     *
     * @var array<string>
     */
    protected $encryptable = [
        'value',
    ];

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
     * Relation to template environment variable.
     *
     * @return HasOne
     */
    public function environmentVariable(): HasOne
    {
        return $this->hasOne(TemplateEnvironmentVariable::class, 'id', 'template_environment_variable_id');
    }
}
