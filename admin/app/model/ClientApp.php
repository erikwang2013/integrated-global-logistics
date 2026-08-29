<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class ClientApp extends Model
{
    protected $table = 'logistics_client_app';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'client_id', 'appid', 'name', 'purpose', 'api_key_sha256',
        'plan_id', 'valid_days', 'expire_at', 'review_remark',
        'reviewed_by', 'reviewed_at', 'status',
    ];

    protected $hidden = ['api_key_sha256'];

    protected $casts = [
        'plan_id' => 'integer',
        'valid_days' => 'integer',
        'reviewed_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id', 'id');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'id');
    }
}
