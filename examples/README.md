# Example: three applications, their foundation and a gateway

```
 client ── POST /iam/api/v1/login ──► gateway/ (Node, :8000) ──────────────────────► iam/ (:8001)
                                                                                      a Sanctum token per device
 client ── Bearer token ──► gateway/ ── X-Identity: {claims}.{hmac} ──► notifications/ (:8002)
                              │ RPC FindUserByToken                     GET /notifications/api/v1/notifications
                              ▼
 iam/ ── users:register ──► stream ──► notifications/ ──► stream ──► analytics/
   User, source of        shadow.changed   copy of users;  mail.sent   RecordSignup, RecordMail: journals
   UserShadow             user.registered  SendWelcome                 analytics:compute: hourly datasets
```

```
foundation/   example/foundation, required by the three: what they share
              Iam/Rpc/FindUserByToken  Iam/Shadows/UserShadow  Iam/Auth/TrustedIdentity
              Iam/Events/UserRegistered  Notifications/Events/MailSent
```

```bash
./run.sh   # needs PHP 8.4, Composer, Node 18+ and Redis on localhost
```

It installs the three applications, starts iam and notifications, both consumers and the gateway,
then registers a user in iam, who logs in through the gateway. Notifications mails a welcome to the
address in its own copy of the user; analytics journals the registration and the mail, then
computes its hourly datasets; the client
reads its inbox through the gateway, which turns a wrong token away with a 401.

```
inbox: ["welcome"]
signups: 1, mails: 1
```
