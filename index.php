<?php header('Cache-Control: no-cache, must-revalidate'); ?>
<!doctype html>
<html lang="en">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="theme-color" content="#0f172a">
<meta name="color-scheme" content="dark">
<title>Lights</title>
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root{
  --bg:#0f172a; --surface:#1e293b; --surface-2:#273449; --muted:#334155; --border:#3b475c; --line:#ffffff10;
  --fg:#f8fafc; --fg-2:#cbd5e1; --fg-3:#94a3b8;
  --accent:#f59e0b; --accent-fg:#1a1200; --accent-soft:#f59e0b26;
  --danger:#ef4444; --ok:#22c55e;
  /* the plan: a blueprint drawn in the slate palette's own blue */
  --plan-bg:#0b1222; --plan-house:#0f1a30; --plan-grid:#131f37; --plan-grid-2:#1b2b4a; --plan-ink:#7fa3d1; --plan-ink-2:#3a5680;
  --r-sm:8px; --r:12px; --r-lg:16px;
  --sp-1:4px; --sp-2:8px; --sp-3:12px; --sp-4:16px; --sp-5:20px; --sp-6:24px; --sp-8:32px;
  /* motion: strong ease-out for things that enter or answer a tap, a drawer curve for sheets */
  --ease:cubic-bezier(.23,1,.32,1); --ease-drawer:cubic-bezier(.32,.72,0,1); --t:180ms;
  --font:"Plus Jakarta Sans",-apple-system,system-ui,"Segoe UI",sans-serif;
  --mono:"JetBrains Mono",ui-monospace,SFMono-Regular,Menlo,monospace;
}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html{color-scheme:dark}
body{margin:0;background:var(--bg);color:var(--fg);font:16px/1.5 var(--font);min-height:100dvh;padding-bottom:calc(96px + env(safe-area-inset-bottom));touch-action:manipulation;-webkit-font-smoothing:antialiased}
button,input,select{font:inherit;color:inherit}
button{border:0;background:none;padding:0;cursor:pointer;touch-action:manipulation}
:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
svg.i{width:20px;height:20px;stroke:currentColor;fill:none;stroke-width:1.75;stroke-linecap:round;stroke-linejoin:round;flex:none}
.tnum{font-variant-numeric:tabular-nums}
.mono{font-family:var(--mono);letter-spacing:.02em}

