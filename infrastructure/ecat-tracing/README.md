# ecat-tracing

Distributed tracing (OpenTelemetry) for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-tracing
```

## Features

- `init(service_name)` global tracing subscriber
- `TracingLayer` for tower / axum pipelines
- `extract_trace_id` / `inject_trace_id` header helpers

## Usage

```rust
use ecat_tracing::{init, extract_trace_id};

init("my-service");
let trace_id = extract_trace_id(&headers);
```
