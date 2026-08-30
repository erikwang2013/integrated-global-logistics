# ecat-transport-ws

WebSocket transport implementation for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-transport-ws
```

## Features

- `WsServer` over axum WebSockets: `new(addr)` + `.path(...)` + `.handler(...)`
- Configurable max message size
- Built-in `echo_handler` for quick testing

## Usage

```rust
use ecat_transport_ws::{WsServer, echo_handler};

let server = WsServer::new("0.0.0.0:8080").path("/ws").handler(echo_handler());
server.start().await?;
```
