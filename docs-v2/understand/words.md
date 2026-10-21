# The words used

- **Module:** an area of the application with a boundary of its own, a business domain (orders,
  billing) or a supporting domain the others rely on (identity, notifications): its code, its
  routes, its context. `iam`, `analytics`, `notifications`.
- **Context:** what a module's code reads while it runs: its connections, its configuration and its
  services (cache, mailer, disks). [Settings](/build/settings), [services](/build/services).
- **Foundation:** the classes every module can load, where modules agree on what they exchange.
  [What modules share](/talk/foundation).
- **Message:** a call from one module to another, a `readonly` class of the foundation with the
  rules that validate it. [Calls](/talk/calls).
- **Handler:** the class a module writes to answer a message (`$rpcHandlers`) or to react to an
  event (`$streamHandlers`).
- **Event:** a fact a module announces; its payload class is in the foundation.
  [Events](/talk/events).
- **Copy:** a read-only table of another module's rows, kept up to date by that module's events.
  [Copies](/talk/copies).
- **Stream:** where events travel between modules, in one process or several.
  [Operating the event stream](/run/streams).
- **Transport:** how calls reach a module that runs elsewhere (`http` ships).
  [HTTP RPC](/reference/rpc-http).
- **Driver:** a package that provides a transport, a stream or an archive store.
  [Writing a driver](/extend/driver).
- **Envelope:** the JSON an event travels in. [On the wire](/talk/events#on-the-wire).
