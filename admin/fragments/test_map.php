<?php
/**
 * admin/fragments/test_map.php
 * ملف اختبار بسيط لعزل مشكلة ظهور الخريطة و Leaflet.Draw
 *
 * يمكن الوصول إليه مباشرةً:  /admin/fragments/test_map.php
 * أو عبر AJAX (fetchAndInsert) كأي fragment آخر.
 *
 * نمط التحميل هنا هو المرجع الذي تستخدمه delivery.php / delivery.js:
 *   - إنشاء <script> في document.head مع ربط .onload
 *   - تحميل Leaflet ثم Leaflet.Draw بشكل متسلسل (serial chain)
 *   - فقط بعد تأكيد وجود window.L و window.L.Draw يتم تهيئة الخريطة
 */
$isFragment = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || isset($_GET['embedded']);
if (!$isFragment) { require_once __DIR__ . '/../includes/header.php'; }
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css" crossorigin="">

<style>
#testMapContainer { padding: 1rem; }
#testMap { height: 480px; width: 100%; border-radius: 8px; border: 1px solid #ccc; }
#testMapStatus { margin-top: .5rem; font-size: .85rem; color: #555; }
</style>

<div id="testMapContainer">
    <h2>Test Map — Leaflet + Draw</h2>
    <div id="testMap"></div>
    <p id="testMapStatus">جاري تحميل الخريطة…</p>
</div>

<script>
(function () {
    'use strict';

    var LEAFLET_JS  = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    var DRAW_JS     = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js';
    var POLL_MS     = 50;
    var MAX_TICKS   = 200; // 200 × 50 ms = 10 s

    /** إضافة <script> إلى <head> مع polling حتى يتحقق checkFn() */
    function loadScript(src, checkFn, cb) {
        if (checkFn()) { cb(); return; }
        var existing = document.querySelector('script[src="' + src + '"]');
        if (!existing) {
            var s = document.createElement('script');
            s.src = src;
            s.crossOrigin = 'anonymous';
            s.onerror = function () { console.error('[TestMap] فشل تحميل: ' + src); };
            document.head.appendChild(s);
        }
        var ticks = 0;
        var iv = setInterval(function () {
            if (checkFn()) { clearInterval(iv); cb(); return; }
            if (++ticks >= MAX_TICKS) {
                clearInterval(iv);
                console.error('[TestMap] انتهت المهلة: ' + src);
                cb(); // استمر رغم الخطأ
            }
        }, POLL_MS);
    }

    function ensureLeaflet(cb) {
        loadScript(LEAFLET_JS, function () { return !!window.L; }, function () {
            loadScript(DRAW_JS, function () { return !!(window.L && window.L.Draw); }, cb);
        });
    }

    function initMap() {
        var status = document.getElementById('testMapStatus');
        if (typeof L === 'undefined') {
            if (status) status.textContent = 'خطأ: لم يتم تحميل Leaflet';
            return;
        }
        if (status) status.textContent = '✓ Leaflet ' + L.version + ' محمّل — جاري رسم الخريطة…';

        var map = L.map('testMap').setView([24.7136, 46.6753], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/">OpenStreetMap</a>',
            maxZoom: 19
        }).addTo(map);

        var drawnItems = L.featureGroup().addTo(map);

        if (L.Control && L.Control.Draw) {
            new L.Control.Draw({
                position: 'topright',
                draw: {
                    polygon:      { allowIntersection: false, shapeOptions: { color: '#2563eb', fillOpacity: 0.15 } },
                    circle:       { shapeOptions: { color: '#2563eb', fillOpacity: 0.15 } },
                    rectangle:    false,
                    marker:       false,
                    polyline:     false,
                    circlemarker: false
                },
                edit: { featureGroup: drawnItems, remove: true }
            }).addTo(map);

            map.on(L.Draw.Event.CREATED, function (e) {
                drawnItems.clearLayers();
                drawnItems.addLayer(e.layer);
            });
        }

        // تعديل الحجم بعد الرسم لضمان ظهور البلاطات
        [100, 400, 900].forEach(function (ms) {
            setTimeout(function () { map.invalidateSize(); }, ms);
        });

        if (status) status.textContent = '✓ الخريطة تعمل بنجاح';
    }

    ensureLeaflet(initMap);
})();
</script>
<?php if (!$isFragment): require_once __DIR__ . '/../includes/footer.php'; endif; ?>
