<?php

namespace Amplify\System\Utility\Models;

use App\Models\User;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class ImportDefinition extends Model implements Auditable
{
    use CrudTrait, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'import_definitions';

    protected $guarded = ['id'];

    protected $casts = [
        'has_hierarchy' => 'bool',
    ];

    const IMPORT_TYPES = [
        [
            'title' => 'Attributes',
            'value' => 'Attribute',
        ],
        [
            'title' => 'Categories',
            'value' => 'Category',
        ],
        [
            'title' => 'Products',
            'value' => 'Product',
        ],
        [
            'title' => 'Product Classifications',
            'value' => 'ProductClassification',
        ],
        [
            'title' => 'Attribute ~ Product Classification',
            'value' => 'AttributeProductClassification',
        ],
        [
            'title' => 'Category ~ Product',
            'value' => 'CategoryProduct',
        ],
        [
            'title' => 'Attribute ~ Product',
            'value' => 'AttributeProduct',
        ],
        [
            'title' => 'Customers',
            'value' => 'Customer',
        ],
        [
            'title' => 'Contacts',
            'value' => 'Contact',
        ],
        [
            'title' => 'Model Codes',
            'value' => 'ModelCode',
        ],
        [
            'title' => 'Contact Permissions',
            'value' => 'ContactPermissions',
        ],
    ];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        static::creating(function (self $importDefinition) {
            if (! backpack_auth()->id()) {
                return false;
            }

            $importDefinition->setAttribute('user_id', backpack_auth()->id());
        });
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function getLocalNameAttribute(): string
    {
        return $this->attributes['name'] ?? 'English';
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
