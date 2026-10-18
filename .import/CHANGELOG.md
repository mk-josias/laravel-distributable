# Changelog

Notable changes to the packages of this repository. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/): before 1.0, a minor version may break.

## [Unreleased]

### Added

- `MICROSERVICES_EVENTS_RELIABLE_PUBLISH`: events kept in `event_publications` until they are sent.
- `stream:export` and `stream:import` read and write the stream itself.
- `config/rpc-http.php`, published with `vendor:publish --tag=rpc-http-config`.

### Changed

- **Breaking:** `microservices.rpc.path`, `middleware_group` and `signature_ttl` move to
  `rpc-http.*`. `microservices.rpc.secret` and the environment variables stay.
- **Breaking:** `ShadowMigration::source()` becomes `shadow()`, which returns the copy's class.
- **Breaking:** the export format is the stream's entries.
- The RPC cache stores its answers as JSON.

### Removed

- **Breaking:** `stream:publish`; events are sent after the commit.
- **Breaking:** the `--since` option of `stream:republish`.
- **Breaking:** `Contracts\Stream\TracksAcknowledgements`.

[Unreleased]: https://github.com/mk-josias/laravel-microservices/compare/v0.2.0...HEAD
