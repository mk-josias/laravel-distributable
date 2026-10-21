# Laravel Distributable

laravel-distributable provides the foundation for distributable modules: autonomous areas, each a
business domain (payments, messaging) or a supporting domain the others rely on (identity, notifications), with
a boundary of its own, built like microservices. Grouped in one application,
spread over several, or one per application: it makes no difference to the package. It brings what
this model requires, a separate context for each module, and the code of the modules is the same
in every case.

Each module runs in an execution context of its own: its own connections (the database among
them), its own configuration and its own services (cache, mailer, disks). While its code runs,
everything it reads comes from that context.

A module reaches the others only through contracts, never through their code: it sends a message
that another module answers, or it publishes an event that the others handle in their own time. When it needs another module's data to
filter or join with its own, it keeps a copy of those rows; the package updates it from the other
module's events, and the module only reads it.

You start with everything in one application. When a module has to scale or ship on its own, you
deploy it in a process of its own, with only its code, and nothing that calls it changes.

## Choose your way in

- **See it run:** [try the starter](/start/starter), an application of three modules that you can
  read, run and replace.
- **Start from nothing:** [a new application](/start/new-app).
- **You already have a Laravel application:** [add the package to it](/start/existing-app).
- **Understand the idea first:** [what a module is](/understand/module), then
  [why](/understand/why).

## Install

```bash
composer require mk-josias/laravel-distributable
# the events driver: Redis Streams (or yours); DISTRIBUTABLE_STREAM_DRIVER=redis
composer require mk-josias/laravel-distributable-stream-redis
# calls to a module running in another process
composer require mk-josias/laravel-distributable-rpc-http
# config/distributable.php and apps/
php artisan modules:install
# apps/Iam, declared in config/distributable.php
php artisan modules:make iam
```

Requires PHP 8.4+ and Laravel 12 or 13. The events driver and the calls transport are packages of
their own; nothing else is a runtime dependency.

## What the package brings

- **A context per module.** Connections, configuration and services follow the module that runs.
  [Settings](/build/settings), [services](/build/services).
- **Boundaries that hold.** A module that names a neighbour's classes, connections or tables fails
  your tests. [The boundaries](/understand/boundaries).
- **Calls through messages.** The same message whether the module answers here or elsewhere.
  [Calling another module](/talk/calls).
- **Events that are not lost.** Sent once the transaction commits, optionally kept until sent,
  handled once. [Events](/talk/events).
- **Copies of another module's rows,** kept up to date by its events. [Copies](/talk/copies).
- **Any broker, any language.** Drivers for the transport, a plain JSON format on the wire.
  [Programs in other languages](/talk/other-languages), [Writing a driver](/extend/driver).
- **An image per module.** The code of the modules a process doesn't run, and the packages only
  they need, leave the image. [Deploying](/run/deploying).

## What the package leaves to you

The package imposes none of the application's own code (response formats, DTOs, repositories,
exceptions, authentication). The starter shows one worked example of each, and the modules most
applications need (identity, notifications, analytics): keep them, change them or delete them. A feature is in the package when getting it wrong would break one of
its guarantees: module code running outside its module's context would use the wrong connection,
so the context is part of the package; a module without a repository breaks nothing, so
repositories are not. Out of scope: turning modules on or off at runtime, a lockfile per module
(one process loads one version of a library), orchestration, and enforcing a code style.

## License

MIT.
