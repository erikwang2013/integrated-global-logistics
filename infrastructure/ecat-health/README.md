# ecat-health

Health check endpoint and registry for e-cat services.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-health
```

## Features

- `HealthCheck` trait with `HealthRegistry` and `FnCheck` helpers
- `into_router()` serves a health endpoint on the axum router

## Usage

```rust
use ecat_health::{HealthRegistry, FnCheck};

let router = HealthRegistry::new()
    .with_check(FnCheck::new("db", check_fn))
    .into_router();
```
