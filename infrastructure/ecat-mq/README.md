# ecat-mq

Message queue abstraction for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-mq
```

## Features

- `MessageQueue` trait: `publish(topic, payload)` / `subscribe(topic)`
- `MessageStream` pull-based stream of `Bytes` payloads
- `InMemoryMq` for local development and tests

## Usage

```rust
use ecat_mq::{InMemoryMq, MessageQueue};

let mq = InMemoryMq::new();
mq.publish("orders.created", b"{\"id\": 42}").await?;
let mut stream = mq.subscribe("orders.created").await?;
```
