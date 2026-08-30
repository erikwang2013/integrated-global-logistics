# ecat-transport

Transport layer abstraction for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-transport
```

## Features

- `Server` trait: `start()` / `stop()` for HTTP, gRPC and WebSocket servers
- `TlsConfig` with optional mutual TLS (client auth)
- `normalize_addr` for consistent address handling

## Usage

```rust
use ecat_transport::{Server, normalize_addr};

let addr = normalize_addr(":8080".into());
// HttpServer / GrpcServer / WsServer all implement Server
server.start().await?;
```
