<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // إذا كنت تستخدم API

class Client extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'clients'; // تأكيد اسم الجدول

    protected $fillable = [
        'name',
        'email',
        'password',
        // أضف أي أعمدة أخرى تريد تعبئتها
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}