# ecat-data-iotdb

Apache IoTDB time-series database client for e-cat (REST v2 API).

Writes use the REST v2 `insertTablet` endpoint: one tablet per `DataPoint`
with `device`, `timestamps`, `measurements`, `data_types` and a 2-D `values`
array. Tags are not representable in `insertTablet` and are ignored on write.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-data-iotdb
```

## Features

- Implements `TsdbClient` from `ecat-data`
- REST v2 API: `insertTablet` writes, one tablet per `DataPoint`
- `write` / `query` against Apache IoTDB

## Usage

```rust
use ecat_data_iotdb::{IotdbClient, IotdbConfig};
use ecat_data::{DataPoint, FieldValue};

let client = IotdbClient::from_config(IotdbConfig {
    base_url: "http://localhost:8080".into(),
    username: "root".into(),
    password: "root".into(),
    tls: None,
})?;

client.write(&[DataPoint {
    measurement: "sensor1".into(),
    timestamp: Some(1_700_000_000_000),
    tags: Default::default(),
    fields: vec![("temp".into(), FieldValue::Float(23.5))].into_iter().collect(),
}]).await?;
```
