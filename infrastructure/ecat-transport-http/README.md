# ecat-transport-http

HTTP/Axum transport implementation for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-transport-http
```

## Features

- `HttpServer` over axum: `new(addr)` + `.router(...)` + optional `.tls(...)`
- Implements the `Server` trait from `ecat-transport`

## Usage

```rust
use ecat_transport_http::HttpServer;

let server = HttpServer::new("0.0.0.0:8080").router(router);
server.start().await?;
```
