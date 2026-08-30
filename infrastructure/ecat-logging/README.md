# ecat-logging

Structured logging via tracing for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-logging
```

## Features

- `init()` sets up structured logging via `tracing`
- Drop-in for services that need a logging subscriber without tracing spans

## Usage

```rust
use ecat_logging::init;

init();
tracing::info!("service started");
```
