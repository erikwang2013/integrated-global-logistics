# ecat-data-redis

Redis cache client for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-data-redis
```

## Features

- Implements the `Cache` trait from `ecat-data`
- `connect` / `connect_with_password` / `from_config`, with TLS (`rediss://`) support
- `RedisLock` distributed lock (SET NX PX + token-checked release)

## Usage

```rust
use std::time::Duration;
use ecat_data_redis::RedisCache;

let cache = RedisCache::connect("redis://localhost:6379").await?;
cache.set("key", b"value", Duration::from_secs(60)).await?;
let value = cache.get("key").await?;
```
