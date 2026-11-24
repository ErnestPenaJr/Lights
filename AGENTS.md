# Repository Guidelines

## Project Structure & Module Organization

This dependency-free PHP 8.2 app controls Hao Deng mesh lights through the Magic Hue cloud and WiFi bridge.

- `index.php`: mobile-first UI with inline CSS, JavaScript, and SVG assets.
- `haodeng.php`: cloud client, MQTT implementation, and pure protocol helpers.
- `api.php` and `hb.php`: JSON UI API and Homebridge endpoints.
- `store.php`: persistence, cloud caching, and shared scene actions.
- `run.php` and `ensure.php`: background scene runner and command-delivery retries.
- `test.php`: offline protocol checks.
- `data/`: generated state, caches, and job locks; excluded from Git.

## Build, Test, and Development Commands

There is no build step, Composer setup, or npm toolchain. The working folder is a Synology web share served at `http://10.214.19.39/lights/`; edits affect that deployment directly.

- `cp -n config.example.php config.php`: create configuration without replacing existing credentials; then fill in the app login.
- `curl http://10.214.19.39/lights/test.php`: run offline checks on the NAS; expect `ok`.
- `php test.php`: run the same checks where PHP with OpenSSL is installed.
- `php -l haodeng.php`: syntax-check a changed PHP file; repeat for each changed file.

Open the deployment URL to inspect the UI. The documented Mac setup has no PHP; use the NAS test endpoint there.

## Coding Style & Naming Conventions

Match existing formatting: four-space PHP indentation, typed function signatures, snake_case helpers (`hd_`, `mqtt_`, `store_`), and uppercase constants. Preserve surrounding inline JavaScript/CSS style. No formatter or linter configuration is provided.

Keep protocol builders pure. Identify lights with `<place>-<addr>` keys because addresses repeat across places. Reuse UI `:root` tokens, amber accents, SVG icons, 44px touch targets, and reduced-motion support.

## Testing Guidelines

Extend the sequential `check(...)` assertions in `test.php` for payload, MQTT encoding, and status-decoding changes. There is no testing framework or coverage threshold. Verify the response body: failed checks print `FAIL check #N`. For UI changes, inspect a mobile viewport such as 390×844. Live command checks operate physical lights; preserve command spacing and retry behavior.

## Commit & Pull Request Guidelines

Git history is unavailable in this checkout. Use short imperative commit subjects, such as `Fix scene stop reset`. Describe behavior changes, affected endpoints, and validation performed; link relevant issues and include screenshots for UI changes.

## Configuration & State

Never commit `config.php`, cloud credentials, or `data/`. Keep configuration examples credential-free. Persistence uses writable `data/`, otherwise `sys_get_temp_dir()/lights`; preserve this fallback.
