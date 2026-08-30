# ecat-registry-etcd

etcd service registry backend for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-registry-etcd
```

## Features

- `EtcdRegistry`, a `Registry` backend backed by etcd
- `new(endpoints, prefix)` + `.lease_ttl(...)` for auto-expiring registrations

## Usage

```rust
use ecat_registry_etcd::EtcdRegistry;

let registry = EtcdRegistry::new(vec!["http://localhost:2379".into()], "services").lease_ttl(30);
let services = registry.discover("auth").await?;
```
