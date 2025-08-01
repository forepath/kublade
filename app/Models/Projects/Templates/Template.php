<?php

declare(strict_types=1);

namespace App\Models\Projects\Templates;

use App\Models\User;
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
 * Class Template.
 *
 * This class is the model for templates.
 *
 * @OA\Schema(
 *     schema="Template",
 *     type="object",
 *
 *     @OA\Property(property="id", type="string", format="uuid", example="123e4567-e89b-12d3-a456-426614174000"),
 *     @OA\Property(property="user_id", type="integer", format="int64", example="1"),
 *     @OA\Property(property="type", type="string", enum={"application", "cluster"}, example="application"),
 *     @OA\Property(property="name", type="string", example="Template 1"),
 *     @OA\Property(property="netpol", type="boolean", example=false),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2021-01-01 00:00:00", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2021-01-01 00:00:00", nullable=true),
 *     @OA\Property(property="deleted_at", type="string", format="date-time", example="2021-01-01 00:00:00", nullable=true),
 * )
 *
 * @author Marcel Menk <marcel.menk@ipvx.io>
 *
 * @property string $id
 * @property string $user_id
 * @property string $type
 * @property string $name
 * @property bool   $netpol
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $deleted_at
 */
class Template extends Model
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
    protected $table = 'templates';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var bool|string[]
     */
    protected $guarded = [
        'id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'netpol' => 'boolean',
    ];

    /**
     * Relation to user.
     *
     * @return HasOne
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    /**
     * Relation to template fields.
     *
     * @return HasMany
     */
    public function fields(): HasMany
    {
        return $this->hasMany(TemplateField::class, 'template_id', 'id');
    }

    /**
     * Relation to template ports.
     *
     * @return HasMany
     */
    public function ports(): HasMany
    {
        return $this->hasMany(TemplatePort::class, 'template_id', 'id');
    }

    /**
     * Relation to template directories.
     *
     * @return HasMany
     */
    public function directories(): HasMany
    {
        return $this->hasMany(TemplateDirectory::class, 'template_id', 'id')
            ->whereNull('parent_id');
    }

    /**
     * Relation to template files.
     *
     * @return HasMany
     */
    public function files(): HasMany
    {
        return $this->hasMany(TemplateFile::class, 'template_id', 'id')
            ->whereNull('template_directory_id');
    }

    /**
     * Relation to template environment variables.
     *
     * @return HasMany
     */
    public function environmentVariables(): HasMany
    {
        return $this->hasMany(TemplateEnvironmentVariable::class, 'template_id', 'id');
    }

    /**
     * Relation to git credentials.
     *
     * @return HasOne
     */
    public function gitCredentials(): HasOne
    {
        return $this->hasOne(TemplateGitCredential::class, 'template_id', 'id');
    }

    /**
     * Get the tree of the template.
     *
     * @return Collection
     */
    public function getTreeAttribute(): Collection
    {
        $subFolders = $this->directories?->map(function (TemplateDirectory $directory) {
            return $directory->tree;
        })->toArray() ?? [];
        $subFiles = $this->files?->map(function (TemplateFile $file) {
            return $file->tree;
        })->toArray() ?? [];

        return collect([
            ...$subFolders,
            ...$subFiles,
        ]);
    }

    /**
     * Get the full tree of the template.
     *
     * @return Collection
     */
    public function getFullTreeAttribute(): Collection
    {
        $subFolders = $this->directories?->map(function (TemplateDirectory $directory) {
            return $directory->fullTree;
        })->toArray() ?? [];
        $subFiles = $this->files?->map(function (TemplateFile $file) {
            return $file->fullTree;
        })->toArray() ?? [];

        return collect([
            ...$subFolders,
            ...$subFiles,
        ]);
    }

    /**
     * Get the grouped fields.
     *
     * @return object
     */
    public function getGroupedFieldsAttribute(): object
    {
        return (object) [
            'all'       => $this->fields->where('type', '!=', 'input_hidden'),
            'on_create' => (object) [
                'advanced' => $this->fields
                    ->where('advanced', true)
                    ->where('set_on_create', true)
                    ->where('type', '!=', 'input_hidden'),
                'default' => $this->fields
                    ->where('advanced', false)
                    ->where('set_on_create', true)
                    ->where('type', '!=', 'input_hidden'),
                'hidden' => $this->fields
                    ->where('set_on_create', true)
                    ->where('type', '=', 'input_hidden'),
            ],
            'on_update' => (object) [
                'advanced' => $this->fields
                    ->where('advanced', true)
                    ->where('set_on_update', true)
                    ->where('type', '!=', 'input_hidden'),
                'default' => $this->fields
                    ->where('advanced', false)
                    ->where('set_on_update', true)
                    ->where('type', '!=', 'input_hidden'),
                'hidden' => $this->fields
                    ->where('set_on_update', true)
                    ->where('type', '=', 'input_hidden'),
            ],
        ];
    }

    /**
     * Get the path attribute.
     *
     * @return string
     */
    public function getPathAttribute(): string
    {
        return 'templates/' . $this->id;
    }

    /**
     * Get the repository template path attribute.
     *
     * @return string
     */
    public function getRepositoryImportPathAttribute(): string
    {
        return 'templates/' . $this->id . ($this->gitCredentials?->base_path ?? '');
    }
}
