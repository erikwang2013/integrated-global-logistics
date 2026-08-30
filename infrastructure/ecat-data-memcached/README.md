# ecat-data-memcached

⚠️ **内存实现，仅用于开发/测试，禁止生产使用** / **IN-MEMORY FAKE IMPLEMENTATION — development/testing only, NOT for production.**

This crate does **not** implement the memcached network protocol and never
connects to a memcached server. It is a process-local in-memory `HashMap`
cache that implements the `Cache` trait from `ecat-data`:

- data is shared only within the current process;
- all data is lost when the process exits;
- no TTL propagation, no distributed behavior.

It exists to exercise the `Cache` trait locally during development and
testing. **Do not use it in production** — use a real memcached/redis client
before deployment.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-data-memcached
```

## Features

- Implements the `Cache` trait from `ecat-data`
- Process-local in-memory `HashMap` cache — no memcached network protocol, no TTL propagation
- Development / testing only; use a real memcached/redis client in production

## Usage

```rust
use std::time::Duration;
use ecat_data_memcached::MemcachedClient;

let cache = MemcachedClient::new();
cache.set("key", b"value", Duration::from_secs(60)).await?;
let value = cache.get("key").await?;
```
