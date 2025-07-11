<?php

namespace Amplify\System\Utility\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class IcecatDefinition extends Model implements Auditable
{
    use CrudTrait;
    use \OwenIt\Auditing\Auditable;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'icecat_definitions';

    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];

    // protected $fillable = [];
    // protected $hidden = [];
    protected $attributes = [
        'document' => '"{\"checked\":false}"',
    ];

    protected $casts = [
        'brand' => 'array',
        'brand_part_code' => 'array',
        'product_name' => 'array',
        'gtin' => 'array',
        'brand_logo' => 'array',
        'main_image' => 'array',
        'thumbnail' => 'array',
        'short_description' => 'array',
        'long_description' => 'array',
        'features' => 'array',
        'document' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    public function brandChecked()
    {
        return optional(json_decode($this->brand))->checked;
    }

    public function brandPartCodeChecked()
    {
        return optional(json_decode($this->brand_part_code))->checked;
    }

    public function gtinChecked()
    {
        return optional(json_decode($this->gtin))->checked;
    }

    public function brandLogoChecked()
    {
        return optional(json_decode($this->brand_logo))->checked;
    }

    public function featuresChecked()
    {
        return optional(json_decode($this->features))->checked;
    }

    public function mainImageChecked()
    {
        return optional(json_decode($this->main_image))->checked;
    }

    public function thumbnailChecked()
    {
        return optional(json_decode($this->thumbnail))->checked;
    }

    public function galleryChecked()
    {
        return optional(json_decode($this->gallery))->checked;
    }

    public function shortDescriptionChecked()
    {
        return optional(json_decode($this->short_description))->checked;
    }

    public function longDescriptionChecked()
    {
        return optional(json_decode($this->long_description))->checked;
    }

    public function productNameChecked()
    {
        return optional(json_decode($this->product_name))->checked;
    }

    public function documentChecked()
    {
        return optional(json_decode($this->document))->checked;
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

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
