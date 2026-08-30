# ecat-config-remote

Remote configuration source for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-config-remote
```

## Features

- `ConsulConfigSource` loads config from a Consul KV prefix
- `watch` for live configuration updates
- Composable with the `ConfigSource` trait from `ecat-config`

## Usage

```rust
use ecat_config_remote::ConsulConfigSource;

let source = ConsulConfigSource::new("http://localhost:8500", "app/config").token("dev-token");
```
