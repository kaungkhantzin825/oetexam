/**
 * scripts/lock-check.mjs
 * ---------------------------------------------------------------------------
 * Guard: `package-lock.json` must be complete and in sync with `package.json`.
 *
 * Why this exists. An npm install that is interrupted (or run with a broken
 * Node) can leave the lockfile *half written*: every entry survives, but the
 * ones npm never finished resolving lose their `version`, `resolved` and
 * `integrity`. The result still installs happily on the machine that produced
 * it and then explodes on the deployment server with a message that names
 * nothing:
 *
 *     npm error Invalid Version:
 *
 * The empty string after the colon is the hollowed-out entry. npm resolves a
 * real version for e.g. @esbuild/linux-x64, then tries to dedupe it against
 * the versionless stub already in the tree, and `semver.gte('', …)` throws
 * inside `Node.canDedupe`. It only bites on a *different* platform than the
 * one that wrote the lock, which is exactly the dev-on-Windows / deploy-on-
 * Linux case — so it never shows up until deployment.
 *
 * Run with:  npm run check:lock
 */
import { readFileSync, existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const lockPath = join(root, 'package-lock.json');
const pkgPath = join(root, 'package.json');

const FIX = [
    'Regenerate it in a clean checkout (npm skips hollow entries it cannot',
    'resolve, so do not run this with a node_modules/ present):',
    '',
    '    rm -rf package-lock.json node_modules',
    '    npm install --package-lock-only',
    '',
    'Then commit the result. On the server:  rm -rf node_modules && npm install',
].join('\n    ');

const problems = [];

if (!existsSync(lockPath)) {
    console.error('package-lock.json is missing — the server has nothing to install from.');
    process.exit(1);
}

const lock = JSON.parse(readFileSync(lockPath, 'utf8'));
const pkg = JSON.parse(readFileSync(pkgPath, 'utf8'));

if (!lock.packages) {
    problems.push(
        `lockfileVersion ${lock.lockfileVersion} has no "packages" map — this is a v1 lockfile.\n    ` + FIX
    );
}

// 1. Every entry must carry a version. A missing one is the hollow-entry bug.
const hollow = [];
for (const [path, entry] of Object.entries(lock.packages ?? {})) {
    if (path === '' || entry.link) continue; // the root, and symlinked workspaces, legitimately have none
    if (!entry.version) hollow.push(path);
}
if (hollow.length) {
    problems.push(
        `${hollow.length} lockfile entr${hollow.length === 1 ? 'y has' : 'ies have'} no version ` +
            `(npm never finished resolving them):\n    ${hollow.slice(0, 8).join('\n    ')}` +
            (hollow.length > 8 ? `\n    … and ${hollow.length - 8} more` : '') +
            `\n\n    This is what produces "npm error Invalid Version:" on the server.\n    ` +
            FIX
    );
}

// 2. Platform-specific binaries must all be present, not just the local one.
//    npm writes every platform into the lock (tagged with os/cpu) so the same
//    file works on the dev machine and the Linux server alike.
const NATIVE = /^(@esbuild\/|@rollup\/rollup-|@napi-rs\/lzma-)/;
const missingPlatforms = Object.entries(lock.packages ?? {})
    .filter(([path, entry]) => NATIVE.test(path.split('node_modules/').pop() ?? '') && !entry.version)
    .map(([path]) => path);
if (missingPlatforms.length) {
    problems.push(
        `${missingPlatforms.length} platform binary package(s) have no version — the lock was ` +
            `written for one platform only:\n    ${missingPlatforms.join('\n    ')}\n    ` +
            FIX
    );
}

// 3. The lock's root entry must declare the same ranges as package.json, or
//    `npm ci` on the server refuses to run ("lock file's X does not satisfy Y").
const lockRoot = lock.packages?.[''] ?? {};
for (const field of ['dependencies', 'devDependencies']) {
    const declared = pkg[field] ?? {};
    const locked = lockRoot[field] ?? {};
    const drift = [];
    for (const name of new Set([...Object.keys(declared), ...Object.keys(locked)])) {
        if (declared[name] !== locked[name]) {
            drift.push(`${name}: package.json ${declared[name] ?? '(absent)'} vs lock ${locked[name] ?? '(absent)'}`);
        }
    }
    if (drift.length) {
        problems.push(
            `package-lock.json is out of sync with package.json (${field}):\n    ${drift.join('\n    ')}\n    ` +
                FIX
        );
    }
}

const entryCount = Object.keys(lock.packages ?? {}).length;
console.log(
    `package-lock.json v${lock.lockfileVersion} · ${entryCount} entries · ` +
        `${hollow.length} hollow · ${missingPlatforms.length} platform gaps`
);

if (problems.length) {
    console.error('\n' + problems.map((p) => '• ' + p).join('\n\n') + '\n');
    console.error('Lockfile check failed.');
    process.exit(1);
}

console.log('Lockfile is complete and in sync.');
