# ecat-tls

TLS configuration and certificate generation for e-cat data backends.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-tls
```

## Features

- `TlsClientConfig` for data backends (enabled flag + skip-verify)
- `build_reqwest_client` and `apply_basic_auth` helpers
- Certificate generation: `generate_ca` / `generate_server_cert`

## Usage

```rust
use ecat_tls::build_reqwest_client;

let client = build_reqwest_client(&tls_config)?;
```
