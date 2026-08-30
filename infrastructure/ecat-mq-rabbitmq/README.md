# ecat-mq-rabbitmq

[RabbitMQ](https://www.rabbitmq.com) message queue backend for the e-cat ecosystem, powered by [lapin](https://crates.io/crates/lapin).

```rust
let mq = RabbitmqMq::from_config(RabbitmqConfig {
    url: "amqp://guest:guest@localhost:5672".into(),
    exchange: Some("events".into()),
})
.await?;

mq.publish("orders.created", b"{\"id\": 42}").await?;
let mut stream = mq.subscribe("orders.created").await?;
```

Implements `MessageQueue` from `ecat-mq`.

**Notes:** subscribe declares the queue if it does not exist and consumes with auto-ack; publish uses the configured exchange (default exchange, routing key = topic).

## Installation

```bash
cargo add ecat-mq-rabbitmq
```

## Features

- Implements `MessageQueue` from `ecat-mq`
- Publish via the configured exchange (default exchange, routing key = topic)
- Queue auto-declared on subscribe; consumes with auto-ack
