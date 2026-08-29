<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\Encryptable\Encryptable;
use support\Model;

class CdnProvider extends Model
{
    protected $table = 'cdn_provider';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'code', 'name', 'access_key', 'access_secret', 'extra', 'domains', 'status', 'sort', 'remark',
    ];

    protected $casts = [
        'status' => 'integer',
        'sort' => 'integer',
        'access_key' => Encryptable::class,
        'access_secret' => Encryptable::class,
        'extra' => 'json',
        'domains' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
