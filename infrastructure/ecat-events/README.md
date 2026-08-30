# ecat-events

Event bus abstraction for e-cat.

Part of the [e-cat](https://github.com/erik/e-cat) ecosystem.

## Installation

```bash
cargo add ecat-events
```

## Features

- Typed event bus: `publish<E: Serialize>` / `subscribe<E: DeserializeOwned>`
- `EventBus::local()` for in-process events; `EventBus::remote(mq)` over any `MessageQueue`
- Event name derived from the Rust type name

## Usage

```rust
use ecat_events::EventBus;

let bus = EventBus::local();
bus.subscribe::<MyEvent, _, _>(|event| async move {
    println!("got {event:?}");
}).await?;
bus.publish(&MyEvent { id: 42 }).await?;
```
