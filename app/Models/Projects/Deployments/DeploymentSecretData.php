<?php

declare(strict_types=1);

namespace App\Models\Projects\Deployments;

use App\Models\Projects\Templates\TemplateField;
use App\Traits\LogsActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Sagalbot\Encryptable\Encryptable;

/**
 * Class DeploymentSecretData.
 *
 * This class is the model for deployment data key value pairs.
 *
 * @author Marcel Menk <marcel.menk@ipvx.io>
 *
 * @property string $id
 * @property string $deployment_id
 * @property string $template_field_id
 * @property string $key
 * @property string $value
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $deleted_at
 */
class DeploymentSecretData extends Model
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
    protected $table = 'deployment_secret_data';

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
     * Relation to deployment.
     *
     * @return HasOne
     */
    public function deployment(): HasOne
    {
        return $this->hasOne(Deployment::class, 'id', 'deployment_id');
    }

    /**
     * Relation to template field.
     *
     * @return HasOne
     */
    public function field(): HasOne
    {
        return $this->hasOne(TemplateField::class, 'id', 'template_field_id');
    }
}
