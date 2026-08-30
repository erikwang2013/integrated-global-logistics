# ecat-circuit-breaker

Circuit breaker middleware for e-cat services.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-circuit-breaker
```

## Features

- Tower `CircuitBreakerLayer` middleware
- Configurable failure ratio, sliding window, half-open probes and open duration
- `classify` maps service errors to success / failure

## Usage

```rust
use std::time::Duration;
use ecat_circuit_breaker::CircuitBreakerLayer;

let layer = CircuitBreakerLayer::new()
    .failure_ratio(0.5)
    .window(Duration::from_secs(60))
    .open_duration(Duration::from_secs(30));
```
