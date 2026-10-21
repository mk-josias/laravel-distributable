# Why

By the end of this page, you know the problem the package solves and what it lets you change later
without rewriting.

The package makes the module the unit and the deployment a setting. Each module is built like an
independent service, with its own data and its own contracts, no shared tables and no direct
calls, whether it runs with others in one process or alone in its own application.

## What the package keeps separate

Microservices give each part of a system its own data, its own contracts and its own deployment,
and that independence is what lets one part change, scale or move without the others. Existing
module tools organise the code, with routes, providers and migrations per module, but run all of it
in the same context: a module can use a neighbour's connections, configuration or services, and
nothing says no.

The package closes that door. A module's code runs in a context of its own, with only its
connections, configuration and services; `modules:doctor` fails your tests when a module imports a
neighbour's class or names its tables. What is left is the way modules are meant to talk:
messages, events and read-only copies.

## What justifies running a module apart

What justifies running a module apart is rarely the same for every module. It is an execution need:
the module has to scale alone, be deployed on its own schedule, or keep working when another
fails. Each of these assumes a condition: scaling apart assumes a module whose load is clearly
different, deploying apart a module that changes far more often than the rest. Where the condition
is missing, running apart only adds a server to run, deploy and watch.

Giving a module an application of its own is another decision: it answers needs of teams and
languages, and costs contracts versioned across repositories and the same plumbing written in
each. The package supports it without requiring it. A few modules run apart; most stay together,
in one process, with calls between them that never leave it.

## What you get

- **Cost follows load.** Group modules into as few processes as the traffic allows; give one its
  own when it earns its own servers.
- **Extraction is a deployment, not a rewrite.** A module already owns its data and speaks only
  through messages and events. Moving it to its own process or application changes a setting; the
  code that calls it, announces to it or copies its rows stays the same.
- **The same behaviour in every topology.** Events always travel on a stream, and a call is the same
  message whether the module answers here or elsewhere.
- **Boundaries you can test.** One repository, one language, one test suite, and `modules:doctor`
  fails the tests when a module crosses a boundary.

When modules share connections and call each other directly, splitting one out is a migration
project. Here it is a setting.

## Next

[The boundaries the package holds modules to](/understand/boundaries).
