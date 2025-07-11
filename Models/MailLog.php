<?php

namespace Amplify\System\Utility\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MailLog extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'email' => 'array',
        'data' => 'json',
    ];
}
