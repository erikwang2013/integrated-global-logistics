# ecat-errors

Unified error types for e-cat services.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-errors
```

## Features

- Unified `Error` with `ErrorCode` (shared via `ecat-protos`)
- `new(code, reason, message)` with optional metadata (`with_metadata`)
- `to_http_status` / `from_status` bridge to HTTP and tonic/gRPC

## Usage

```rust
use ecat_errors::Error;

let err = Error::new(code, "users", "user not found"); // code: ErrorCode from ecat_protos
let status = err.to_http_status();
```
