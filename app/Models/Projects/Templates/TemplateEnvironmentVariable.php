<?php

declare(strict_types=1);

namespace App\Models\Projects\Templates;

use App\Traits\LogsActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class TemplateEnvironmentVariable.
 *
 * This class is the model for template environment variables.
 *
 * @OA\Schema(
 *     schema="TemplateEnvironmentVariable",
 *     type="object",
 *
 *     @OA\Property(property="id", type="string", format="uuid", example="123e4567-e89b-12d3-a456-426614174000"),
 *     @OA\Property(property="template_id", type="string", format="uuid", example="123e4567-e89b-12d3-a456-426614174000"),
 *     @OA\Property(property="key", type="string", example="KEY"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2021-01-01 00:00:00", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2021-01-01 00:00:00", nullable=true),
 *     @OA\Property(property="deleted_at", type="string", format="date-time", example="2021-01-01 00:00:00", nullable=true),
 * )
 *
 * @author Marcel Menk <marcel.menk@ipvx.io>
 *
 * @property string $id
 * @property string $template_id
 * @property string $key
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $deleted_at
 */
class TemplateEnvironmentVariable extends Model
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
    protected $table = 'template_env_variables';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var bool|string[]
     */
    protected $guarded = [
        'id',
    ];

    /**
     * Relation to template.
     *
     * @return HasOne
     */
    public function template(): HasOne
    {
        return $this->hasOne(Template::class, 'id', 'template_id');
    }
}
