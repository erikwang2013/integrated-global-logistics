# ecat-registry

Service registry abstraction for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-registry
```

## Features

- `Registry` trait: `register` / `deregister` / `discover` / `list_services`
- `ServiceInfo` builder with name, version and endpoint
- `MemoryRegistry` for local development and tests

## Usage

```rust
use ecat_registry::{MemoryRegistry, ServiceInfo};

let registry = MemoryRegistry::new();
registry.register(ServiceInfo::new("auth", "1.0.0").with_endpoint("http://localhost:8080")).await?;
```
