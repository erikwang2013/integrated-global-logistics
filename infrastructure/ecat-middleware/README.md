# ecat-middleware

Tower middleware (rate limiting, timeout) for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-middleware
```

## Features

- Rate limiting with in-memory or Redis stores (`RateLimitLayer`)
- Timeout, retry (`RetryLayer` with exponential backoff), recovery layers
- Logging and tracing layers; re-exports `tower_http` CORS

## Usage

```rust
use ecat_middleware::{CorsLayer, RateLimitLayer};

let app = Router::new()
    .layer(CorsLayer::permissive())
    .layer(RateLimitLayer::new(/* capacity, window, store */));
```
