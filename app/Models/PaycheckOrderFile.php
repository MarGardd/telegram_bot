<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaycheckOrderFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'path'
    ];

    public function getPathAttribute($value){
        if($value)
            return url('storage/' . $value);
        else
            return $value;
    }
}
