# ecat-registry-consul

Consul service registry backend for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-registry-consul
```

## Features

- `ConsulRegistry`, a `Registry` backend backed by Consul
- `new(base_url)` + `.datacenter(...)` / `.token(...)` builder options

## Usage

```rust
use ecat_registry::ServiceInfo;
use ecat_registry_consul::ConsulRegistry;

let registry = ConsulRegistry::new("http://localhost:8500")?.datacenter("dc1");
registry.register(ServiceInfo::new("auth", "1.0.0").with_endpoint("http://localhost:8080")).await?;
```
