<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'char_name';
    }

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function account()
    {
        return $this->hasOne(Account::class);
    }
}
