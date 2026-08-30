# ecat-lock

Distributed lock abstraction for the e-cat ecosystem.

Implementations:

- [ecat-data-redis](https://github.com/erik/e-cat/tree/main/ecat-data-redis) — `RedisLock` (SET NX PX + token-checked release)

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-lock
```

## Features

- `DistributedLock` trait: `acquire(key, ttl) -> token` / `release(key, token)`
- Implemented by `ecat-data-redis` `RedisLock` (SET NX PX + token-checked release)

## Usage

```rust
use std::time::Duration;
use ecat_lock::DistributedLock;
use ecat_data_redis::RedisLock;

let lock = RedisLock::connect("redis://localhost:6379").await?;
if let Some(token) = lock.acquire("job-1", Duration::from_secs(30)).await? {
    // critical section
    lock.release("job-1", &token).await?;
}
```
