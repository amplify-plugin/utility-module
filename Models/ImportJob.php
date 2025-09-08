<?php

namespace Amplify\System\Utility\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class ImportJob extends Model implements Auditable
{
    use CrudTrait, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'import_jobs';

    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];

    // protected $fillable = [];
    // protected $hidden = [];

    protected $casts = [
        'schedule_time' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    protected static function boot()
    {
        parent::boot();

        self::creating(function ($model) {
            $model->user_id = backpack_auth()->id();
        });
    }

    public function retryImportJob(): string
    {
        return '<a class="btn btn-sm btn-link" href="'.route('import-job.retry.import.job', $this->id)
               .'" data-toggle="tooltip" title="Retry Import Job"><i class="las la-retweet"></i> Retry</a>';
    }
    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function importDefinition(): BelongsTo
    {
        return $this->belongsTo(ImportDefinition::class);
    }

    public function importErrors(): HasMany
    {
        return $this->hasMany(ImportError::class);
    }

    public function importJobHistory(): HasMany
    {
        return $this->hasMany(ImportJobHistory::class);
    }

    public function importJobHistories(): HasMany
    {
        return $this->hasMany(ImportJobHistory::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Amplify\System\Backend\Models\User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
