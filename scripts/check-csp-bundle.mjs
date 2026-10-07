/**
 * Fail the build if production assets contain CSP-unsafe eval patterns.
 * Run after `vite build`.
 */
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

const root = join(process.cwd(), 'public', 'build', 'assets');
const banned = [/new\s+Function\s*\(/, /\beval\s*\(/];

function walk(dir) {
    const out = [];
    for (const name of readdirSync(dir)) {
        const full = join(dir, name);
        if (statSync(full).isDirectory()) {
            out.push(...walk(full));
        } else if (/\.(js|mjs|cjs)$/.test(name)) {
            out.push(full);
        }
    }
    return out;
}

let failed = false;
for (const file of walk(root)) {
    const text = readFileSync(file, 'utf8');
    for (const pattern of banned) {
        if (pattern.test(text)) {
            console.error(`CSP unsafe pattern ${pattern} found in ${file}`);
            failed = true;
        }
    }
}

if (failed) {
    console.error('CSP bundle check failed: remove new Function()/eval() from the production build.');
    process.exit(1);
}

console.log('CSP bundle check passed (no new Function/eval in public/build/assets).');
