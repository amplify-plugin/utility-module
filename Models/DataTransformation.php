<?php

namespace Amplify\System\Utility\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class DataTransformation extends Model implements Auditable
{
    use CrudTrait;
    use \OwenIt\Auditing\Auditable;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'data_transformations';

    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];

    // protected $fillable = [];
    // protected $hidden = [];

    protected $casts = [
        'run_when' => 'array',
    ];

    public const APPLIES_TO = [
        'Products' => '{"name":"Products"}',
        'Categories' => '{"name":"Categories"}',
    ];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * @return \string[][]
     */
    public function getRunWhen()
    {
        return [
            ['name' => 'sdfg'],
            ['name' => 'sdfg'],
            ['name' => 'sdfg'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function dataTransformationErrors()
    {
        return $this->hasMany(DataTransformationError::class);
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

    /**
     * @return false|string
     */
    public function setRunWhenAttribute($value)
    {
        return $this->attributes['run_when'] = json_encode($value);
    }

    /**
     * return button html to test the transformation scripts
     */
    public function testDataTransformationScript(): string
    {
        return '<a class="btn btn-sm btn-link" href="'.route('data-transformation.test.script', $this->id)
               .'" data-toggle="tooltip" title="Test Data Transformation Script"><i class="las la-retweet"></i> Test</a>';
    }

    /**
     * return button html to run the transformation scripts
     */
    public function runDataTransformationScript(): ?string
    {
        return (in_array('On Demand', $this->run_when))
            ? '<a class="btn btn-sm btn-link" href="'.route('data-transformation.run.script', $this->id)
              .'" data-toggle="tooltip" title="Run Data Transformation Script"><i class="las la-play"></i> Run Script</a>'
            : null;
    }
}
