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
        'email_verified_at'    => 'datetime',
        // Per-category notification opt-in. Cast so the Notification class
        // via() methods can use a plain `!$client->notify_*` truthiness
        // check — Eloquent's default attribute path returns the raw DB
        // value (often "0"/"1" strings on tinyint columns), which would
        // not gate correctly without coercion.
        'notify_transactions'  => 'boolean',
        'notify_shipments'     => 'boolean',
        'notify_receipts'      => 'boolean',
    ];
}