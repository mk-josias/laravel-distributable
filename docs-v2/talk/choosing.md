# Call, event or copy?

By the end of this page, you know which of the three ways to use when a module needs something
from another, and what each costs you.

A module never reaches into another module's classes, tables or connections. It has three ways
across, and the choice depends on what it needs.

```
need the answer now, to go on                       → a call
announce a fact; whoever cares reacts later          → an event
read another module's rows next to your own          → a copy (kept up to date by events)
```

## A call

**Use it** to check one value or read one record, when your code can't go on without the answer.
In the starter, every module asks `iam` what a user may do through the message `Grants`
(`RpcPermissions` calls `IamClient::grants()`).

- It is always current.
- It costs a call on the transport when the other module runs elsewhere, and nothing but a method
  call when it runs here.
- It is not part of your transaction: if your code fails afterwards, what the other module did
  stays done.

[Calling another module](/talk/calls).

## An event

**Use it** to announce that something happened, when you don't need a result and don't know who
reacts. In the starter, `iam` announces `iam.user.registered`; `analytics` records a signup and
`notifications` stores a welcome.

- The announcer doesn't wait and doesn't know who handles it.
- It leaves once your transaction commits, and never if it rolls back.
- Events travel on a stream even when the modules run in the same process, so they behave the same
  in every setup.

[Publishing and handling events](/talk/events).

## A copy

**Use it** to list, filter, sort or join on another module's rows. In the starter, `analytics`
reads each signup's user from its copy of `iam`'s users (`Signup::user()` is a `belongsTo` on
`UserShadow`).

- It is a local table, so the query joins like any other.
- It is current once the consumer has handled the events that updated it.
- Only the owner writes the data: the module that keeps the copy reads it.

A copy is made of events: the owner announces each row it saves or deletes, and the package writes
it into every module that keeps the copy. [Keeping a copy](/talk/copies).

## When two fit

- **Checking one record that changes often, or must be exact:** call.
- **Showing a list with another module's columns:** copy.
- **Triggering work in another module:** event. If you need to know it worked, call.

## Next

Before writing a message, an event or a copy, read [what modules share](/talk/foundation): all
three keep their classes there.
