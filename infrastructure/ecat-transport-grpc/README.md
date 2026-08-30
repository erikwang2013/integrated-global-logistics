# ecat-transport-grpc

gRPC transport implementation for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-transport-grpc
```

## Features

- `GrpcServer` over tonic: `new(addr)` + `.routes(...)` + optional `.tls(...)`
- Implements the `Server` trait from `ecat-transport`

## Usage

```rust
use ecat_transport_grpc::GrpcServer;

let server = GrpcServer::new("0.0.0.0:9090").routes(routes);
server.start().await?;
```
