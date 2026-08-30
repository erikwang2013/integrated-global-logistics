# ecat-mq-kafka

Kafka message queue backend for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-mq-kafka
```

## Features

- Implements `MessageQueue` from `ecat-mq`
- `connect(brokers)` or `from_config` with Kafka client options
- Publish / subscribe with consumer group support

## Usage

```rust
use ecat_mq_kafka::KafkaMq;

let mq = KafkaMq::connect("localhost:9092").await?;
mq.publish("orders.created", b"{\"id\": 42}").await?;
```
