<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class Carrier extends Model
{
    protected $table = 'carrier';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'code', 'name', 'channel', 'country', 'logo',
        'status', 'timeout_ms', 'cache_ttl', 'sort', 'remark',
    ];

    protected $casts = [
        'status' => 'integer',
        'timeout_ms' => 'integer',
        'cache_ttl' => 'integer',
        'sort' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function credentials()
    {
        return $this->hasMany(CarrierCredential::class, 'carrier_id', 'id');
    }
}
