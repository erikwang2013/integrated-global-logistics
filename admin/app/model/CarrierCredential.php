<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\Encryptable\Encryptable;
use support\Model;

class CarrierCredential extends Model
{
    protected $table = 'carrier_credential';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'carrier_id', 'name', 'app_key', 'app_secret', 'extra', 'status', 'weight',
    ];

    protected $casts = [
        'status' => 'integer',
        'weight' => 'integer',
        'app_key' => Encryptable::class,
        'app_secret' => Encryptable::class,
        'extra' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function carrier()
    {
        return $this->belongsTo(Carrier::class, 'carrier_id', 'id');
    }
}
