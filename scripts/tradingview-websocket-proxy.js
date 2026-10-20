const { WebSocket, WebSocketServer } = require('ws');
const fs = require('fs');
const path = require('path');

const port = Number(process.env.TRADINGVIEW_PROXY_PORT || 8081);
const envPath = path.resolve(__dirname, '..', '.env');
let envAppUrl;

if (fs.existsSync(envPath)) {
    const envContents = fs.readFileSync(envPath, 'utf8');
    const appUrlLine = envContents.match(/^\s*APP_URL\s*=\s*(?:"([^"]*)"|'([^']*)'|([^#\r\n]*))/m);
    envAppUrl = appUrlLine && (appUrlLine[1] || appUrlLine[2] || appUrlLine[3].trim());
}

const appUrl = new URL(process.env.APP_URL || envAppUrl || 'http://127.0.0.1:8000');
const appPort = Number(process.env.APP_PORT || appUrl.port || (appUrl.protocol === 'https:' ? 443 : 80));
const upstreamUrl = 'wss://data.tradingview.com/socket.io/websocket';
const allowedOrigins = new Set(['127.0.0.1', 'localhost'].map((host) => {
    const origin = new URL(`${appUrl.protocol}//${host}`);
    origin.port = (appPort === 80 && appUrl.protocol === 'http:') ||
        (appPort === 443 && appUrl.protocol === 'https:') ? '' : String(appPort);
    return origin.origin;
}));

function closeSocket(socket, code, reason) {
    if (code === 1000 || (code >= 3000 && code <= 4999)) {
        socket.close(code, reason.toString().slice(0, 123));
    } else {
        socket.terminate();
    }
}

const server = new WebSocketServer({
    host: '127.0.0.1',
    port,
    verifyClient: ({ origin }, callback) => {
        callback(allowedOrigins.has(origin), 403, 'Local development origin required');
    },
});

server.on('listening', () => {
    console.log(`TradingView websocket relay listening on ws://127.0.0.1:${port}`);
});

server.on('connection', (client) => {
    const upstream = new WebSocket(upstreamUrl, {
        headers: {
            Origin: 'https://data.tradingview.com',
        },
    });

    client.on('message', (data, isBinary) => {
        if (upstream.readyState === WebSocket.OPEN) {
            upstream.send(data, { binary: isBinary });
        }
    });

    upstream.on('message', (data, isBinary) => {
        if (client.readyState === WebSocket.OPEN) {
            client.send(data, { binary: isBinary });
        }
    });

    client.on('close', (code, reason) => {
        if (upstream.readyState === WebSocket.OPEN) {
            closeSocket(upstream, code, reason);
        } else {
            upstream.terminate();
        }
    });

    upstream.on('close', (code, reason) => {
        console.error(`TradingView upstream closed (${code}): ${reason.toString()}`);
        if (client.readyState === WebSocket.OPEN) {
            closeSocket(client, code, reason);
        }
    });

    upstream.on('open', () => {
        console.log('TradingView upstream connected');
    });

    upstream.on('error', (error) => {
        console.error(`TradingView upstream websocket error: ${error.message}`);
        if (client.readyState === WebSocket.OPEN) {
            client.close(1011, 'TradingView upstream connection failed');
        }
    });
});

server.on('error', (error) => {
    console.error(`TradingView websocket relay failed: ${error.message}`);
    process.exitCode = 1;
});
