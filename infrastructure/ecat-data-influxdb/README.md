# ecat-data-influxdb

InfluxDB time-series database client for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-data-influxdb
```

## Features

- Implements the `TsdbClient` trait from `ecat-data`
- `write` batches of `DataPoint`s; `query` in Flux or InfluxQL
- `from_config` with org / bucket / token

## Usage

```rust
use ecat_data_influxdb::{InfluxClient, InfluxConfig};

let client = InfluxClient::from_config(InfluxConfig {
    base_url: "http://localhost:8086".into(),
    org: "myorg".into(),
    bucket: "metrics".into(),
    token: "my-token".into(),
    tls: None,
})?;
let result = client.query("SELECT * FROM cpu").await?;
```
