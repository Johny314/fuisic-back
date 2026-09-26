<?php

namespace App\Models;

use App\Models\Concerns\AuditsAdminChanges;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Section extends Model
{
    use AuditsAdminChanges;
    use CrudTrait;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
    ];
}
