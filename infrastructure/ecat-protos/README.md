# ecat-protos

Protobuf definitions for e-cat gRPC services.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-protos
```

## Features

- Shared protobuf definitions for e-cat gRPC services
- `errors` module — `ErrorCode` used by `ecat-errors`
- `metadata` module — trace / request metadata propagation

## Usage

```rust
use ecat_protos::errors::ErrorCode;
use ecat_protos::metadata::TraceMetadata;
```