/* app bar */
.bar{position:sticky;top:0;z-index:5;background:color-mix(in srgb,var(--bg) 88%,transparent);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);padding:calc(var(--sp-3) + env(safe-area-inset-top)) var(--sp-4) var(--sp-3);border-bottom:1px solid var(--line)}
.bar-row{display:flex;align-items:center;justify-content:space-between;gap:var(--sp-3);max-width:720px;margin:0 auto}
h1{font-size:22px;font-weight:700;margin:0;letter-spacing:-.01em}
.seg{display:flex;background:var(--surface);border-radius:var(--r);padding:3px;gap:2px}
.seg button{min-height:38px;padding:0 var(--sp-4);border-radius:9px;color:var(--fg-3);font-weight:500;font-size:14px;transition:background-color var(--t) var(--ease),color var(--t) var(--ease);white-space:nowrap}
.seg button[aria-selected=true]{background:var(--surface-2);color:var(--fg);box-shadow:0 1px 2px #0006}

main{max-width:720px;margin:0 auto;padding:var(--sp-4)}
h2{font-size:12px;font-weight:700;color:var(--fg-3);text-transform:uppercase;letter-spacing:.08em;margin:var(--sp-8) 0 var(--sp-3)}
h2:first-child{margin-top:var(--sp-2)}
.h2row{display:flex;align-items:center;justify-content:space-between;gap:var(--sp-2);margin:var(--sp-6) 0 var(--sp-2)}
.h2row h2{margin:0}
.h2row .row{gap:0;flex-wrap:nowrap}
.link{color:var(--accent);font-weight:600;font-size:14px;min-height:44px;display:inline-flex;align-items:center;gap:6px;padding:0 var(--sp-2);border-radius:var(--r-sm);transition:color var(--t) ease}
.link svg.i{width:18px;height:18px}
.link.quiet{color:var(--fg-2)}
.link[aria-pressed=true]{color:var(--fg)}

/* buttons: press = small scale-down, so every tap answers within a frame */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:var(--sp-2);min-height:44px;padding:0 var(--sp-4);border-radius:var(--r);background:var(--surface-2);color:var(--fg);font-weight:600;font-size:15px;transition:background-color var(--t) var(--ease),transform 140ms var(--ease),opacity var(--t) ease}
.btn:active{transform:scale(.97)}
.btn.primary{background:var(--accent);color:var(--accent-fg)}
.btn.ghost{background:transparent;border:1px solid var(--border)}
.btn.danger{background:transparent;border:1px solid var(--danger);color:#fca5a5}
.btn.icon{width:44px;padding:0}
.btn[disabled]{opacity:.45;cursor:default;transform:none}
.btn.busy svg.i{animation:spin 700ms linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

/* hero: all lights */
.power{width:56px;height:56px;border-radius:50%;background:var(--surface-2);display:inline-flex;align-items:center;justify-content:center;transition:background-color var(--t) var(--ease),color var(--t) var(--ease),transform 140ms var(--ease);flex:none}
.power:active{transform:scale(.94)}
.power.on{background:var(--accent);color:var(--accent-fg)}
.power svg.i{width:26px;height:26px;stroke-width:2.25}
.quick{display:grid;grid-template-columns:repeat(4,1fr);gap:var(--sp-2);margin-top:var(--sp-3)}
.quick .btn{flex-direction:column;gap:2px;min-height:64px;font-size:13px;font-weight:500;padding:var(--sp-2)}
.quick .btn svg.i{width:22px;height:22px}

/* sliders */
.field{margin-top:var(--sp-4)}
.field-h{display:flex;justify-content:space-between;align-items:baseline;font-size:14px;color:var(--fg-2);margin-bottom:var(--sp-1);gap:var(--sp-2)}
.field-h b{color:var(--fg);font-weight:600}
input[type=range]{-webkit-appearance:none;appearance:none;width:100%;height:44px;background:transparent;margin:0}
input[type=range]::-webkit-slider-runnable-track{height:6px;border-radius:3px;background:var(--track,var(--muted))}
input[type=range]::-moz-range-track{height:6px;border-radius:3px;background:var(--track,var(--muted))}
input[type=range]::-webkit-slider-thumb{-webkit-appearance:none;width:28px;height:28px;border-radius:50%;background:#fff;border:0;margin-top:-11px;box-shadow:0 1px 4px #0008}
input[type=range]::-moz-range-thumb{width:28px;height:28px;border-radius:50%;background:#fff;border:0;box-shadow:0 1px 4px #0008}
.kelvin{--track:linear-gradient(90deg,#ffb15c,#fff3e0 50%,#cfe4ff)}

/* light cards */
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(156px,1fr));gap:var(--sp-3)}
.card{background:var(--surface);border:1px solid var(--line);border-radius:var(--r-lg);padding:var(--sp-4);min-height:96px;display:flex;flex-direction:column;justify-content:space-between;text-align:left;width:100%;transition:transform 140ms var(--ease),background-color var(--t) var(--ease),border-color var(--t) ease;position:relative}
.card.pick:active{transform:scale(.97)}
.card .top{display:flex;justify-content:space-between;align-items:flex-start;gap:var(--sp-2)}
.card .name{font-weight:600;font-size:15px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0}
.card .num{font-size:12px;color:var(--fg-3);flex:none;font-family:var(--mono)}
.card .status{display:flex;align-items:center;gap:var(--sp-2);font-size:13px;color:var(--fg-3);margin-top:var(--sp-3)}
.dot{width:12px;height:12px;border-radius:50%;background:var(--muted);flex:none;box-shadow:inset 0 0 0 1px #0004}
.card.lane{border-color:transparent;box-shadow:inset 0 0 0 2px var(--lane)}
.card.lane .num{color:var(--lane);font-weight:600}
.card.pick{cursor:pointer}
.grip{width:44px;height:44px;margin:-8px -8px -8px auto;display:inline-flex;align-items:center;justify-content:center;color:var(--fg-3);touch-action:none;cursor:grab;border-radius:var(--r-sm)}
.grip:active{cursor:grabbing}
.card.drag-src{opacity:.3}
.card.ghost{box-shadow:0 16px 40px #000c;scale:1.04;background:var(--surface-2)}
.grid.enter>*{animation:rise 320ms var(--ease) both;animation-delay:calc(min(var(--i),12) * 30ms)}

/* bottom sheet: drawer curve in, quicker out */
.scrim{position:fixed;inset:0;background:#000a;z-index:9;animation:fade 240ms ease-out}
.sheet{position:fixed;left:0;right:0;bottom:0;z-index:10;background:var(--surface);border:1px solid var(--line);border-bottom:0;border-radius:20px 20px 0 0;padding:var(--sp-2) var(--sp-4) calc(var(--sp-6) + env(safe-area-inset-bottom));max-height:92dvh;overflow-y:auto;box-shadow:0 -12px 40px #0008;animation:sheet-in 340ms var(--ease-drawer);max-width:720px;margin:0 auto}
#layer.closing .sheet{animation:sheet-out 200ms var(--ease) forwards}
#layer.closing .scrim{animation:fade-out 200ms ease forwards}
@keyframes sheet-in{from{transform:translateY(100%)}}
@keyframes sheet-out{to{transform:translateY(100%)}}
.handle{width:36px;height:4px;border-radius:2px;background:var(--border);margin:0 auto var(--sp-3)}
.sheet-h{display:flex;align-items:center;justify-content:space-between;gap:var(--sp-3);margin-bottom:var(--sp-1)}
.sheet-h h3{font-size:20px;font-weight:700;margin:0;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:flex;align-items:center;gap:10px}
.sheet .sub{color:var(--fg-3);font-size:14px}
.row{display:flex;gap:var(--sp-2);flex-wrap:wrap;align-items:center}
.swatches{display:grid;grid-template-columns:repeat(auto-fill,minmax(44px,1fr));gap:var(--sp-2)}
.swatch{height:44px;border-radius:var(--r);box-shadow:inset 0 0 0 1px #0006;transition:transform 140ms var(--ease)}
.swatch:active{transform:scale(.92)}
.colorwrap{position:relative;height:44px;border-radius:var(--r);background:var(--surface-2);display:flex;align-items:center;justify-content:center;gap:var(--sp-2);padding:0 var(--sp-3);font-weight:600;font-size:15px}
.colorwrap input[type=color]{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer}
.text{width:100%;min-height:44px;padding:0 var(--sp-3);border-radius:var(--r);background:var(--bg);border:1px solid var(--border)}
.text:focus{outline:2px solid var(--accent);outline-offset:0;border-color:transparent}
label.lbl{display:block;font-size:14px;color:var(--fg-2);margin:var(--sp-4) 0 var(--sp-1)}
select.text{appearance:none;-webkit-appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%2394a3b8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m4 6 4 4 4-4'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:36px}

/* editor view */
.editor-bar{position:sticky;top:0;z-index:5;background:color-mix(in srgb,var(--bg) 88%,transparent);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);padding:calc(var(--sp-2) + env(safe-area-inset-top)) var(--sp-3) var(--sp-2);border-bottom:1px solid var(--line)}
.editor-bar .bar-row{gap:var(--sp-2)}
.title-in{flex:1;min-width:0;min-height:44px;background:transparent;border:0;border-radius:var(--r-sm);font-size:20px;font-weight:700;letter-spacing:-.01em;padding:0 var(--sp-2)}
.title-in::placeholder{color:var(--fg-3)}
.title-in:focus{outline:none;background:var(--surface)}
.lanes{display:flex;gap:var(--sp-2);flex-wrap:wrap}
.lane-chip{display:flex;align-items:center;gap:var(--sp-2);min-height:44px;padding:0 var(--sp-2);border-radius:var(--r);background:var(--surface);border:2px solid transparent;cursor:pointer;transition:border-color var(--t) ease}
.lane-chip[aria-pressed=true]{border-color:var(--fg)}
.lane-chip .colorwrap{height:32px;width:32px;padding:0;border-radius:var(--r-sm);background:var(--c);box-shadow:inset 0 0 0 1px #0005}
.lane-chip .n{font-weight:600;font-size:14px;min-width:16px;text-align:center}
.lane-chip .x{width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;border-radius:var(--r-sm);color:var(--fg-3)}
.hint{color:var(--fg-3);font-size:14px;margin:var(--sp-2) 0 0}
.actions{position:fixed;left:0;right:0;bottom:0;z-index:6;padding:var(--sp-3) var(--sp-4) calc(var(--sp-3) + env(safe-area-inset-bottom));background:color-mix(in srgb,var(--bg) 92%,transparent);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-top:1px solid var(--line)}
.actions .row{max-width:720px;margin:0 auto;flex-wrap:nowrap}
.actions .btn{flex:1}
.effects{display:grid;grid-template-columns:repeat(2,1fr);gap:var(--sp-2)}
.opt{display:flex;gap:10px;align-items:flex-start;padding:12px;border-radius:var(--r);background:var(--surface);border:1px solid var(--line);text-align:left;min-height:64px;transition:border-color var(--t) ease,background-color var(--t) ease,transform 140ms var(--ease)}
.opt:active{transform:scale(.98)}
.opt svg.i{margin-top:2px;color:var(--fg-3)}
.opt b{display:block;font-size:15px;font-weight:600}
.opt small{display:block;font-size:12.5px;line-height:1.35;color:var(--fg-3);margin-top:2px}
.opt[aria-checked=true]{border-color:var(--fg-2);background:var(--surface-2)}
.opt[aria-checked=true] svg.i{color:var(--accent)}

/* now playing */
.playing{position:fixed;left:var(--sp-4);right:var(--sp-4);bottom:calc(var(--sp-4) + env(safe-area-inset-bottom));z-index:8;background:var(--surface-2);border:1px solid var(--border);border-radius:var(--r-lg);padding:var(--sp-2) var(--sp-2) var(--sp-2) var(--sp-4);display:flex;align-items:center;justify-content:space-between;gap:var(--sp-3);box-shadow:0 8px 30px #0008;max-width:688px;margin:0 auto;animation:up 300ms var(--ease)}
.playing .t{font-size:12px;color:var(--fg-3)}
.playing .n{font-weight:600}
.playing .busybox{flex:1;min-width:0}
.prog{height:4px;border-radius:2px;background:var(--muted);margin-top:8px;overflow:hidden}
.prog i{display:block;height:100%;width:100%;background:var(--accent);transform-origin:left;transition:transform 600ms var(--ease)}

/* SweetAlert2 (toasts, the wait while lights change, confirms) on the app's tokens */
.swal2-popup{background:var(--surface);color:var(--fg);font-family:var(--font);border-radius:var(--r-lg)}
.swal2-toast{box-shadow:0 6px 24px #0008;padding:10px 16px;border:1px solid var(--line)}
.swal2-toast .swal2-title{font-size:15px;font-weight:600}
.swal2-toast .swal2-html-container{font-size:13px;color:var(--fg-3)}
.swal2-title{font-size:20px;font-weight:700}
.swal2-html-container{color:var(--fg-2)}
.swal2-styled.swal2-confirm{background:var(--accent);color:var(--accent-fg);border-radius:var(--r);font-weight:600;min-height:44px}
.swal2-styled.swal2-cancel{background:var(--surface-2);color:var(--fg);border-radius:var(--r);font-weight:600;min-height:44px}
.swal2-styled:focus{box-shadow:0 0 0 2px var(--accent)}
.swal2-loader{border-color:var(--accent) transparent var(--accent) transparent}
.notice{background:var(--surface);border:1px solid var(--line);border-radius:var(--r);padding:var(--sp-3) var(--sp-4);font-size:14px;color:var(--fg-2);margin-bottom:var(--sp-4)}
.notice b{color:var(--fg)}
.skel{background:linear-gradient(90deg,var(--surface) 25%,var(--surface-2) 50%,var(--surface) 75%);background-size:200% 100%;animation:shim 1.2s linear infinite;border-radius:var(--r-lg);min-height:96px}
@keyframes shim{to{background-position:-200% 0}}
@keyframes up{from{transform:translateY(16px);opacity:0}}
@keyframes rise{from{transform:translateY(8px);opacity:0}}
@keyframes fade{from{opacity:0}}
@keyframes fade-out{to{opacity:0}}

/* home: sidebar (desktop) + content */
.app{display:flex;min-height:100dvh}
.side{display:none}
.content{flex:1;min-width:0}
.placewrap{position:relative;display:inline-flex;align-items:center;min-width:0}
select.place{appearance:none;-webkit-appearance:none;border:0;background:none;padding:0 30px 0 0;font-size:26px;font-weight:800;letter-spacing:-.02em;min-height:44px;max-width:100%;text-overflow:ellipsis}
.placewrap svg.i{position:absolute;right:2px;color:var(--fg-3);pointer-events:none;width:22px;height:22px}
.pill{font-size:13px;font-weight:600;color:var(--fg-2);background:var(--surface);border:1px solid var(--line);border-radius:999px;padding:6px 12px;white-space:nowrap;flex:none;display:inline-flex;align-items:center;gap:6px}
.pill i{width:8px;height:8px;border-radius:50%;background:var(--fg-3)}
.pill.lit i{background:var(--accent)}

/* scenes: chips on the phone, rows in the desktop sidebar */
.scenes-row{display:flex;gap:var(--sp-2);overflow-x:auto;margin:0 calc(-1 * var(--sp-4)) var(--sp-4);padding:0 var(--sp-4) var(--sp-1);scrollbar-width:none}
.scenes-row::-webkit-scrollbar{display:none}
.chip{display:inline-flex;align-items:center;min-height:44px;border-radius:999px;background:var(--surface);border:1px solid var(--line);font-weight:600;font-size:14px;white-space:nowrap;flex:none;transition:background-color var(--t) ease}
.chip .go{display:inline-flex;align-items:center;gap:var(--sp-2);min-height:44px;padding:0 var(--sp-2) 0 10px;border-radius:999px 0 0 999px;transition:transform 140ms var(--ease)}
.chip .go:active{transform:scale(.97)}
.chip .go svg.i{width:18px;height:18px;color:var(--fg-3)}
.chip .ed{width:40px;min-height:44px;display:inline-flex;align-items:center;justify-content:center;color:var(--fg-3);border-left:1px solid var(--bg);border-radius:0 999px 999px 0}
.chip .ed svg.i{width:16px;height:16px}
.chip.live{background:var(--accent);color:var(--accent-fg);border-color:transparent}
.chip.live .go svg.i,.chip.live .ed{color:var(--accent-fg)}
.chip.add{border:1px dashed var(--border);background:transparent;color:var(--fg-2);padding:0 14px 0 10px;gap:var(--sp-1)}
.stripe{display:flex;gap:2px;width:24px;height:12px;border-radius:3px;overflow:hidden;flex:none}
.stripe i{flex:1}

/* tiles: the strip at the bottom is the brightness slider */
.tile{padding:14px 14px 12px;gap:2px;justify-content:flex-start}
.tile .head{display:block;width:100%;text-align:left;border-radius:var(--r-sm);transition:transform 140ms var(--ease)}
.tile .head:active{transform:scale(.97)}
.tile .status{margin-top:0;display:block}
.tile.off .status,.tile.off .spct{color:var(--fg-3)}
.strip{position:relative;height:40px;border-radius:10px;background:var(--bg);overflow:hidden;margin-top:8px;--p:0%;--c:#ffc070}
.strip input[type=range]{position:absolute;inset:0;width:100%;height:40px;background:linear-gradient(90deg,var(--c) var(--p),transparent var(--p))}
.strip input[type=range]::-webkit-slider-runnable-track{height:40px;background:transparent}
.strip input[type=range]::-moz-range-track{height:40px;background:transparent}
.strip input[type=range]::-webkit-slider-thumb{width:18px;height:40px;margin-top:0;border-radius:6px;background:transparent;box-shadow:none}
.strip input[type=range]::-moz-range-thumb{width:18px;height:40px;border-radius:6px;background:transparent;box-shadow:none}
.strip.dashed{border:1px dashed var(--border);background:transparent}
.spct{position:absolute;right:8px;top:8px;line-height:24px;padding:0 8px;border-radius:6px;background:#0f172acc;font-size:13px;font-weight:700;pointer-events:none}
.strip.dashed .spct{pointer-events:auto;padding:0 4px}
.all{margin-bottom:var(--sp-2)}
.all .name{font-size:18px}
.all .status{font-size:14px}
.all .strip{height:52px}.all .strip input[type=range]{height:52px}.all .spct{top:14px;font-size:15px}
.all .strip input[type=range]::-webkit-slider-runnable-track{height:52px}.all .strip input[type=range]::-webkit-slider-thumb{height:52px}
.quick .btn.on{box-shadow:inset 0 0 0 2px var(--accent);background:var(--muted)}

/* ---- the plan: a top-down blueprint of the property; each light sits where it is on the house.
   Pools are flat circles that show where the light lands; they add up where they overlap (screen blend). */
.plan{position:relative;aspect-ratio:4/3;border-radius:var(--r-lg);overflow:hidden;background:var(--plan-bg);border:1px solid var(--line);container-type:inline-size;user-select:none;-webkit-user-select:none}
.plan-svg{position:absolute;inset:0;width:100%;height:100%;display:block}
.plan-svg *{vector-effect:non-scaling-stroke}
.plan-svg .g1{fill:none;stroke:var(--plan-grid);stroke-width:1}
.plan-svg .g2{fill:none;stroke:var(--plan-grid-2);stroke-width:1}
.plan-svg .lot{fill:none;stroke:var(--plan-ink-2);stroke-width:1;stroke-dasharray:6 5}
.plan-svg .street{stroke:var(--plan-ink-2);stroke-width:1.5;stroke-dasharray:16 10}
.plan-svg .wall{fill:var(--plan-house);stroke:var(--plan-ink);stroke-width:1.5}
.plan-svg .wall-in{fill:none;stroke:var(--plan-ink);stroke-width:1;opacity:.45}
.plan-svg .cut{fill:var(--plan-house);stroke:none}
.plan-svg .door{fill:none;stroke:var(--plan-ink);stroke-width:1;opacity:.8}
.plan-svg .door.arc{stroke-dasharray:3 3;opacity:.6}
.tag{position:absolute;transform:translate(-50%,-50%);font:600 10px/1 var(--mono);letter-spacing:.18em;color:var(--plan-ink);text-transform:uppercase;pointer-events:none;white-space:nowrap;opacity:.75}
.tag.l{transform:translate(0,-50%)}
.pools{position:absolute;inset:0;pointer-events:none}
.pool{position:absolute;width:clamp(30px,9cqw,68px);height:clamp(30px,9cqw,68px);border-radius:50%;transform:translate(-50%,-50%);background:var(--c);opacity:calc(var(--b) * .3);mix-blend-mode:screen;transition:opacity 160ms ease,background-color 160ms ease}
.pool.off,.pool.out:not(.lit),.pool.other,.pool.nolink{opacity:0}
.fx{position:absolute;width:32px;height:32px;transform:translate(-50%,-50%);display:grid;place-items:center;border-radius:50%;--c:#ffc070;--b:1}
.fx .bulb{width:14px;height:14px;border-radius:50%;background:color-mix(in srgb,var(--c) calc(var(--b) * 100%),var(--plan-bg));box-shadow:0 0 0 2px var(--plan-bg),0 0 0 3.5px var(--plan-ink);transition:background-color 160ms ease,transform 160ms var(--ease)}
.fx:active .bulb{transform:scale(.85)}
.fx.off .bulb,.fx.out:not(.lit) .bulb{background:var(--plan-bg);box-shadow:0 0 0 2px var(--plan-bg),0 0 0 3.5px var(--fg-3)}
.fx.out:not(.lit) .bulb{box-shadow:0 0 0 1.5px var(--fg-3);width:11px;height:11px}
.fx.nolink .bulb{background:var(--plan-bg);box-shadow:none;border:1.5px dashed var(--danger)}
.fx.other{pointer-events:none;opacity:.35}
.fx.other .bulb{width:9px;height:9px;background:var(--plan-bg);box-shadow:0 0 0 1px var(--plan-ink)}
.fx .lbl{position:absolute;top:calc(50% + 12px);left:50%;transform:translateX(-50%);font:600 9.5px/1.3 var(--mono);color:var(--fg-2);background:color-mix(in srgb,var(--plan-bg) 85%,transparent);padding:0 3px;border-radius:3px;white-space:nowrap;pointer-events:none;display:none}
.fx.alt .lbl{top:auto;bottom:calc(50% + 12px)}
.plan.names .fx:not(.other) .lbl,.plan.layout .fx .lbl{display:block}
.fx:focus-visible{outline-offset:-2px}
.plan.paint,.plan.layout{touch-action:none}
.plan.paint .fx{cursor:crosshair}
.plan.layout .fx{cursor:grab}
.plan.layout .fx.drag{cursor:grabbing;z-index:3}
.plan.layout .fx.drag .bulb{transform:scale(1.25)}
.plan.layout .fx.other{pointer-events:auto;opacity:1}
.plan.layout .fx.other .bulb{width:14px;height:14px}
.hnd{position:absolute;width:32px;height:32px;transform:translate(-50%,-50%);display:grid;place-items:center;cursor:nwse-resize;z-index:2}
.hnd::after{content:"";width:12px;height:12px;background:var(--plan-bg);border:2px solid var(--accent);border-radius:3px}
.plan-cap{display:flex;align-items:center;gap:var(--sp-2);font-size:13px;color:var(--fg-3);margin:var(--sp-2) 2px 0;min-height:20px}
.plan-cap .live{width:8px;height:8px;border-radius:50%;background:var(--accent);flex:none;animation:beat 1.6s ease-in-out infinite}
@keyframes beat{50%{opacity:.35}}
.plan-tools{display:flex;gap:var(--sp-2);margin-top:var(--sp-3);flex-wrap:wrap}
.plan-tools .btn{flex:1}
.legend{display:flex;gap:var(--sp-4);flex-wrap:wrap;font-size:12.5px;color:var(--fg-3);margin-top:var(--sp-2)}
.legend span{display:inline-flex;align-items:center;gap:6px}
.legend i{width:10px;height:10px;border-radius:50%;display:inline-block}
@container (min-width:600px){.fx:not(.other) .lbl{display:block}.tag{font-size:11px}}

/* desktop: sidebar with areas and scenes, wider grid */
@media (min-width:900px){
  .side{display:flex;flex-direction:column;gap:var(--sp-6);width:264px;flex:none;padding:var(--sp-6) var(--sp-4);border-right:1px solid var(--line);position:sticky;top:0;height:100dvh;overflow-y:auto}
  .brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:18px;padding:0 var(--sp-2)}
  .brand svg.i{color:var(--accent);width:24px;height:24px}
  .side h2{margin:0 0 6px;padding:0 var(--sp-2)}
  .nav{display:flex;align-items:center;justify-content:space-between;width:100%;min-height:44px;padding:0 var(--sp-3);border-radius:10px;color:var(--fg-2);font-weight:600;font-size:15px;transition:background-color var(--t) ease,color var(--t) ease}
  .nav[aria-current=true]{background:var(--surface);color:var(--fg)}
  .nav .cnt{font-size:12px;color:var(--fg-3)}
  .side .chip{display:flex;background:none;border:0;border-radius:10px;min-height:48px}
  .side .chip .go{flex:1;padding:0 var(--sp-2) 0 var(--sp-3);gap:10px}
  .side .chip .go span:not(.stripe){flex:1}
  .side .chip .stripe{width:28px;height:28px;border-radius:8px;gap:3px}
  .side .chip .ed{border:0}
  .side .chip.live{background:var(--accent)}
  .side .chip.add{border:1px dashed var(--border);margin-top:var(--sp-2)}
  .scenes-row{display:none}
  .bar-row,main{max-width:1180px;margin:0}
  .bar{padding-left:40px;padding-right:40px}
  main{padding:var(--sp-6) 40px 40px}
  select.place{font-size:30px}
  .grid{grid-template-columns:repeat(auto-fill,minmax(200px,1fr))}
  .home-top{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(300px,1fr);gap:var(--sp-6);align-items:start}
  .home-top .h2row:first-child,.h2row.first-desk{margin-top:4px}
  .all{margin-bottom:0}
  .effects{grid-template-columns:repeat(2,1fr)}
  .ed-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(320px,1fr);gap:var(--sp-8);align-items:start}
  .ed-grid>.ed-plan{position:sticky;top:84px}
  .editor-bar .bar-row,.actions .row{max-width:1180px;margin:0}
  .editor-bar,.actions{padding-left:40px;padding-right:40px}
  .actions .btn{flex:0 1 220px}
  main.ed{max-width:1260px}
}
@media (hover:hover) and (pointer:fine){
  .btn:not(.primary):not([disabled]):hover{background:var(--muted)}
  .btn.ghost:hover{background:var(--surface)}
  .chip:not(.live):hover{background:var(--surface-2)}
  .nav:not([aria-current=true]):hover{background:color-mix(in srgb,var(--surface) 60%,transparent)}
  .opt:hover{border-color:var(--border)}
  .link:hover{color:var(--fg)}
  .fx:not(.other):hover .bulb{transform:scale(1.2)}
}
/* reduced motion: keep color and opacity changes (they carry meaning), drop movement */
@media (prefers-reduced-motion:reduce){
  *{transition-property:opacity,background-color,color,border-color,box-shadow!important}
  .sheet,.playing,.grid.enter>*{animation-name:fade!important}
  #layer.closing .sheet{animation-name:fade-out!important}
  .skel,.plan-cap .live{animation:none!important}
}
</style>

<div id="app"></div>
<div id="layer"></div>

<script>
'use strict';
const $ = (s, el = document) => el.querySelector(s);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
const I = { // Lucide-style icons, 24 grid
  power: '<path d="M12 2v10"/><path d="M18.4 6.6a9 9 0 1 1-12.8 0"/>',
  sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
  palette: '<circle cx="13.5" cy="6.5" r=".8"/><circle cx="17.5" cy="10.5" r=".8"/><circle cx="8.5" cy="7.5" r=".8"/><circle cx="6.5" cy="12.5" r=".8"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10a2.5 2.5 0 0 0 1.8-4.2 2.5 2.5 0 0 1 1.8-4.3H18a4 4 0 0 0 4-4c0-4.4-4.5-7.5-10-7.5z"/>',
  sparkle: '<path d="M12 3l1.9 5.6L19.5 10.5l-5.6 1.9L12 18l-1.9-5.6L4.5 10.5l5.6-1.9z"/><path d="M19 17l.7 2.3L22 20l-2.3.7L19 23l-.7-2.3L16 20l2.3-.7z"/>',
  pencil: '<path d="M17 3a2.8 2.8 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5z"/><path d="m15 5 4 4"/>',
  play: '<path d="m6 4 14 8-14 8z"/>',
  pause: '<rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/>',
  stop: '<rect x="5" y="5" width="14" height="14" rx="2"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  back: '<path d="m15 18-6-6 6-6"/>',
  up: '<path d="m18 15-6-6-6 6"/>',
  down: '<path d="m6 9 6 6 6-6"/>',
  sort: '<path d="M3 6h18M7 12h10M10 18h4"/>',
  grip: '<circle cx="9" cy="6" r="1.2"/><circle cx="15" cy="6" r="1.2"/><circle cx="9" cy="12" r="1.2"/><circle cx="15" cy="12" r="1.2"/><circle cx="9" cy="18" r="1.2"/><circle cx="15" cy="18" r="1.2"/>',
  x: '<path d="M18 6 6 18M6 6l12 12"/>',
  check: '<path d="M20 6 9 17l-5-5"/>',
  loader: '<path d="M21 12a9 9 0 1 1-6.2-8.6"/>',
  flame: '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.4-.5-2-1-3-1.1-2.2-.2-4.1 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.2.5-2.3 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>',
  snow: '<path d="M12 2v20M2 12h20M4.9 4.9l14.2 14.2M19.1 4.9 4.9 19.1"/>',
  bulb: '<path d="M9 18h6M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.8.6 1 1.3 1 2.3h6c0-1 .2-1.7 1-2.3A7 7 0 0 0 12 2z"/>',
  move: '<path d="m5 9-3 3 3 3M9 5l3-3 3 3M15 19l-3 3-3-3M19 9l3 3-3 3M2 12h20M12 2v20"/>',
  type: '<path d="M4 7V4h16v3M9 20h6M12 4v16"/>',
  reset: '<path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M8 16H3v5"/>',
  still: '<circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="9"/>',
  blend: '<circle cx="9" cy="12" r="6"/><circle cx="15" cy="12" r="6"/>',
  repeat: '<path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/>',
  wave: '<path d="M2 12c2-5 4-5 6 0s4 5 6 0 4-5 6 0"/>',
  zap: '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
  rainbow: '<path d="M22 17a10 10 0 0 0-20 0"/><path d="M18 17a6 6 0 0 0-12 0"/><path d="M14 17a2 2 0 0 0-4 0"/>',
};
const ic = (n, cls = '') => `<svg class="i ${cls}" viewBox="0 0 24 24" aria-hidden="true">${I[n]}</svg>`;

const PRESETS = {
  'Christmas':   {effect:'fade', speed:3, colors:['ff0000','00c000']},
  'Halloween':   {effect:'twinkle', speed:6, colors:['ff6a00','8a00ff']},
  'Easter':      {effect:'fade', speed:2, colors:['ff9ad5','a0e8ff','fff59a','c8ffb0']},
  '4th of July': {effect:'rotate', speed:5, colors:['ff0000','d2d2d2','0040ff']},
  'Valentine':   {effect:'breathe', speed:3, colors:['ff1060','ff80b0']},
  'St. Patrick': {effect:'breathe', speed:3, colors:['00c000','ffffff']},
  'Rainbow':     {effect:'rainbow', speed:6, colors:['ff0000']},
};
// [name, what it does, icon]. Blink, breathe and rainbow go out as one broadcast, so they reach every light in the area.
const EFFECTS = {
  static:  ['Still', 'Colors stay as set', 'still'],
  fade:    ['Fade', 'Colors glide into each other', 'blend'],
  rotate:  ['Chase', 'Colors jump to the next group', 'repeat'],
  twinkle: ['Twinkle', 'A random light dips, then the next', 'sparkle'],
  breathe: ['Breathe', 'Every light in the area dims and glows', 'wave'],
  blink:   ['Blink', 'Every light in the area flashes', 'zap'],
  rainbow: ['Rainbow', 'Every light in the area cycles colors', 'rainbow'],
};
const SPEEDS = ['', 'Very slow', 'Slow', 'Slow', 'Relaxed', 'Medium', 'Medium', 'Lively', 'Fast', 'Fast', 'Very fast'];
const SWATCHES = ['ff0000','ff6a00','ffd000','00c000','00c8ff','0040ff','8a00ff','ff40c0'];
const REDUCED = matchMedia('(prefers-reduced-motion: reduce)').matches;
const store = {get: k => { try { return localStorage.getItem(k); } catch (e) { return null; } }, set: (k, v) => { try { localStorage.setItem(k, v); } catch (e) {} }};

let place = store.get('place') === 'all' ? 'all' : +(store.get('place') || 0), S = null, editing = null, laneSel = 0, loadTimer, reordering = false, touched = 0, settling = 0;
let layout = null, planNames = store.get('planNames') === '1', preview = {on: !REDUCED, t0: performance.now()}, firstPaint = true;
const BOTH = () => place === 'all';

// ---- data
async function api(a, body) {
  const r = body ? await fetch('api.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({a, place, ...body})})
                 : await fetch(`api.php?a=${a}&place=${place}`);
  const j = await r.json();
  if (j.error) { toast('Error: ' + j.error, true); throw new Error(j.error); }
  return j;
}
async function load() {
  clearTimeout(loadTimer);
  if (Date.now() - touched < 1500 || layout) return reloadSoon(1500); // a strip is being dragged, or the plan is being laid out: don't re-render under the finger
  const old = S; S = await api('state');
  if (old && S.playing && S.playing.phase === 'running') // a bridge skips status requests during a scene's bursts: keep the last known look
    S.lights.forEach(l => { if (!l.status) l.status = (old.lights.find(x => x.key === l.key) || {}).status || null; });
  render();
}
function reloadSoon(ms = 2500) { clearTimeout(loadTimer); loadTimer = setTimeout(() => { if (!reordering) load(); }, ms); }
async function cmd(body, msg = 'Sent', btn) {
  if (btn) busy(btn, true);
  try { await api('cmd', body); expect(body); settle(msg); } finally { if (btn) busy(btn, false); }
}
// paint what the command will do right away (the tiles and the plan, not the sheet); the next status readback corrects it
function expect(b) {
  const hit = b.addr === 'all' ? S.lights : S.lights.filter(l => l.addr === b.addr && l.place === b.place);
  hit.forEach(l => {
    if (l.status && l.status.link === false) return; // the bridge cannot reach it: do not pretend
    const st = l.status || (l.status = {on: false, bright: 0, mode: 'white', kelvin: 2700});
    if (b.cmd === 'off') { st.on = false; st.bright = 0; return; }
    st.on = true; st.bright = st.bright || 100;
    if (b.cmd === 'bright') st.bright = b.bright;
    if (b.cmd === 'white') Object.assign(st, {mode: 'white', kelvin: b.kelvin, bright: b.bright, rgb: null});
    if (b.cmd === 'rgb') { const hex = b.color.replace('#', ''); Object.assign(st, {mode: 'color', rgb: hex, bright: Math.round(Math.max(...[0, 2, 4].map(i => parseInt(hex.substr(i, 2), 16))) / 2.55)}); }
  });
  if (!editing) $('#app').innerHTML = homeView();
}
// the lights are being changed in the background (up to ~30 s): show a bar, poll until the sender is done, then read the truth once
function settle(msg) {
  settling = Date.now();
  Swal.fire({toast: true, position: 'top', titleText: msg, text: 'Changing the lights… up to 30 s', showConfirmButton: false, didOpen: () => Swal.showLoading()});
  startPolling();
}
function busy(btn, on) { btn.disabled = on; btn.classList.toggle('busy', on); if (on) { btn.dataset.html = btn.innerHTML; btn.innerHTML = ic('loader'); } else btn.innerHTML = btn.dataset.html; }
function toast(m, isError) { Swal.fire({toast: true, position: 'top', icon: isError ? 'error' : 'success', titleText: m, showConfirmButton: false, timer: 3000}); }

// ---- helpers
const light = key => S.lights.find(l => l.key === key);
function swatch(st) {
  if (!st || !st.on) return 'var(--muted)';
  if (st.rgb) return '#' + st.rgb; // painted from a command we just sent
  if (st.mode === 'white') return st.kelvin < 4000 ? '#ffc070' : '#eef4ff';
  return `hsl(${st.hue} ${st.sat}% 55%)`;
}
function look(st) {
  if (!st) return 'No reply';
  if (st.link === false) return 'Not reachable';
  if (!st.on) return 'Off';
  return st.mode === 'white' ? (st.kelvin < 4000 ? 'Warm white' : st.kelvin < 5500 ? 'White' : 'Cool white') : 'Color';
}
const describe = st => st && st.on ? `${st.bright}% · ${look(st)}` : look(st);
const onLights = () => S.lights.filter(l => l.status && l.status.on);
const anyOn = () => onLights().length > 0;
const avgBright = () => { const on = onLights(); return on.length ? Math.round(on.reduce((a, l) => a + l.status.bright, 0) / on.length) : 60; };
function mainLook() { const c = {}; onLights().forEach(l => { const k = look(l.status); c[k] = (c[k] || 0) + 1; }); return Object.keys(c).sort((a, b) => c[b] - c[a])[0] || 'Off'; }
const allKeyed = () => S.places.flatMap((n, p) => (S.allLights[p] || []).map((l, i) => ({...l, place: p, idx: i})));

// ---- color math, the same as haodeng.php (hd_mix_hex, hd_breathe_levels) so the preview matches the lights
const toRgb = h => [0, 2, 4].map(i => parseInt(h.substr(i, 2), 16) / 255);
const toHex = c => c.map(x => Math.round(x * 255).toString(16).padStart(2, '0')).join('');
function hsv([r, g, b]) {
  const max = Math.max(r, g, b), d = max - Math.min(r, g, b);
  const h = d === 0 ? 0 : (max === r ? ((g - b) / d + 6) % 6 : max === g ? (b - r) / d + 2 : (r - g) / d + 4) * 60;
  return [h, max ? d / max : 0, max];
}
function mixHex(a, b, t) {
  const ca = toRgb(a), cb = toRgb(b), [ha, sa, va] = hsv(ca), [hb, sb, vb] = hsv(cb);
  if (sa < .15 || sb < .15) return toHex(ca.map((x, i) => x + (cb[i] - x) * t));
  const dh = ((hb - ha + 540) % 360) - 180, h = ((ha + dh * t + 360) % 360) / 60, s = sa + (sb - sa) * t, v = va + (vb - va) * t;
  const f = h - Math.floor(h), p = v * (1 - s), q = v * (1 - s * f), u = v * (1 - s * (1 - f));
  return toHex([[v, u, p], [q, v, p], [p, v, u], [p, q, v], [u, p, v], [v, p, q]][Math.floor(h) % 6]);
}
const breatheLevels = n => Array.from({length: n}, (_, i) => Math.round(5 + 95 * (1 + Math.cos(2 * Math.PI * i / n)) / 2));
function hueHex(h) { // run.php's rainbow step
  const x = 1 - Math.abs((h / 60) % 2 - 1), m = Math.floor(255 * x) / 255;
  return toHex([[1, m, 0], [m, 1, 0], [0, 1, m], [0, m, 1], [m, 0, 1], [1, 0, m]][Math.floor(h / 60) % 6]);
}
function cssHex(st) { // a light's own look as RRGGBB, for the plan
  if (!st || !st.on) return 'ffc070';
  if (st.rgb) return st.rgb;
  if (st.mode === 'white') return st.kelvin < 4000 ? 'ffc070' : 'eef4ff';
  const s = st.sat / 100, h = st.hue / 60, f = h - Math.floor(h), p = 1 - s, q = 1 - s * f, u = 1 - s * (1 - f);
  return toHex([[1, u, p], [q, 1, p], [p, 1, u], [p, q, 1], [u, p, 1], [1, p, q]][Math.floor(h) % 6]);
}

// ---- effect simulation: what run.php sends, step by step, as a function of time (seconds). Mirrors its timing,
// including the 0.12 s gap after every MQTT publish, so the preview's rhythm is the real one.
const PUB = 0.12, period = sp => 0.5 + (10 - sp) * 0.35;
function simOf(sc) {
  const lanes = sc.lanes.filter(l => l.lights.length);
  if (!lanes.length) return null;
  const laneOf = {};
  lanes.forEach((l, i) => l.lights.forEach(k => laneOf[typeof k === 'number' ? `${sc.place}-${k}` : k] = i));
  const keys = Object.keys(laneOf), places = [...new Set(keys.map(k => +k.split('-')[0]))];
  const wide = ['blink', 'breathe', 'rainbow'].includes(sc.effect) ? places.flatMap(p => (S.allLights[p] || []).map(l => l.key)) : [];
  return {effect: sc.effect, colors: lanes.map(l => l.color), laneOf, keys, wide, n: places.length, P: period(sc.speed), K: lanes.length};
}
function mix32(i) { let x = Math.imul(i ^ 0x9e3779b9, 0x85ebca6b); x ^= x >>> 13; x = Math.imul(x, 0xc2b2ae35); return (x ^ (x >>> 16)) >>> 0; }
function simAt(sim, t) { // -> {key: [hex or null (= its own color), brightness 0..1]}
  const {effect, colors, laneOf, keys, wide, n, P, K} = sim, out = {};
  keys.forEach(k => out[k] = [colors[laneOf[k]], 1]);
  wide.forEach(k => { if (!out[k]) out[k] = [null, 1]; });
  if (effect === 'blink') { const on = Math.floor(t / (P + n * PUB)) % 2 === 0; for (const k in out) out[k][1] = on ? 1 : 0; }
  if (effect === 'breathe') { const cyc = 2.5 * P, m = clamp(Math.round(cyc / .3), 10, 24), lv = breatheLevels(m)[Math.floor(t / (cyc / m + n * PUB)) % m]; for (const k in out) out[k][1] = lv / 100; }
  if (effect === 'rotate') { const s = Math.floor(t / (P + K * n * PUB)); keys.forEach(k => out[k][0] = colors[(laneOf[k] + s) % K]); }
  if (effect === 'fade') {
    const m = clamp(Math.floor(P / (PUB * K * n + .15)), 1, 4), g = Math.floor(t / (P / m + K * n * PUB)), tt = Math.floor(g / m), s = g % m;
    keys.forEach(k => { const i = laneOf[k]; out[k][0] = mixHex(colors[(i + tt) % K], colors[(i + tt + 1) % K], s / m); });
  }
  if (effect === 'twinkle') {
    const d = Math.max(.3, P / 3), C = P / 2 + d + 4 * PUB, c = Math.floor(t / C), w = t - c * C, N = keys.length;
    let a = mix32(c) % N; if (N > 1 && a === mix32(c - 1) % N) a = (a + 1) % N; // never the same light twice in a row
    if (w >= P / 2 + 2 * PUB && w < P / 2 + 3 * PUB + d) out[keys[a]][1] = .05;
  }
  if (effect === 'rainbow') { const hex = hueHex((Math.floor(t / (P / 3 + n * PUB)) * 20) % 360); for (const k in out) out[k][0] = hex; }
  return out;
}
// what the plan should animate right now: the scene being edited (preview), or the scene playing on the lights
let homeRun = {token: null, t0: 0};
function simSource() {
  if (layout) return null;
  if (editing) return preview.on ? {sim: simOf(editing), t0: preview.t0} : null;
  const p = S && S.playing;
  if (!p || !p.id || p.phase !== 'running') return null;
  const sc = S.scenes.find(s => s.id === p.id);
  if (!sc) return null;
  if (homeRun.token !== p.token) homeRun = {token: p.token, t0: performance.now()};
  return {sim: simOf(sc), t0: homeRun.t0};
}
function tick() {
  requestAnimationFrame(tick);
  const src = simSource();
  if (!src || !src.sim) return;
  const out = simAt(src.sim, (performance.now() - src.t0) / 1000);
  document.querySelectorAll('.plan [data-k]').forEach(el => {
    const v = out[el.dataset.k]; if (!v) return;
    const c = '#' + (v[0] || el.dataset.own || 'ffc070'), b = v[1].toFixed(2);
    if (el._c !== c) { el.style.setProperty('--c', c); el._c = c; }
    if (el._b !== b) { el.style.setProperty('--b', b); el._b = b; }
    if (!el._lit) { el.classList.add('lit'); el.classList.remove('off'); el._lit = true; }
  });
}
requestAnimationFrame(tick);

// ---- the plan: positions are 0..1 of the plan box (4:3). Saved in store.json; until then guessed from names and order.
function planDefault(house = [0.17, 0.27, 0.66, 0.42]) {
  const [hx, hy, hw, hh] = house, dx = 0.045, dy = 0.06, pos = {};
  const even = (n, a, b) => Array.from({length: n}, (_, i) => a + (b - a) * (n === 1 ? .5 : i / (n - 1)));
  const L = hx - dx, R = hx + hw + dx, F = hy + hh + dy, T = hy + 0.04, B = hy + hh - 0.02;
  let rowY = 0.12, uUsed = false;
  S.places.forEach((name, p) => {
    const ls = S.allLights[p] || [];
    if (!ls.length) return;
    if (/back/i.test(name) || uUsed) { // a row along the back fence
      even(ls.length, 0.16, 0.84).forEach((x, i) => pos[ls[i].key] = [x, rowY]); rowY += 0.06; return;
    }
    uUsed = true;
    const left = ls.filter(l => /^LS/i.test(l.name)), right = ls.filter(l => /^RS/i.test(l.name));
    if (left.length || right.length) { // names say where: left side top to bottom, front left to right, right side bottom to top
      const front = ls.filter(l => !left.includes(l) && !right.includes(l));
      even(left.length, T, B).forEach((y, i) => pos[left[i].key] = [L, y]);
      even(front.length, L + 0.065, R - 0.065).forEach((x, i) => pos[front[i].key] = [x, F]);
      even(right.length, B, T).forEach((y, i) => pos[right[i].key] = [R, y]);
      return;
    }
    // no hints: walk round the house (up the left, across the front, up the right) in the saved order
    const pts = [[L, T], [L, F], [R, F], [R, T]], seg = [], asp = 0.75;
    for (let i = 0; i < 3; i++) seg.push(Math.hypot(pts[i + 1][0] - pts[i][0], (pts[i + 1][1] - pts[i][1]) * asp));
    const total = seg.reduce((a, b) => a + b);
    ls.forEach((l, i) => {
      let d = total * (ls.length === 1 ? .5 : i / (ls.length - 1)), s = 0;
      while (s < 2 && d > seg[s]) d -= seg[s++];
      const k = seg[s] ? d / seg[s] : 0;
      pos[l.key] = [pts[s][0] + (pts[s + 1][0] - pts[s][0]) * k, pts[s][1] + (pts[s + 1][1] - pts[s][1]) * k];
    });
  });
  for (const k in pos) pos[k] = pos[k].map(v => +clamp(v, 0.03, 0.97).toFixed(4));
  return {house, pos};
}
function planData() {
  const sp = S.plan || {}, house = sp.house && sp.house.length === 4 ? sp.house : undefined, d = planDefault(house);
  return {house: d.house, pos: {...d.pos, ...(sp.pos && !Array.isArray(sp.pos) ? sp.pos : {})}};
}
function houseSvg([hx, hy, hw, hh]) {
  const x = hx * 1000, y = hy * 750, w = hw * 1000, h = hh * 750, cx = x + w / 2, by = y + h, dw = Math.min(46, w / 4);
  return `<rect class="wall" x="${x}" y="${y}" width="${w}" height="${h}"/>
    <rect class="wall-in" x="${x + 9}" y="${y + 9}" width="${Math.max(0, w - 18)}" height="${Math.max(0, h - 18)}"/>
    <rect class="cut" x="${cx - dw / 2}" y="${by - 10}" width="${dw}" height="12"/>
    <path class="door" d="M${cx - dw / 2} ${by} V${by - dw}"/><path class="door arc" d="M${cx - dw / 2} ${by - dw} A${dw} ${dw} 0 0 1 ${cx + dw / 2} ${by}"/>`;
}
// mode: 'home' (live looks, tap opens the light), 'paint' (scene editor), 'layout' (drag lights and house corners)
function planView(mode) {
  const pd = mode === 'layout' ? layout : planData(), [hx, hy, hw, hh] = pd.house;
  const inScope = mode === 'paint' ? new Set(sceneLights().map(l => l.key)) : null;
  const laneOf = {}; if (mode === 'paint') editing.lanes.forEach((l, i) => l.lights.forEach(k => laneOf[k] = i));
  const fx = allKeyed().filter(l => pd.pos[l.key]).map(l => {
    const [x, y] = pd.pos[l.key], live = mode === 'home' ? light(l.key) : null, st = live && live.status;
    let cls = '', c = 'ffc070', b = 1, label = l.name, own = 'ffc070';
    if (mode === 'home') {
      if (!live) cls = 'other';
      else if (!st) cls = 'off';
      else if (st.link === false) { cls = 'nolink'; label += ', not reachable'; }
      else if (!st.on) { cls = 'off'; label += ', off'; }
      else { c = own = cssHex(st); b = Math.max(.3, st.bright / 100); label += ', ' + describe(st); }
    } else if (mode === 'paint') {
      if (!inScope.has(l.key)) cls = 'other';
      else if (laneOf[l.key] != null) { c = editing.lanes[laneOf[l.key]].color; label += ', color ' + (laneOf[l.key] + 1); }
      else { cls = 'out'; label += ', not in scene'; }
    } else if (!(BOTH() || l.place === place)) cls = 'other';
    const style = `left:${x * 100}%;top:${y * 100}%;--c:#${c};--b:${b}`, alt = l.idx % 2 ? 'alt' : '';
    const act = mode === 'home' ? `onclick="openLight('${l.key}')"` : mode === 'paint' ? `onclick="if(event.detail===0)toggleLane('${l.key}')"` : `onkeydown="nudge(event,'${l.key}')"`;
    return [`<i class="pool ${cls}" data-k="${l.key}" data-own="${own}" style="${style}"></i>`,
      `<button class="fx ${cls} ${alt}" data-k="${l.key}" data-own="${own}" style="${style}" aria-label="${esc(label)}" ${cls === 'other' && mode !== 'layout' ? 'tabindex="-1" aria-hidden="true"' : ''} ${act}><i class="bulb"></i><span class="lbl">${esc(l.name)}</span></button>`];
  });
  const backName = S.places.find(n => /back/i.test(n));
  const down = mode === 'paint' ? 'onpointerdown="paintStart(event)"' : mode === 'layout' ? 'onpointerdown="layoutDown(event)"' : '';
  return `<div class="plan ${mode} ${planNames ? 'names' : ''}" ${down}>
    <svg class="plan-svg" viewBox="0 0 1000 750" preserveAspectRatio="none" aria-hidden="true">
      <defs><pattern id="pg1" width="25" height="25" patternUnits="userSpaceOnUse"><path class="g1" d="M25 0H0V25"/></pattern>
        <pattern id="pg2" width="125" height="125" patternUnits="userSpaceOnUse"><path class="g2" d="M125 0H0V125"/></pattern></defs>
      <rect width="1000" height="750" fill="url(#pg1)"/><rect width="1000" height="750" fill="url(#pg2)"/>
      <rect class="lot" x="18" y="16" width="964" height="672"/>
      <line class="street" x1="0" x2="1000" y1="712" y2="712"/>
      <g class="house">${houseSvg(pd.house)}</g>
    </svg>
    <span class="tag" data-tag="house" style="left:${(hx + hw / 2) * 100}%;top:${(hy + hh / 2) * 100}%">House</span>
    <span class="tag" style="left:50%;top:97.6%">Street</span>
    ${backName ? `<span class="tag l" style="left:3.5%;top:5.2%">${esc(backName)}</span>` : ''}
    <div class="pools">${fx.map(f => f[0]).join('')}</div>
    ${fx.map(f => f[1]).join('')}
    ${mode === 'layout' ? `<button class="hnd" data-h="tl" aria-label="House corner, top left" style="left:${hx * 100}%;top:${hy * 100}%"></button><button class="hnd" data-h="br" aria-label="House corner, bottom right" style="left:${(hx + hw) * 100}%;top:${(hy + hh) * 100}%"></button>` : ''}
  </div>`;
}
function toggleNames() { planNames = !planNames; store.set('planNames', planNames ? '1' : '0'); document.querySelectorAll('.plan').forEach(p => p.classList.toggle('names', planNames)); document.querySelectorAll('[data-names]').forEach(b => b.setAttribute('aria-pressed', planNames)); }
const namesBtn = () => `<button class="link quiet" data-names aria-pressed="${planNames}" onclick="toggleNames()">${ic('type')}Names</button>`;

// ---- layout editing: drag a light (or a house corner); arrows nudge the focused light
function startLayout() { layout = JSON.parse(JSON.stringify(planData())); render(); }
function cancelLayout() { layout = null; render(); }
function autoLayout() { layout = planDefault(layout.house); render(); }
async function saveLayout(btn) {
  busy(btn, true);
  try { await api('plan_save', {plan: layout}); S.plan = layout; layout = null; toast('Layout saved'); render(); }
  finally { if (btn.isConnected) busy(btn, false); }
}
const snap = (v, step) => Math.round(v / step) * step;
function placeFx(key, x, y) {
  layout.pos[key] = [+x.toFixed(4), +y.toFixed(4)];
  document.querySelectorAll(`.plan [data-k="${key}"]`).forEach(el => { el.style.left = x * 100 + '%'; el.style.top = y * 100 + '%'; });
}
function drawHouse() {
  const [hx, hy, hw, hh] = layout.house, plan = $('.plan');
  $('.house', plan).innerHTML = houseSvg(layout.house);
  const tag = $('[data-tag=house]', plan); tag.style.left = (hx + hw / 2) * 100 + '%'; tag.style.top = (hy + hh / 2) * 100 + '%';
  const [tl, br] = plan.querySelectorAll('.hnd');
  tl.style.left = hx * 100 + '%'; tl.style.top = hy * 100 + '%'; br.style.left = (hx + hw) * 100 + '%'; br.style.top = (hy + hh) * 100 + '%';
}
function layoutDown(e) {
  const t = e.target.closest('.fx, .hnd'); if (!t || e.button > 0) return;
  e.preventDefault(); t.focus({preventScroll: true});
  const plan = e.currentTarget, r = plan.getBoundingClientRect(), key = t.dataset.k, h = t.dataset.h;
  t.classList.add('drag');
  const move = ev => {
    if (ev.pointerId !== e.pointerId) return;
    const x = clamp(snap((ev.clientX - r.left) / r.width, 0.0125), 0.02, 0.98), y = clamp(snap((ev.clientY - r.top) / r.height, 1 / 60), 0.02, 0.98);
    if (key) return placeFx(key, x, y);
    const [hx, hy, hw, hh] = layout.house;
    layout.house = h === 'tl' ? [Math.min(x, hx + hw - 0.1), Math.min(y, hy + hh - 0.1), Math.max(0.1, hx + hw - x), Math.max(0.1, hy + hh - y)]
                              : [hx, hy, Math.max(0.1, x - hx), Math.max(0.1, y - hy)];
    layout.house = layout.house.map(v => +v.toFixed(4));
    drawHouse();
  };
  const up = ev => {
    if (ev.pointerId !== e.pointerId) return;
    document.removeEventListener('pointermove', move); document.removeEventListener('pointerup', up); document.removeEventListener('pointercancel', up);
    t.classList.remove('drag');
  };
  document.addEventListener('pointermove', move); document.addEventListener('pointerup', up); document.addEventListener('pointercancel', up);
}
function nudge(e, key) {
  const d = {ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1]}[e.key]; if (!d) return;
  e.preventDefault();
  const m = e.shiftKey ? 4 : 1, [x, y] = layout.pos[key];
  placeFx(key, clamp(x + d[0] * 0.0125 * m, 0.02, 0.98), clamp(y + d[1] / 60 * m, 0.02, 0.98));
}

// ---- views
function render() {
  $('#app').innerHTML = editing ? editorView() : homeView();
  firstPaint = false;
  renderBar();
}
function renderBar() {
  const p = S.playing;
  if (!p || editing) { if (!$('.sheet')) $('#layer').innerHTML = ''; if (!settling) stopPolling(); return; }
  if ($('.sheet')) return; // the bar comes back when the sheet closes
  const working = p.phase !== 'running';
  const title = p.mode === 'identify' ? 'Blinking to find' : p.mode === 'reset' ? 'Putting lights back to normal' : working ? `Starting ${esc(p.name)}` : 'Now playing';
  const sub = working ? esc(p.phase || 'Connecting') : esc(p.mode === 'reset' ? '' : p.name);
  const bar = $('#layer .playing');
  if (bar && bar.dataset.token === p.token) { // same run: update in place, so the bar does not slide in again every poll
    $('.t', bar).innerHTML = title; $('.n', bar).innerHTML = sub;
    const pr = $('.prog i', bar); if (pr) pr.style.transform = `scaleX(${(p.pct || 2) / 100})`; else if (working) bar.outerHTML = playingBar(p, title, sub, working);
    if (!working && pr) pr.parentNode.remove();
  } else $('#layer').innerHTML = playingBar(p, title, sub, working);
  if (working) startPolling(); else stopPolling();
}
const playingBar = (p, title, sub, working) => `<div class="playing" data-token="${esc(p.token)}" role="status"><div class="busybox"><div class="t">${title}</div><div class="n">${sub}</div>${working ? `<div class="prog"><i style="transform:scaleX(${(p.pct || 2) / 100})"></i></div>` : ''}</div>${p.mode === 'reset' ? '' : `<button class="btn" onclick="stop(this)">${ic('stop')} Stop</button>`}</div>`;
let pollT;
function startPolling() {
  if (pollT) return;
  pollT = setInterval(async () => { try {
    const r = await api('playing'); const was = S.playing && S.playing.phase; S.playing = r.playing;
    if (settling && (r.jobs === 0 || Date.now() - settling > 45000)) { settling = 0; if (Swal.isLoading()) Swal.close(); }
    const starting = r.playing && r.playing.phase !== 'running';
    if (!starting && !settling) { stopPolling(); setTimeout(load, 1500); } // a breather before the status request: the bridge is slow right after a burst
    renderBar();
    if (was !== (r.playing && r.playing.phase) && !editing && !layout) updatePlanCap();
  } catch (e) {} }, 2000);
}
function stopPolling() { clearInterval(pollT); pollT = null; }

// the strip is the brightness slider: the fill is the current level, in the light's color
function strip(pct, color, onchange, label, text = pct + '%') {
  return `<div class="strip" style="--p:${pct}%;--c:${color}"><input type="range" min="1" max="100" value="${pct}" aria-label="${label}" oninput="touched=Date.now();this.parentNode.style.setProperty('--p',this.value+'%');this.nextElementSibling.textContent=this.value+'%'" onchange="${onchange}"><span class="spct tnum">${text}</span></div>`;
}
function chip(sc) {
  const on = !!(S.playing && S.playing.id === sc.id);
  return `<div class="chip ${on ? 'live' : ''}">
    <button class="go" onclick="${on ? 'stop(this)' : `play('${sc.id}',this)`}" aria-label="${on ? 'Stop' : 'Play'} ${esc(sc.name)}"><span class="stripe">${sc.lanes.map(l => `<i style="background:#${l.color}"></i>`).join('')}</span><span>${esc(sc.name)}</span>${ic(on ? 'stop' : 'play')}</button>
    <button class="ed" aria-label="Edit ${esc(sc.name)}" onclick="editScene('${sc.id}')">${ic('pencil')}</button></div>`;
}
const scenesHtml = () => S.scenes.map(chip).join('') + `<button class="chip add" onclick="newScene()">${ic('plus')}New scene</button>`;
function planCap() {
  if (layout) return `<span>Drag each light to its spot. Drag the amber corners to fit the house. Arrow keys nudge.</span>`;
  const p = S.playing;
  if (p && p.id && p.phase === 'running') return `<i class="live"></i><span><b style="color:var(--fg-2)">${esc(p.name)}</b> is playing. The plan shows it as a simulation.</span>`;
  if (!S.plan) return `<span>Spots are guessed from the light names. Tap Edit layout to match your house.</span>`;
  return `<span>Tap a light to change it.</span>`;
}
function updatePlanCap() { const c = $('#planCap'); if (c) c.innerHTML = planCap(); if (!(S.playing && S.playing.phase === 'running')) document.querySelectorAll('.plan [data-k]').forEach(el => { el._c = el._b = el._lit = null; }); }

function homeView() {
  const on = anyOn(), nOn = onLights().length, main = mainLook(), first = onLights()[0];
  const linked = S.lights.some(l => l.status && l.status.link !== false);
  const enter = firstPaint && !REDUCED ? 'enter' : '';
  const grid = ls => `<div class="grid ${enter}">${ls.map((l, i) => lightCard(l, i)).join('')}</div>`;
  const quick = (label, icon, want, cmdBody, msg) => `<button class="btn ${on && main === want ? 'on' : ''}" onclick="cmd({addr:'all',${cmdBody}},'${msg}')">${ic(icon)}${label}</button>`;
  return `<div class="app">
  <aside class="side">
    <div class="brand">${ic('bulb')}Lights</div>
    <nav><h2>Areas</h2>
      ${S.places.map((n, i) => `<button class="nav" aria-current="${i === place}" onclick="setPlace(${i})">${esc(n)}<span class="cnt tnum">${(S.allLights[i] || []).length}</span></button>`).join('')}
      <button class="nav" aria-current="${BOTH()}" onclick="setPlace('all')">Both areas<span class="cnt tnum">${Object.values(S.allLights).reduce((a, l) => a + l.length, 0)}</span></button></nav>
    <div><h2>Scenes</h2>${scenesHtml()}</div>
  </aside>
  <div class="content">
  <header class="bar"><div class="bar-row">
    <div class="placewrap"><select class="place" aria-label="Area" onchange="setPlace(this.value==='all'?'all':+this.value)">${S.places.map((n, i) => `<option value="${i}" ${i === place ? 'selected' : ''}>${esc(n)}</option>`).join('')}<option value="all" ${BOTH() ? 'selected' : ''}>Both areas</option></select>${ic('down')}</div>
    <span class="pill tnum ${nOn ? 'lit' : ''}"><i></i>${nOn} of ${S.lights.length} on</span>
  </div></header>
  <main>
    ${S.permanent ? '' : `<div class="notice">Names and scenes are saved in a temporary folder. In File Station, give user <b>http</b> Read/Write on <b>web/lights/data</b> to keep them.</div>`}
    <div class="scenes-row">${scenesHtml()}</div>
    <div class="home-top">
      <section aria-label="Plan">
        <div class="h2row"><h2>Plan</h2><div class="row">${namesBtn()}${layout ? '' : `<button class="link" onclick="startLayout()">${ic('move')}Edit layout</button>`}</div></div>
        ${planView(layout ? 'layout' : 'home')}
        <p class="plan-cap" id="planCap">${planCap()}</p>
        ${layout ? `<div class="plan-tools">
          <button class="btn ghost" onclick="autoLayout()">${ic('reset')}Auto-place</button>
          <button class="btn" onclick="cancelLayout()">Cancel</button>
          <button class="btn primary" onclick="saveLayout(this)">${ic('check')}Save layout</button></div>` : ''}
      </section>
      <section aria-label="All lights">
        <div class="h2row"><h2>All lights</h2></div>
        <div class="card tile all">
          <div class="top"><div><div class="name">${BOTH() ? 'Both areas' : esc(S.places[place])}</div><div class="status">${!linked ? 'Not reachable · check the lights’ power or the bridge' : on ? main + ' · drag to dim' : 'Off'}</div></div>
            <button class="power ${on ? 'on' : ''}" aria-label="${on ? 'Turn all off' : 'Turn all on'}" onclick="cmd({addr:'all',cmd:'${on ? 'off' : 'on'}'},'All ${on ? 'off' : 'on'}')">${ic('power')}</button></div>
          ${strip(on ? avgBright() : 0, first ? swatch(first.status) : '#ffc070', "cmd({addr:'all',cmd:'bright',bright:+this.value},'Brightness '+this.value+'%')", 'Brightness of all lights', on ? undefined : 'Off')}
          <div class="quick">
            ${quick('Warm', 'flame', 'Warm white', `cmd:'white',kelvin:2700,bright:${on ? avgBright() : 100}`, 'Warm white')}
            ${quick('White', 'sun', 'White', `cmd:'white',kelvin:5000,bright:${on ? avgBright() : 100}`, 'White')}
            ${quick('Cool', 'snow', 'Cool white', `cmd:'white',kelvin:6500,bright:${on ? avgBright() : 100}`, 'Cool white')}
            <label class="btn ${on && main === 'Color' ? 'on' : ''}" style="position:relative">${ic('palette')}Color<input type="color" value="#ff8800" aria-label="Color for all lights" style="position:absolute;inset:0;opacity:0;width:100%;height:100%" onchange="cmd({addr:'all',cmd:'rgb',color:this.value},'Color sent')"></label>
          </div>
        </div>
      </section>
    </div>

    ${BOTH() ? '' : `<div class="h2row"><h2>Lights</h2><button class="link" onclick="reordering=!reordering;render()">${reordering ? ic('check') + 'Done' : ic('sort') + 'Reorder'}</button></div>`}
    ${reordering ? `<p class="hint" style="margin:0 0 12px">Drag a card by its grip, or use the arrows, so the list matches your house left to right. Scenes spread colors in this order.</p>` : ''}
    ${BOTH() ? S.places.map((n, pi) => `<h2>${esc(n)}</h2>${grid(S.lights.filter(l => l.place === pi))}`).join('') : reordering ? `<div class="grid">${S.lights.map((l, i) => `
      <div class="card" data-addr="${l.addr}" style="cursor:default">
        <div class="top"><span class="name">${i + 1}. ${esc(l.name)}</span><span class="grip" aria-label="Drag to move ${esc(l.name)}" onpointerdown="dragStart(event)">${ic('grip')}</span></div>
        <div class="row" style="margin-top:8px;flex-wrap:nowrap">
          <button class="btn icon" aria-label="Move ${esc(l.name)} up" ${i === 0 ? 'disabled' : ''} onclick="moveLight(${i},-1)">${ic('up')}</button>
          <button class="btn icon" aria-label="Move ${esc(l.name)} down" ${i === S.lights.length - 1 ? 'disabled' : ''} onclick="moveLight(${i},1)">${ic('down')}</button></div>
      </div>`).join('')}</div>` : grid(S.lights)}
  </main>
  </div></div>`;
}
function lightCard(l, i = 0) {
  const st = l.status, on = !!(st && st.on), bl = blinking(l);
  return `<div class="card tile ${on ? '' : 'off'}" style="--i:${i}">
    <button class="head" onclick="openLight('${l.key}')" aria-label="${esc(l.name)}, ${describe(st)}">
      <div class="top"><span class="name">${esc(l.name)}</span><span class="num tnum">#${l.addr}</span></div>
      <div class="status">${bl ? 'Blinking' : look(st)}</div>
    </button>
    ${st && st.link !== false ? strip(on ? st.bright : 0, bl ? 'var(--accent)' : on ? swatch(st) : '#ffc070', `cmd({place:${l.place},addr:${l.addr},cmd:'bright',bright:+this.value},'Brightness '+this.value+'%')`, `Brightness of ${esc(l.name)}`, on ? undefined : 'Off')
         : `<div class="strip dashed"><button class="spct" onclick="load()">Retry</button></div>`}
  </div>`;
}

function setPlace(i) { place = i; store.set('place', i); editing = null; reordering = false; layout = null; render(); load(); }
let saveOrderT;
function saveOrder() { clearTimeout(saveOrderT); saveOrderT = setTimeout(async () => { await api('reorder', {order: S.lights.map(l => l.addr)}); toast('Order saved'); }, 600); }
function moveLight(i, dir) {
  const j = i + dir; if (j < 0 || j >= S.lights.length) return;
  [S.lights[i], S.lights[j]] = [S.lights[j], S.lights[i]];
  render(); saveOrder();
}
// drag and drop: a floating copy follows the finger; the real card is moved in the grid as you pass other cards.
// Listeners live on document: moving the card in the DOM would otherwise drop the pointer capture and freeze the drag.
let dragging = false;
function dragStart(e) {
  if (dragging) return; // a second finger or a stuck drag must not start another
  dragging = true;
  const grip = e.currentTarget, card = grip.closest('.card'), grid = card.parentElement;
  e.preventDefault();
  const r = card.getBoundingClientRect(), dx = e.clientX - r.left, dy = e.clientY - r.top;
  const ghost = card.cloneNode(true); ghost.classList.add('ghost');
  Object.assign(ghost.style, {position:'fixed', left: r.left + 'px', top: r.top + 'px', width: r.width + 'px', zIndex: 30, pointerEvents:'none', margin: 0});
  document.body.appendChild(ghost); card.classList.add('drag-src'); document.body.style.userSelect = 'none';
  let lastY = e.clientY;
  const scroller = setInterval(() => { if (lastY > innerHeight - 90) scrollBy(0, 10); else if (lastY < 130) scrollBy(0, -10); }, 16);
  const move = ev => {
    if (ev.pointerId !== e.pointerId) return;
    lastY = ev.clientY;
    ghost.style.transform = `translate(${ev.clientX - r.left - dx}px, ${ev.clientY - r.top - dy}px)`;
    const over = document.elementFromPoint(ev.clientX, ev.clientY)?.closest('.grid > .card');
    if (over && over !== card && over.parentElement === grid) {
      const cards = [...grid.children];
      cards.indexOf(over) > cards.indexOf(card) ? over.after(card) : over.before(card);
    }
  };
  const up = ev => {
    if (ev.type !== 'blur' && ev.pointerId !== e.pointerId) return;
    document.removeEventListener('pointermove', move); document.removeEventListener('pointerup', up); document.removeEventListener('pointercancel', up); window.removeEventListener('blur', up);
    clearInterval(scroller); ghost.remove(); card.classList.remove('drag-src'); document.body.style.userSelect = ''; dragging = false;
    const order = [...grid.children].map(c => +c.dataset.addr);
    S.lights.sort((a, b) => order.indexOf(a.addr) - order.indexOf(b.addr));
    render(); saveOrder();
  };
  document.addEventListener('pointermove', move);
  document.addEventListener('pointerup', up);
  document.addEventListener('pointercancel', up);
  window.addEventListener('blur', up); // e.g. the mouse button is released outside the window
}

// ---- light sheet
function openLight(key) {
  const l = light(key); if (!l) return;
  const st = l.status, addr = l.addr, pl = l.place;
  const bright = st && st.on ? st.bright : 60, kelvin = st && st.mode === 'white' ? st.kelvin : 3000;
  sheet(`
    <div class="sheet-h"><h3 id="lname"><span class="dot" style="background:${swatch(st)}"></span>${esc(l.name)}</h3><button class="btn icon ghost" aria-label="Close" onclick="closeSheet()">${ic('x')}</button></div>
    <div class="sub">${describe(st)} · cloud name “${esc(l.cloudName)}” · <span class="mono">#${addr}</span></div>
    <div id="renameBox" hidden style="margin-top:12px"><label class="lbl" for="rn">Name</label>
      <div class="row" style="flex-wrap:nowrap"><input id="rn" class="text" value="${esc(l.name)}" maxlength="40" autocomplete="off"><button class="btn primary" aria-label="Save name" onclick="rename('${key}',this)">${ic('check')}</button></div>
      <p class="hint">Leave empty to use the app's name.</p></div>
    <div class="row" style="margin-top:16px">
      <button class="btn ${st && st.on ? 'primary' : ''}" onclick="cmd({place:${pl},addr:${addr},cmd:'on'},'On',this)">${ic('power')}On</button>
      <button class="btn" onclick="cmd({place:${pl},addr:${addr},cmd:'off'},'Off',this)">Off</button>
      <button class="btn ${blinking(l) ? 'primary' : ''}" aria-pressed="${blinking(l)}" onclick="identify('${key}',${!!(st && st.on)},this)">${ic('sparkle')}${blinking(l) ? 'Stop blinking' : 'Blink to find'}</button>
      <button class="btn ghost" onclick="$('#renameBox').hidden=false;$('#rn').focus()">${ic('pencil')}Rename</button>
    </div>
    <div class="field"><div class="field-h"><span>Brightness</span><b class="tnum" id="bOne">${bright}%</b></div>
      <input type="range" min="1" max="100" value="${bright}" aria-label="Brightness" oninput="$('#bOne').textContent=this.value+'%'" onchange="cmd({place:${pl},addr:${addr},cmd:'bright',bright:+this.value},'Brightness '+this.value+'%')"></div>
    <div class="field"><div class="field-h"><span>White</span><b>Warm ⟷ cool</b></div>
      <input class="kelvin" type="range" min="2500" max="6535" step="5" value="${kelvin}" aria-label="White temperature" onchange="cmd({place:${pl},addr:${addr},cmd:'white',kelvin:+this.value,bright:100},'White sent')"></div>
    <div class="field"><div class="field-h"><span>Color</span></div>
      <div class="swatches">${SWATCHES.map(c => `<button class="swatch" style="background:#${c}" aria-label="Color ${c}" onclick="cmd({place:${pl},addr:${addr},cmd:'rgb',color:'${c}'},'Color sent')"></button>`).join('')}
        <label class="colorwrap" style="grid-column:span 2">${ic('palette')}Pick<input type="color" value="#ff8800" aria-label="Pick any color" onchange="cmd({place:${pl},addr:${addr},cmd:'rgb',color:this.value},'Color sent')"></label></div></div>`);
}
const blinking = l => !!(S.playing && S.playing.mode === 'identify' && S.playing.addr === l.addr && S.playing.place === l.place);
async function identify(key, wasOn, btn) {
  const l = light(key); busy(btn, true);
  try { const r = await api('identify', {place: l.place, addr: l.addr, wasOn, name: l.name}); toast(r.blinking ? 'Blinking. Tap again to stop.' : 'Stopped'); await load(); openLight(key); }
  finally { if (btn.isConnected) busy(btn, false); }
}
async function rename(key, btn) { const l = light(key); busy(btn, true); try { await api('rename', {place: l.place, addr: l.addr, name: $('#rn').value}); toast('Renamed'); closeSheet(); await load(); } finally {} }
let sheetOpener = null;
function sheet(html) {
  sheetOpener = document.activeElement;
  const layer = $('#layer'); layer.classList.remove('closing');
  layer.innerHTML = `<div class="scrim" onclick="closeSheet()"></div><div class="sheet" role="dialog" aria-modal="true" aria-labelledby="lname"><div class="handle"></div>${html}</div>`;
  $('.sheet .btn.icon').focus({preventScroll: true});
}
function closeSheet() {
  const layer = $('#layer');
  if (!$('.sheet') || layer.classList.contains('closing')) return;
  layer.classList.add('closing');
  setTimeout(() => { layer.classList.remove('closing'); layer.innerHTML = ''; render(); if (sheetOpener && sheetOpener.isConnected) sheetOpener.focus({preventScroll: true}); }, REDUCED ? 120 : 190);
}
document.addEventListener('keydown', e => { if (e.key === 'Escape' && $('.sheet')) closeSheet(); });

// ---- scene editor (full view): paint lights on the plan, which previews the effect live
function newScene() { editing = {id:'', name:'', scope: BOTH() ? 'all' : 'place', effect:'fade', speed:4, lanes:[{color:'ff0000', lights:[]}, {color:'00c000', lights:[]}]}; laneSel = 0; restartPreview(); render(); window.scrollTo(0, 0); }
function editScene(id) {
  editing = JSON.parse(JSON.stringify(S.scenes.find(s => s.id === id)));
  editing.scope = BOTH() ? 'all' : (editing.scope || 'place');
  editing.lanes.forEach(l => l.lights = l.lights.map(k => typeof k === 'number' ? `${editing.place}-${k}` : k)); // old scenes stored bare numbers
  laneSel = 0; restartPreview(); render(); window.scrollTo(0, 0);
}
function restartPreview() { preview.t0 = performance.now(); }
function togglePreview() { preview.on = !preview.on; restartPreview(); render(); }
// lights a scene can use: [{key, name, place}] for this area, or every area
function sceneLights() {
  const places = editing.scope === 'all' ? Object.keys(S.allLights).map(Number) : [editing.place ?? place];
  return places.flatMap(p => S.allLights[p].map(l => ({...l, place: p})));
}
function setScope(scope) { editing.scope = scope; const ok = new Set(sceneLights().map(l => l.key)); editing.lanes.forEach(l => l.lights = l.lights.filter(k => ok.has(k))); render(); }
function applyPreset(name) {
  if (!name) return; const p = PRESETS[name];
  editing.name = editing.name || name; editing.effect = p.effect; editing.speed = p.speed;
  editing.lanes = p.colors.map(c => ({color:c, lights:[]}));
  restartPreview(); spread();
}
function spread() { editing.lanes.forEach(l => l.lights = []); sceneLights().forEach((l, i) => editing.lanes[i % editing.lanes.length].lights.push(l.key)); laneSel = 0; render(); }
function setEffect(k) { editing.effect = k; restartPreview(); render(); }
const speedText = s => `${SPEEDS[s]} · a step every ${period(s).toFixed(1)} s`;
function editorView() {
  const e = editing, laneOf = k => e.lanes.findIndex(l => l.lights.includes(k));
  const lights = sceneLights(), missing = lights.filter(l => laneOf(l.key) < 0).length;
  const card = l => { const li = laneOf(l.key); return `
      <button class="card pick ${li >= 0 ? 'lane' : ''}" style="--lane:#${li >= 0 ? e.lanes[li].color : '000'}" onclick="toggleLane('${l.key}')" aria-pressed="${li === laneSel}">
        <div class="top"><span class="name">${esc(l.name)}</span><span class="num tnum">${li >= 0 ? 'Color ' + (li + 1) : '#' + l.addr}</span></div>
        <div class="status"><span class="dot" style="background:${li >= 0 ? '#' + e.lanes[li].color : 'var(--muted)'}"></span>${li >= 0 ? 'In scene' : 'Not in scene'}</div>
      </button>`; };
  return `
  <header class="editor-bar"><div class="bar-row">
    <button class="btn icon ghost" aria-label="Back" onclick="editing=null;render()">${ic('back')}</button>
    <input id="sn" class="title-in" placeholder="Scene name" aria-label="Scene name" value="${esc(e.name)}" maxlength="40" autocomplete="off" oninput="editing.name=this.value">
  </div></header>
  <main class="ed" style="padding-bottom:110px">
    <div class="ed-grid">
      <section class="ed-plan" aria-label="Preview">
        <div class="h2row" style="margin-top:4px"><h2>${e.id ? 'Edit scene' : 'New scene'} · preview</h2><div class="row">${namesBtn()}
          <button class="link" aria-pressed="${preview.on}" onclick="togglePreview()">${ic(preview.on ? 'pause' : 'play')}${preview.on ? 'Pause' : 'Play'}</button></div></div>
        ${planView('paint')}
        <p class="plan-cap">${preview.on ? `<i class="live"></i>` : ''}<span>Preview only. Nothing is sent to the lights until you play the scene.</span></p>
        <div class="h2row"><h2>Colors</h2><button class="link" onclick="spread()">Spread evenly</button></div>
        <div class="lanes">
          ${e.lanes.map((l, i) => `<div class="lane-chip" role="button" aria-pressed="${i === laneSel}" aria-label="Color ${i + 1}, ${l.lights.length} lights" tabindex="0" onclick="laneSel=${i};render()" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();laneSel=${i};render()}">
            <label class="colorwrap" style="--c:#${l.color}" aria-label="Change color ${i + 1}" onclick="event.stopPropagation()"><input type="color" value="#${l.color}" onchange="editing.lanes[${i}].color=this.value.slice(1);render()"></label>
            <span class="n tnum">${l.lights.length}</span>
            ${e.lanes.length > 1 ? `<button class="x" aria-label="Remove color ${i + 1}" onclick="event.stopPropagation();editing.lanes.splice(${i},1);laneSel=0;render()">${ic('x')}</button>` : ''}</div>`).join('')}
          ${e.lanes.length < 8 ? `<button class="btn ghost" onclick="editing.lanes.push({color:'ffffff',lights:[]});laneSel=editing.lanes.length-1;render()">${ic('plus')}Add color</button>` : ''}
        </div>
        <p class="hint">Pick a color, then tap or drag across lights on the plan.${missing ? ` ${missing} light${missing > 1 ? 's are' : ' is'} not in this scene yet.` : ''}</p>
      </section>
      <section aria-label="Settings">
        <div class="h2row first-desk"><h2>Effect</h2></div>
        <div class="effects" role="radiogroup" aria-label="Effect">
          ${Object.entries(EFFECTS).map(([k, [name, what, icon]]) => `<button class="opt" role="radio" aria-checked="${k === e.effect}" onclick="setEffect('${k}')">${ic(icon)}<span><b>${name}</b><small>${what}</small></span></button>`).join('')}
        </div>
        <div class="field"><div class="field-h"><span>Speed</span><b id="spd" class="tnum">${speedText(e.speed)}</b></div>
          <input type="range" min="1" max="10" value="${e.speed}" aria-label="Speed" oninput="editing.speed=+this.value;$('#spd').textContent=speedText(this.value)"></div>
        ${BOTH() ? '' : `<label class="lbl">Lights</label>
        <div class="seg" role="tablist" style="display:inline-flex">
          <button role="tab" aria-selected="${e.scope !== 'all'}" onclick="setScope('place')">${esc(S.places[place])} only</button>
          <button role="tab" aria-selected="${e.scope === 'all'}" onclick="setScope('all')">Both areas</button></div>`}
        <label class="lbl" for="sp">Start from a preset</label>
        <select id="sp" class="text" onchange="applyPreset(this.value);this.value=''"><option value="">Choose…</option>${Object.keys(PRESETS).map(p => `<option>${p}</option>`).join('')}</select>
      </section>
    </div>

    <div class="h2row"><h2>Or pick from the list</h2></div>
    ${e.scope === 'all'
      ? Object.keys(S.allLights).map(p => `<h2>${esc(S.places[p])}</h2><div class="grid">${lights.filter(l => l.place === +p).map(card).join('')}</div>`).join('')
      : `<div class="grid">${lights.map(card).join('')}</div>`}
    ${e.id ? `<div style="margin-top:32px"><button class="btn danger" onclick="deleteScene()">Delete this scene</button></div>` : ''}
  </main>
  <div class="actions"><div class="row">
    <button class="btn" onclick="saveScene(false,this)">Save</button>
    <button class="btn primary" onclick="saveScene(true,this)">${ic('play')}Save &amp; play</button>
  </div></div>`;
}
function setLane(key, li) { editing.lanes.forEach(l => { const i = l.lights.indexOf(key); if (i >= 0) l.lights.splice(i, 1); }); if (li >= 0) editing.lanes[li].lights.push(key); }
function toggleLane(key) { setLane(key, editing.lanes[laneSel].lights.includes(key) ? -1 : laneSel); render(); }
// paint on the plan: the first light decides paint or erase; every light the finger passes over follows. One render at the end.
function fxAt(plan, x, y) {
  let best = null, bd = 26 * 26;
  plan.querySelectorAll('.fx:not(.other)').forEach(el => { const r = el.getBoundingClientRect(), dx = r.left + r.width / 2 - x, dy = r.top + r.height / 2 - y, d = dx * dx + dy * dy; if (d < bd) { bd = d; best = el.dataset.k; } });
  return best;
}
function paintStart(e) {
  if (e.button > 0) return;
  const plan = e.currentTarget, first = fxAt(plan, e.clientX, e.clientY);
  if (!first) return;
  e.preventDefault();
  const erase = editing.lanes[laneSel].lights.includes(first), seen = new Set(), color = editing.lanes[laneSel].color;
  const apply = k => {
    if (!k || seen.has(k)) return; seen.add(k);
    setLane(k, erase ? -1 : laneSel);
    plan.querySelectorAll(`[data-k="${k}"]`).forEach(el => { el.classList.toggle('out', erase); if (!erase) el.style.setProperty('--c', '#' + color); el._c = el._b = el._lit = null; el.classList.remove('lit'); });
    restartPreview();
  };
  apply(first);
  const move = ev => { if (ev.pointerId === e.pointerId) apply(fxAt(plan, ev.clientX, ev.clientY)); };
  const up = ev => { if (ev.pointerId !== e.pointerId) return; document.removeEventListener('pointermove', move); document.removeEventListener('pointerup', up); document.removeEventListener('pointercancel', up); render(); };
  document.addEventListener('pointermove', move); document.addEventListener('pointerup', up); document.addEventListener('pointercancel', up);
}
async function saveScene(andPlay, btn) {
  if (!editing.name.trim()) { toast('Give the scene a name', true); $('#sn').focus(); return; }
  busy(btn, true);
  try { const r = await api('scene_save', {place: BOTH() ? (editing.place ?? 0) : place, scene: editing}); editing = null; toast('Saved'); if (andPlay) await play(r.id); else await load(); }
  finally { if (btn.isConnected) busy(btn, false); }
}
async function deleteScene() {
  const r = await Swal.fire({titleText: `Delete “${editing.name}”?`, text: 'This cannot be undone.', showCancelButton: true, confirmButtonText: 'Delete', cancelButtonText: 'Keep', reverseButtons: true});
  if (!r.isConfirmed) return; await api('scene_delete', {id: editing.id}); editing = null; toast('Deleted'); load(); }
async function play(id, btn) { if (btn) busy(btn, true); try { await api('play', {id}); const r = await api('playing'); S.playing = r.playing; render(); } finally { if (btn && btn.isConnected) busy(btn, false); } }
async function stop(btn) { busy(btn, true); try { await api('stop'); const r = await api('playing'); S.playing = r.playing; render(); if (!r.playing) load(); } finally {} }

// ---- boot
$('#app').innerHTML = `<header class="bar"><div class="bar-row"><h1>Lights</h1></div></header><main><h2>Plan</h2><div class="skel" style="aspect-ratio:4/3"></div><h2>Lights</h2><div class="grid"><div class="skel"></div><div class="skel"></div><div class="skel"></div><div class="skel"></div></div></main>`;
load().catch(e => { $('#app').innerHTML = `<main><div class="notice">Could not reach the lights service.<br><small>${esc(e.message)}</small><br><button class="link" onclick="boot()">Try again</button></div></main>`; });
function boot() { $('#app').innerHTML = '<main><div class="skel" style="min-height:180px"></div></main>'; load().catch(e => toast('Still failing: ' + e.message, true)); }
setInterval(() => { if (!editing && !reordering && !settling && !layout && !$('.sheet') && document.visibilityState === 'visible') load(); }, 30000);
</script>
</html>
