// Serveur local rapide : sert les fichiers statiques (build, images, polices, uploads) directement, et ne
// passe à PHP que les pages. Le serveur intégré de PHP traite une requête à la fois et, sous Windows, fait
// échouer le chargement des pages quand le navigateur demande beaucoup de fichiers en parallèle.
//
//   npm run local      →  http://localhost:8000   (PHP écoute sur 127.0.0.1:8001, interne)
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', 'public');
const PORT = Number(process.env.PORT || 8000);
const PHP_PORT = Number(process.env.PHP_PORT || 8001);

const TYPES = {
    '.js': 'text/javascript; charset=utf-8', '.mjs': 'text/javascript; charset=utf-8', '.css': 'text/css; charset=utf-8',
    '.json': 'application/json', '.svg': 'image/svg+xml', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg',
    '.webp': 'image/webp', '.gif': 'image/gif', '.ico': 'image/x-icon', '.woff2': 'font/woff2', '.woff': 'font/woff',
    '.pdf': 'application/pdf', '.txt': 'text/plain; charset=utf-8', '.map': 'application/json', '.mp4': 'video/mp4',
};

function staticFile(urlPath) {
    let rel;
    try { rel = decodeURIComponent(urlPath); } catch { return null; }
    if (rel.includes('\0')) return null;

    const file = path.join(root, rel);
    const ext = path.extname(file).toLowerCase();

    // Jamais de PHP ni de fichier caché ; le fichier doit rester dans public/.
    if (!file.startsWith(root + path.sep) || ext === '.php' || path.basename(file).startsWith('.')) return null;

    try {
        return fs.statSync(file).isFile() ? { file, ext } : null;
    } catch {
        return null;
    }
}

const noKeepAlive = new http.Agent({ keepAlive: false });

http.createServer((req, res) => {
    const url = new URL(req.url, 'http://localhost');
    const found = req.method === 'GET' || req.method === 'HEAD' ? staticFile(url.pathname) : null;

    if (found) {
        const stat = fs.statSync(found.file);
        res.writeHead(200, {
            'Content-Type': TYPES[found.ext] || 'application/octet-stream',
            'Content-Length': stat.size,
            'Cache-Control': url.pathname.startsWith('/build/') ? 'public, max-age=3600' : 'no-cache',
        });
        if (req.method === 'HEAD') return res.end();
        return fs.createReadStream(found.file).pipe(res);
    }

    const upstream = http.request(
        { host: '127.0.0.1', port: PHP_PORT, method: req.method, path: req.url, headers: { ...req.headers, connection: 'close' }, agent: noKeepAlive },
        (php) => {
            res.writeHead(php.statusCode, php.headers);
            php.pipe(res);
        },
    );
    upstream.on('error', () => {
        res.writeHead(502, { 'Content-Type': 'text/plain; charset=utf-8' });
        res.end(`PHP ne répond pas sur 127.0.0.1:${PHP_PORT}. Lancez « npm run local » (ou php artisan serve --port=${PHP_PORT}).`);
    });
    req.pipe(upstream);
}).listen(PORT, '127.0.0.1', () => console.log(`CAMA : http://localhost:${PORT} (PHP interne sur ${PHP_PORT})`));
