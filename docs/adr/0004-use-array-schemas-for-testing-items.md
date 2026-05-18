# Use Array Schemas For Testing Items

Date: 2026-05-18

Products will register Scenarios and Utilities with WordPress-style array schemas instead of required PHP contract classes. This makes product-side adoption faster, keeps the extension API familiar to WordPress developers, and maps cleanly to the Dashboard boot data consumed by React islands. Behavior is attached through named callbacks such as `apply`, `inspect`, `run`, `mutate`, and `restore`.

## Considered Options

- Class contracts: stronger IDE support and clearer behavior boundaries, but heavier for product plugins to author.
- Array schemas with callbacks: more WordPress-native and easier to register, but requires strong validation and documentation.
- Supporting both: flexible, but doubles the public API before the first stable version.
