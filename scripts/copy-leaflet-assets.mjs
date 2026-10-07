import { cpSync, existsSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const src = join(root, 'node_modules', 'leaflet', 'dist');
const dest = join(root, 'public', 'vendor', 'leaflet');

if (!existsSync(join(src, 'leaflet.js'))) {
    console.warn('leaflet not installed; skip copy');
    process.exit(0);
}

mkdirSync(join(dest, 'images'), { recursive: true });
cpSync(join(src, 'leaflet.js'), join(dest, 'leaflet.js'));
cpSync(join(src, 'leaflet.css'), join(dest, 'leaflet.css'));
cpSync(join(src, 'images'), join(dest, 'images'), { recursive: true });
console.log('Copied Leaflet assets to public/vendor/leaflet');
