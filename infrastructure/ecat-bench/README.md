# ecat-bench

Benchmarking utilities for e-cat services.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-bench
```

## Features

- `BenchResult` with avg / p50 / p95 / p99 latency and throughput (RPS)
- `print()` renders a human-readable summary

## Usage

```rust
use ecat_bench::BenchResult;

let result = /* run your benchmark, fill the BenchResult fields */;
result.print();
```
