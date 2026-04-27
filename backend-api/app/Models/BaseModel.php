<?php

namespace App\Models;

use App\Traits\HasModelHelpers;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class BaseModel extends Model
{
    use HasModelHelpers, LogsActivity, SoftDeletes;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'metadata' => 'array',
        'settings' => 'array',
        'config' => 'array',
        'data' => 'array',
        'options' => 'array',
        'parameters' => 'array',
        'attributes' => 'array',
        'properties' => 'array',
        'flags' => 'array',
        'tags' => 'array',
        'categories' => 'array',
        'groups' => 'array'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'deleted_at',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'status',
        'type',
        'metadata',
        'settings',
        'config',
        'data',
        'options',
        'parameters',
        'attributes',
        'properties',
        'flags',
        'tags',
        'categories',
        'groups'
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            if (Schema::hasColumn($model->getTable(), 'created_by')) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (Schema::hasColumn($model->getTable(), 'updated_by')) {
                $model->updated_by = auth()->id();
            }
        });

        static::deleting(function ($model) {
            if (Schema::hasColumn($model->getTable(), 'deleted_by')) {
                $model->deleted_by = auth()->id();
                $model->save();
            }
        });
    }

    /**
     * Begin a new database transaction.
     */
    public static function beginTransaction()
    {
        DB::beginTransaction();
    }

    /**
     * Commit the active database transaction.
     */
    public static function commitTransaction()
    {
        DB::commit();
    }

    /**
     * Rollback the active database transaction.
     */
    public static function rollbackTransaction()
    {
        DB::rollBack();
    }

    /**
     * Get the table associated with the model.
     */
    public static function getTableName()
    {
        return with(new static)->getTable();
    }

    /**
     * Get the primary key for the model.
     */
    public static function getPrimaryKey()
    {
        return with(new static)->getKeyName();
    }

    /**
     * Get the auto-incrementing key type.
     */
    public static function getKeyType()
    {
        return with(new static)->getKeyType();
    }

    /**
     * Get the columns for the model.
     */
    public static function getTableColumns()
    {
        return Schema::getColumnListing(static::getTableName());
    }

    /**
     * Check if the model uses timestamps.
     */
    public static function usesTimestamps()
    {
        return with(new static)->timestamps;
    }

    /**
     * Check if the model uses soft deletes.
     */
    public static function usesSoftDeletes()
    {
        return in_array(SoftDeletes::class, class_uses_recursive(static::class));
    }

    /**
     * Get the model's relationships.
     */
    public static function getRelationships()
    {
        $model = new static;
        $relationships = [];

        foreach ((new \ReflectionClass($model))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class != get_class($model) ||
                !empty($method->getParameters()) ||
                $method->getName() == __FUNCTION__) {
                continue;
            }

            try {
                $return = $method->invoke($model);

                if ($return instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
                    $relationships[$method->getName()] = [
                        'type' => (new \ReflectionClass($return))->getShortName(),
                        'model' => (new \ReflectionClass($return->getRelated()))->getName()
                    ];
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return $relationships;
    }

    /**
     * Get the model's fillable attributes.
     */
    public static function getFillableAttributes()
    {
        return with(new static)->getFillable();
    }

    /**
     * Get the model's guarded attributes.
     */
    public static function getGuardedAttributes()
    {
        return with(new static)->getGuarded();
    }

    /**
     * Get the model's hidden attributes.
     */
    public static function getHiddenAttributes()
    {
        return with(new static)->getHidden();
    }

    /**
     * Get the model's visible attributes.
     */
    public static function getVisibleAttributes()
    {
        return with(new static)->getVisible();
    }

    /**
     * Get the model's appended attributes.
     */
    public static function getAppendedAttributes()
    {
        return with(new static)->getAppends();
    }

    /**
     * Get the model's date attributes.
     */
    public static function getDates()
    {
        return with(new static)->getDates();
    }

    /**
     * Get the model's casts.
     */
    public static function getCasts()
    {
        return with(new static)->getCasts();
    }
}
