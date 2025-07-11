<?php

namespace Amplify\System\Utility\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class IcecatTransformationError extends Model implements Auditable
{
    use CrudTrait, HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'icecat_transformation_errors';

    protected $guarded = ['id'];
}
