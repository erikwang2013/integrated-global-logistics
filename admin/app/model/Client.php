<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class Client extends Model
{
    protected $table = 'client';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'username', 'password', 'contact_name', 'contact_phone', 'contact_email',
        'status',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function apps()
    {
        return $this->hasMany(ClientApp::class, 'client_id', 'id');
    }
}
