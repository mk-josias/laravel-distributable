# Commands

Every command the package adds or changes, with its options.

`--module=<name>` is on every Artisan command, and always names one module: a generator writes in
it, any other command runs in its context. See
[Running a command in a module](/build/create#run-a-command-in-a-module).

## Modules

- `modules:install`: publishes `config/distributable.php` and creates the modules directory.
- `modules:make <name>`: creates a module and declares it.
- `modules:delete <name> [--force]`: deletes its folder and its declaration; its database is left
  as it is.
- `modules:list`: lists the modules and where each one runs.
- `modules:doctor`: checks that every module can run: declaration, provider, connections, remote
  hosts, boundaries. See [Checking the boundaries](/understand/boundaries).
- `modules:cache` and `modules:clear`: cache, or remove, what the module folders tell: namespaces,
  databases and copy sources.
- `modules:purge [--force]`: deletes the folder of every module this process doesn't run. See
  [One image per module](/run/deploying#one-image-per-module).
- `modules:unused-packages`: lists the Composer packages only those modules require. See
  [The dependencies of a module](/run/deploying#the-libraries-of-a-module).

## Events

- `stream:consume [--stream=]`: consumes events and runs the handlers of the modules that run here.
- `stream:republish`: sends the events stored in the database that could not be sent after their
  commit. See [Events](/talk/events#don-t-lose-an-event).
- `stream:trim`, `stream:prune-consumptions`, `stream:skip`, `stream:export` and `stream:import`
  keep the stream and its marks in order: [Operating the event stream](/run/streams).

## Copies

- `shadows:announce <source> [--keepers=*]`: announces every row of a source table to the modules
  keeping a copy of it.
- `shadows:want [--keepers=*] [--sources=*]`: asks the owners of the source tables copied here to
  announce their rows. See [Read-only copies](/talk/copies).

## Calls

- `rpc:serve [--transport=]`: answers the calls of a transport in a process of its own. See
  [Calls (RPC)](/talk/calls).

## Laravel's own commands

`make:*`, `migrate` and the rest of Laravel's commands are the same ones, made aware of modules.
Each takes `--module`: one module, the option repeated, or `*` for every module the process runs.
See [Generating code in a module](/build/create#generate-code-in-a-module) and
[Migrations](/build/migrations).
