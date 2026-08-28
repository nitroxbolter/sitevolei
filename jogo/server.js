const http = require('http');
const fs = require('fs');
const path = require('path');

const root = __dirname;
const port = Number(process.env.PORT || 8080);

const mimeTypes = {
    '.html': 'text/html; charset=utf-8',
    '.js': 'text/javascript; charset=utf-8',
    '.css': 'text/css; charset=utf-8',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.ico': 'image/x-icon',
    '.txt': 'text/plain; charset=utf-8'
};

function sendJson(response, status, payload) {
    response.writeHead(status, { 'Content-Type': 'application/json; charset=utf-8' });
    response.end(JSON.stringify(payload));
}

function saveLog(request, response) {
    let body = '';
    request.on('data', (chunk) => {
        body += chunk;
        if (body.length > 1024 * 1024) {
            request.destroy();
        }
    });

    request.on('end', () => {
        try {
            const data = JSON.parse(body || '{}');
            if (!data.text) {
                sendJson(response, 400, { ok: false, error: 'log vazio ou invalido' });
                return;
            }

            const filename = path.basename(String(data.filename || 'volei-log.txt'));
            const entry = [
                '==============================',
                `arquivo: ${filename}`,
                String(data.text),
                '',
                ''
            ].join('\n');

            fs.appendFileSync(path.join(root, 'log.txt'), entry, 'utf8');
            sendJson(response, 200, { ok: true, file: 'log.txt' });
        } catch (error) {
            sendJson(response, 500, { ok: false, error: error.message });
        }
    });
}

function serveFile(request, response) {
    const urlPath = decodeURIComponent(new URL(request.url, `http://localhost:${port}`).pathname);
    const relativePath = urlPath === '/' ? 'index.html' : urlPath.slice(1);
    const filePath = path.resolve(root, relativePath);

    if (!filePath.startsWith(root)) {
        response.writeHead(403);
        response.end('Forbidden');
        return;
    }

    fs.readFile(filePath, (error, contents) => {
        if (error) {
            response.writeHead(404);
            response.end('Not found');
            return;
        }

        response.writeHead(200, {
            'Content-Type': mimeTypes[path.extname(filePath).toLowerCase()] || 'application/octet-stream'
        });
        response.end(contents);
    });
}

const server = http.createServer((request, response) => {
    if (request.method === 'POST' && request.url.split('?')[0] === '/save_log.php') {
        saveLog(request, response);
        return;
    }

    if (request.method === 'GET' || request.method === 'HEAD') {
        serveFile(request, response);
        return;
    }

    response.writeHead(405);
    response.end('Method not allowed');
});

server.listen(port, '127.0.0.1', () => {
    console.log(`Jogo rodando em http://127.0.0.1:${port}/`);
});
