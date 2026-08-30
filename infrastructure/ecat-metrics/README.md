# ecat-metrics

Prometheus metrics integration for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-metrics
```

## Features

- Prometheus metrics registry (`registry()`)
- `metrics_text()` renders the exposition format for scraping
- `metrics_router()` serves metrics on an axum router

## Usage

```rust
use ecat_metrics::{registry, metrics_text};

let text = metrics_text(); // Prometheus exposition format
```
