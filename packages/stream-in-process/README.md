# In-process Event Stream

The `in-process` events driver of [laravel-distributable](https://github.com/mk-josias/laravel-distributable/tree/main/packages/core), with no broker.

```bash
composer require mk-josias/laravel-distributable-stream-in-process
```

Its provider is discovered and registers the driver. The shipped `default` stream uses it once
`DISTRIBUTABLE_STREAM_DRIVER=in-process`.

Each event goes to the handlers of the modules the process runs, once the emitter's transaction
commits, with no `stream:consume`. A module another process runs never gets it: when modules run
apart, use a driver that crosses processes, such as `redis`.

A handler that implements `ShouldQueue` still runs on the queue, as with any driver.
