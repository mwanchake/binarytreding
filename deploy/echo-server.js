const fs = require('fs');
const { spawn } = require('child_process');

const redisUrl = new URL(process.env.REDIS_URL);
const configuredAppUrl = process.env.APP_URL;
if (!configuredAppUrl) {
    throw new Error('APP_URL must be set for Echo private-channel authentication.');
}
const appUrl = new URL(configuredAppUrl).origin;

const redisConfig = {
    host: redisUrl.hostname,
    port: Number(redisUrl.port || (redisUrl.protocol === 'rediss:' ? 6380 : 6379)),
    db: Number(redisUrl.pathname.slice(1) || 0),
};

if (redisUrl.username) {
    redisConfig.username = decodeURIComponent(redisUrl.username);
}
if (redisUrl.password) {
    redisConfig.password = decodeURIComponent(redisUrl.password);
}
if (redisUrl.protocol === 'rediss:') {
    redisConfig.tls = {};
}

const configuration = {
    authHost: appUrl,
    authEndpoint: '/broadcasting/auth',
    clients: [],
    database: 'redis',
    databaseConfig: { redis: redisConfig },
    devMode: false,
    host: '0.0.0.0',
    port: Number(process.env.PORT || 10000),
    protocol: 'http',
    socketio: {},
    apiOriginAllow: {
        allowCors: true,
        allowOrigin: appUrl,
        allowMethods: 'GET, POST',
        allowHeaders: 'Origin, Content-Type, X-Auth-Token, X-Requested-With, Accept, Authorization, X-CSRF-TOKEN, X-Socket-Id',
    },
};

const configPath = '/tmp/laravel-echo-server.json';
fs.writeFileSync(configPath, JSON.stringify(configuration));

const server = spawn(
    './node_modules/.bin/laravel-echo-server',
    ['start', '--config', configPath],
    { stdio: 'inherit' }
);

let shuttingDown = false;
for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => {
        shuttingDown = true;
        server.kill(signal);
    });
}

server.on('error', (error) => {
    console.error('Failed to start Laravel Echo Server.', error);
    process.exit(1);
});

server.on('exit', (code, signal) => {
    process.exit(shuttingDown ? 0 : (code === null || signal ? 1 : code));
});
