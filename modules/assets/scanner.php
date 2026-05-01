<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

if (!isLoggedIn() || !hasPermission('scan_assets')) {
    ?><!DOCTYPE html>
    <html lang="nl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Geen toegang</title>
    <style>body{font-family:sans-serif;background:#f8fafc;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}.box{text-align:center;padding:30px;background:white;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,.1);max-width:320px}a{color:#2563eb;text-decoration:none;font-weight:600}</style>
    </head><body><div class="box"><h2>Geen toegang</h2><p style="color:#6b7280;margin:15px 0">Je hebt geen rechten voor de scanner.</p><a href="<?= BASE_URL ?>/index.php">Inloggen</a></div></body></html>
    <?php
    exit;
}
?><!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>AssetTrack Scanner</title>
    <meta name="theme-color" content="#1a2332">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; background:#0f172a; color:white; min-height:100vh; display:flex; flex-direction:column; }
        .topbar { background:#1a2332; padding:14px 18px; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:10; }
        .topbar h1 { font-size:1.1rem; font-weight:700; }
        .topbar a { color:#cbd5e1; text-decoration:none; font-size:0.9rem; }
        .scanner-wrapper { flex:1; display:flex; flex-direction:column; align-items:center; padding:20px; gap:16px; }
        #reader { width:100%; max-width:400px; border-radius:16px; overflow:hidden; background:#000; }
        #reader video { border-radius:16px; }
        #reader img { display:none; }
        .status-box { width:100%; max-width:400px; background:#1e293b; border-radius:12px; padding:14px 18px; text-align:center; font-size:0.95rem; color:#94a3b8; min-height:50px; display:flex; align-items:center; justify-content:center; }
        .status-box.success { background:#064e3b; color:#6ee7b7; font-weight:600; }
        .status-box.error { background:#7f1d1d; color:#fca5a5; }
        .controls { width:100%; max-width:400px; display:flex; gap:12px; }
        .btn { flex:1; padding:14px; border:none; border-radius:12px; font-size:1rem; font-weight:600; cursor:pointer; color:white; }
        .btn-switch { background:#2563eb; }
        .btn-stop { background:#dc2626; }
        .btn-start { background:#059669; width:100%; max-width:400px; }
        .hint { color:#475569; font-size:0.82rem; text-align:center; max-width:300px; line-height:1.5; }
    </style>
</head>
<body>
<div class="topbar">
    <h1>📷 AssetTrack Scanner</h1>
    <a href="<?= BASE_URL ?>/modules/assets/">← Terug</a>
</div>

<div class="scanner-wrapper">
    <div id="reader"></div>
    <div class="status-box" id="statusBox">Klik op Start om de camera te activeren</div>
    <div class="controls" id="runningControls" style="display:none;">
        <button class="btn btn-switch" onclick="switchCamera()">🔄 Camera wisselen</button>
        <button class="btn btn-stop" onclick="stopScanner()">⏹ Stoppen</button>
    </div>
    <button class="btn btn-start" id="startBtn" onclick="startScanner()">▶ Scanner starten</button>
    <p class="hint">Richt de camera op een AssetTrack QR-code. De app detecteert hem automatisch.</p>
</div>

<script>
let html5QrCode = null;
let currentCamera = 'environment';
let scanning = false;

function setStatus(msg, type) {
    const box = document.getElementById('statusBox');
    box.textContent = msg;
    box.className = 'status-box' + (type ? ' ' + type : '');
}

async function startScanner() {
    document.getElementById('startBtn').style.display = 'none';
    setStatus('Camera starten...');

    try {
        html5QrCode = new Html5Qrcode("reader");

        await html5QrCode.start(
            { facingMode: currentCamera },
            { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1.0 },
            onScanSuccess,
            () => {} // stille fout
        );

        scanning = true;
        document.getElementById('runningControls').style.display = 'flex';
        setStatus('📷 Scanner actief — richt op QR-code');

    } catch (err) {
        setStatus('Camera kon niet starten. Controleer camera-toegang.', 'error');
        document.getElementById('startBtn').style.display = 'block';
        console.error(err);
    }
}

function onScanSuccess(decodedText) {
    if (!scanning) return;
    scanning = false;
    setStatus('✅ QR-code gevonden! Laden...', 'success');

    if (html5QrCode) {
        html5QrCode.stop().then(() => {
            window.location.href = decodedText;
        }).catch(() => {
            window.location.href = decodedText;
        });
    } else {
        window.location.href = decodedText;
    }
}

async function stopScanner() {
    scanning = false;
    if (html5QrCode) {
        try { await html5QrCode.stop(); html5QrCode.clear(); } catch(e) {}
        html5QrCode = null;
    }
    document.getElementById('runningControls').style.display = 'none';
    document.getElementById('startBtn').style.display = 'block';
    setStatus('Scanner gestopt');
}

async function switchCamera() {
    await stopScanner();
    currentCamera = currentCamera === 'environment' ? 'user' : 'environment';
    await startScanner();
}

window.addEventListener('beforeunload', () => {
    if (html5QrCode) { html5QrCode.stop().catch(() => {}); }
});
</script>
</body>
</html>
