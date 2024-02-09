<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaycheckOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'chat_id',
        'username',
        'send'
    ];

    public function getCreatedAtAttribute($value){
        if($value)
            return Carbon::parse($value)->setTimezone('Europe/Samara')/*addHours(4)*/->format('d.m.Y');
        else
            return $value;
    }

    public function answers(){
        return $this->hasMany(Answer::class, 'order_id');
    }

    public function files(){
        return $this->hasMany(PaycheckOrderFile::class, 'order_id');
    }
}
