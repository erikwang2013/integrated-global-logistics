# ecat-metadata

Request/response metadata propagation for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-metadata
```

## Features

- `Metadata` key-value propagation for requests / responses
- `trace_id` helpers for distributed tracing correlation

## Usage

```rust
use ecat_metadata::Metadata;

let mut meta = Metadata::new();
meta.set("request_id", "abc123");
let trace_id = meta.trace_id();
```
