# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A small PHP web app (mobile-first, no framework, no build) that controls Hao Deng Bluetooth-mesh outdoor lights through the Hao Deng
WiFi bridge. The bridge talks to the Magic Hue cloud, so this page does too. No local/BLE path.

It is served by a Synology NAS (PHP 8.2) from this folder: http://10.214.19.39/lights/
The folder is the SMB share `/Volumes/web/lights` on the Mac. Edit here, reload there. No build step.
The web server cannot write to this folder, so state lives in the PHP session only.

## Files

- `haodeng.php` — the whole cloud/mesh protocol, pure payload builders, hand-rolled MQTT client.
- `api.php` — JSON API used by the page (`?a=state`, `?a=playing`, POST `cmd`, `identify`, `rename`, `plan_save`, `scene_save`, `scene_delete`, `play`, `stop`).
  `state` and `playing` carry `jobs`: commands still being delivered by ensure.php. After a `cmd` the page paints the
  expected result at once, shows a "Changing the lights" bar, polls `playing` until `jobs` is 0, then reads status once.
- `index.php` — the UI: one HTML file with inline CSS/JS. Home = "Tiles": the area name is a `<select>` title,
  scenes are chips (play + edit), an "All lights" tile and one tile per light whose bottom strip is a range input
  (the brightness slider, filled in the light's color); tapping the tile's top opens the per-light bottom sheet.
  At >= 900px a left sidebar holds areas + scenes and the chips row hides. Scene editor (full view), presets.
  **Plan**: a 2D blueprint of the whole property (4:3 box, positions 0..1, saved as `store.json` `plan` =
  `{house:[x,y,w,h], pos:{key:[x,y]}}`; until saved, `planDefault` guesses from names: LS* left side, RS* right side,
  the rest across the front, an area named "Back…" along the back fence). Home shows live colors and, while a scene
  plays, a simulation; "Edit layout" drags lights and house corners. The scene editor paints colors on the plan and
  previews the effect live. `simAt` in index.php mirrors run.php's timing (0.12 s per publish) and math
  (`hd_mix_hex`, `hd_breathe_levels`): change both together.
  Design: "Operate" surface, dark slate palette (`--bg #0f172a`, `--surface #1e293b`),
  amber accent `#f59e0b` for the one primary action per screen, Plus Jakarta Sans (system fallback),
  inline Lucide-style SVG icons (no emoji), 44px touch targets, `prefers-reduced-motion` respected.
  Keep new UI on the CSS tokens in `:root`; no glows, gradients, or purple.
  To preview without the NAS: build a mock with a fake `fetch` (see session notes) and use `~/.claude/skills/gstack/browse/dist/browse` at 390x844.
  Design options go in one working mock page with a tab per option (the user clicks through them), never a design canvas.
- `run.php` — pattern runner, a CLI PHP process started in the background by `play` (`nohup /usr/bin/php run.php &`).
- `store.php` — JSON store (`store.json`: names, scenes, light order, the plan, last lane layout; `run.json`: what is
  playing), the disk-cached cloud login (`cloud.ser`, 1 h), a short status cache, and the shared actions
  `cmd_job`, `scene_play`, `scene_stop`, `stop_runner` used by both api.php and hb.php.
- `ensure.php` — background delivery of one command (started by `cmd_job`): send twice, check status,
  re-send to lights that missed it, up to 5 rounds; a newer command on the same light takes over via
  `jobs/lock-<place>-<addr>`. Needed because the mesh drops commands, badly for far lights (#19 loses ~1 in 3).
- `hb.php` — Homebridge endpoints (plain-text GET) for `homebridge-http-lightbulb` (on/bright/hue/sat/ct per
  light) and `homebridge-http-switch` (stateless: play scene, stop). `hb.php?config=1` prints the
  accessories JSON to paste into Homebridge (homebridge.local:8581). Hue and saturation arrive as two calls,
  so the last pair is kept in `hb-color.json` and combined into one RGB command.
- `test.php` — offline checks. `config.php` — login (gitignored).

## Commands

```
cp config.example.php config.php          # then fill in the Hao Deng app login
curl http://10.214.19.39/lights/test.php  # offline check of payloads + MQTT bytes, prints "ok"
curl http://10.214.19.39/lights/          # the UI
```

There is no PHP on the Mac. Run PHP through the NAS as above.

## How it works

- `haodeng.php` is the whole protocol. `index.php` is the UI. No PHP dependencies. The page loads SweetAlert2 from
  jsdelivr for every toast, the "changing the lights" wait, and confirms (the user asked for it); nothing else.
- **REST login** to `http://<region>meshcloud.magichue.net:8081/MeshClouds/`. The login body
  needs an MD5 password and an AES-256-ECB "checkcode" of `"ZG"+timestamp` with a fixed key.
  The returned `auth_token` goes in a `token` header afterwards.
- **Fetch places, then per place: devices, groups, MQTT creds.** The account has several
  "places" (Back Yard, Front & Sides); each has its own bridge and its own MQTT creds, and mesh
  addresses restart at 1 per place. Light names repeat, so the UI addresses targets by key
  `"<place index>-<meshAddress>"` (groups: `"<place index>-g<groupID>"`), see `HaoDeng::key`.
  Device `meshAddress` and group `groupID` are the MQTT destination addresses. Each device has a
  `controlType` (default 5) that becomes the first hex byte of every command. A group borrows
  the `controlType` of its first member.
- **Publish one JSON command over MQTT** to `<region>.meshbroker.magichue.net:1883`, topic
  `/<productKey>/<deviceName>/control`, with the `SOFTWARE` creds from getMasterControlData.
  The MQTT client is hand-rolled (CONNECT, PUBLISH qos 1, DISCONNECT) since there is no
  composer on the NAS. Opcodes: `D0` on/off, `E2` color (`60` = RGB, `62` = white temp + brightness).
- **Status readback**: `HaoDeng::status()` subscribes to `/<HARDWARE productKey>/<deviceName>/subStatus`,
  publishes `{"type":"immediateNOW","ver":1}` to the SOFTWARE `/request` topic, and collects replies for
  ~3 s. Each reply is `[{"a": meshAddress, "d": "XXYYZZWW"}]`: byte0 nonzero = on, byte1 = brightness %,
  byte2 > 0x3F = white mode (then byte3 = color temp %) else byte3 = hue/255. See `hd_decode_status`.
  Groups have no status. The page fetches status only for the place being viewed (`?place=N`).
- The logged-in `HaoDeng` object is cached on disk (`cloud_cached`) for an hour and shared by every
  device; api.php re-logs-in once if a call fails. Payload builders are pure functions so `test.php` can check them offline.
- **Mesh addressing tricks** (all verified live): `65535` (`HD_ALL`) is the broadcast address, one
  command hits every light in the place. Opcode `D7` adds/removes a light to a mesh group
  (`01`/`00` + gid lo + gid hi, *no* control-type prefix). Opcode `D0 02 <pct> 06` sets brightness only.
- **Patterns**: a scene = lanes (color + list of light keys `"<place>-<addr>"`) + effect + speed + `scope`
  (`place` = one area, `all` = both areas; the runner opens one MQTT connection per area and
  paints every area each step). Old scenes stored bare addresses; both api.php and run.php still accept them. `run.php` puts each lane's
  lights into mesh group `0x80F0 + lane` (only when the layout changed since last play, stored in
  `store.json`), then animates with one command per lane (or one broadcast) per step. Effects:
  static, blink (broadcast off/on), breathe (broadcast cosine brightness curve, 10-24 steps), rotate ("Chase": lanes take
  the next lane's color), fade (like rotate, gliding in up to 4 in-between colors round the color wheel), twinkle
  (random light dips, never the same twice in a row), rainbow (broadcast hue cycle). Stop = delete `run.json`,
  kill the pid, then start `run.php` again in `mode: reset`, which runs `hd_reset_place` per area in the
  background: broadcast white, then up to 4 rounds of status check + per-light re-send (the mesh drops
  ~1 in 6 commands; the bridge goes silent for a minute after a burst, so never flood it). Everyday look =
  `config['default']` (currently warm white 45%). The runner quits by itself after 12 h and resets too.
  A still scene is painted once and left alone. A stopped blink-to-find restores only its own light.
- The lights have no known built-in effect command. If one is ever wanted: the broker echoes our
  own `/control` topic, so subscribing to it while using the phone app remotely would capture the
  app's exact command bytes.

Protocol source: https://github.com/Pharylon/hao_deng_cloud (Home Assistant integration). Look
there if more of the protocol is ever needed.

## Gotchas

- The cloud drops ~15% of a burst at 10 commands/s, and the bridge stops answering status requests
  for a few seconds after a long burst. Space commands (`sendMany` gap 0.12 s), send important
  ones twice, and never send a status request right after a burst.
- The web server (user `http`) cannot write to this share, so `store.php` falls back to the NAS temp
  dir (`/var/services/tmp/lights`). The page shows a banner until `lights/data` is made writable
  for `http` in File Station.
- The status "d" hex: byte0 is NOT a power flag (it varies while the light is in reach); brightness 0 means off. In color mode
  byte1 is the HSV value, so a `00c000` green reports 75%. **byte0 = 00 means the bridge cannot reach the light** and answers
  from memory (stale bytes, commands do not take; 2026-09-16 every light of both areas did this). Decoded as `link=false`,
  shown as "Not reachable", `hd_ensure` does not re-send to those. If the lights ever work with byte0 = 00, revisit this.
- Lights are rarely off in real life: the user's normal state is all lights on, warm white, 40%.
- `config.php` holds the real password and is gitignored. Never commit it.
