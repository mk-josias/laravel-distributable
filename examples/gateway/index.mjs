// The only door clients knock on: it asks iam who holds the bearer token, then forwards the request
// with X-Identity, which the services trust without calling iam. RPC is described in
// packages/core/docs/other-languages.md.
import { createHmac, randomUUID } from 'node:crypto';
import { createServer } from 'node:http';

const PORT = Number(process.env.GATEWAY_PORT ?? 8000);
const IAM = process.env.IAM_HOST ?? 'http://127.0.0.1:8001';
const ROUTES = { '/iam/api': IAM, '/notifications/api': process.env.NOTIFICATIONS_HOST ?? 'http://127.0.0.1:8002' };
// No identity yet: forwarded as they are.
const PUBLIC = ['POST /iam/api/v1/login'];
const RPC_SECRET = process.env.DISTRIBUTABLE_RPC_SECRET;
const GATEWAY_SECRET = process.env.GATEWAY_SECRET;
const RPC_PATH = process.env.DISTRIBUTABLE_RPC_PATH ?? '{service}/rpc/{method}';
const IDENTITY_TTL = 60;

createServer(async (request, response) => {
    try {
        const prefix = Object.keys(ROUTES).find((path) => request.url.startsWith(path));

        if (!prefix) {
            response.writeHead(404).end();
            return;
        }

        const open = PUBLIC.includes(`${request.method} ${request.url}`);
        const token = /^Bearer (.+)$/.exec(request.headers.authorization ?? '')?.[1];
        const user = !open && token ? await rpc('iam', 'findUserByToken', 'Foundation\\Iam\\Contracts\\IamService', { token }) : null;

        if (!open && !user) {
            response.writeHead(401).end();
            return;
        }

        // The client's own X-Identity is never forwarded: only the one signed here.
        const upstream = await fetch(ROUTES[prefix] + request.url, {
            method: request.method,
            body: ['GET', 'HEAD'].includes(request.method) ? undefined : await read(request),
            headers: {
                Accept: 'application/json',
                'Content-Type': request.headers['content-type'] ?? 'application/json',
                'User-Agent': request.headers['user-agent'] ?? '',
                ...(request.headers['x-device-id'] ? { 'X-Device-Id': request.headers['x-device-id'] } : {}),
                ...(user ? { 'X-Identity': identity(user.id) } : {}),
            },
        });

        response.writeHead(upstream.status, { 'Content-Type': upstream.headers.get('content-type') ?? 'text/plain' });
        response.end(await upstream.text());
    } catch (error) {
        console.error(error);
        response.writeHead(502).end();
    }
}).listen(PORT, '127.0.0.1');

async function read(request) {
    const chunks = [];

    for await (const chunk of request) {
        chunks.push(chunk);
    }

    return Buffer.concat(chunks);
}

// The format of Foundation\Iam\Auth\TrustedIdentity: {claims as base64url JSON}.{hmac}.
function identity(id) {
    const payload = Buffer.from(JSON.stringify({ sub: id, exp: Math.floor(Date.now() / 1000) + IDENTITY_TTL })).toString('base64url');

    return `${payload}.` + createHmac('sha256', GATEWAY_SECRET).update(payload).digest('hex');
}

async function rpc(service, method, contract, args) {
    const path = '/' + RPC_PATH.replace('{service}', service).replace('{method}', method);
    const body = JSON.stringify({ contract, arguments: args });
    const timestamp = String(Math.floor(Date.now() / 1000));
    const nonce = randomUUID();
    const context = '{}';
    const signature = createHmac('sha256', RPC_SECRET).update([timestamp, nonce, path, body, context].join('\n')).digest('hex');

    const response = await fetch(`${IAM}${path}`, {
        method: 'POST',
        body,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Rpc-Timestamp': timestamp,
            'X-Rpc-Nonce': nonce,
            'X-Rpc-Context': context,
            'X-Rpc-Signature': signature,
        },
    });

    if (response.status === 404) {
        return null;                                                   // the method returned null
    }

    if (!response.ok) {
        throw new Error(`${path} answered ${response.status}: ${await response.text()}`);
    }

    return response.json();
}
