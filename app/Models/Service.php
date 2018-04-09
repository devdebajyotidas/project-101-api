<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use SoftDeletes;

    protected $fillable=[
        'account_id',
        'name',
    ];

    public function account(){
        return $this->belongsTo('App\Models\Account')->withTrashed();
    }
}
