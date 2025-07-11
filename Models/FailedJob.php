<?php

namespace Amplify\System\Utility\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class FailedJob extends Model implements Auditable
{
    use CrudTrait;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'failed_jobs';

    protected $casts = [
        'failed_at' => 'datetime',
        'payload' => 'json',
        //        'exception' => 'json'
    ];
}
