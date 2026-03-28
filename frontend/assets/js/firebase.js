/**
 * /frontend/assets/js/firebase.js
 * تسجيل FCM Token وحفظه في user_devices
 * يُحمَّل في footer.php بعد تسجيل الدخول
 */
(function () {
    'use strict';

    // ── إعداد Firebase (القيم من window.APP_CONFIG) ──────────
    const FIREBASE_CONFIG = {
        apiKey:            window.APP_CONFIG?.FCM_API_KEY            || '',
        authDomain:        window.APP_CONFIG?.FCM_AUTH_DOMAIN        || '',
        projectId:         window.APP_CONFIG?.FCM_PROJECT_ID         || '',
        messagingSenderId: window.APP_CONFIG?.FCM_MESSAGING_SENDER_ID|| '',
        appId:             window.APP_CONFIG?.FCM_APP_ID             || '',
    };

    const VAPID_KEY     = window.APP_CONFIG?.FCM_VAPID_KEY  || '';
    const API_DEVICES   = (window.APP_CONFIG?.API_BASE || '/api') + '/user_devices';
    const SW_PATH       = '/firebase-messaging-sw.js';
    const STORAGE_KEY   = 'fcm_token_registered';

    // ── نقطة الدخول ─────────────────────────────────────────
    async function init() {
        if (!('serviceWorker' in navigator) || !('Notification' in window)) {
            return;
        }

        if (Notification.permission === 'denied') {
            return;
        }

        if (!FIREBASE_CONFIG.projectId) {
            return;
        }

        try {
            if (typeof firebase === 'undefined') {
                return;
            }

            if (!firebase.apps.length) {
                firebase.initializeApp(FIREBASE_CONFIG);
            }

            const messaging = firebase.messaging();

            const swRegistration = await navigator.serviceWorker.register(SW_PATH, {
                scope: '/',
            });

            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                return;
            }

            const tokenOpts = { serviceWorkerRegistration: swRegistration };
            if (VAPID_KEY && VAPID_KEY !== 'REPLACE_WITH_YOUR_VAPID_KEY') {
                tokenOpts.vapidKey = VAPID_KEY;
            }

            const token = await messaging.getToken(tokenOpts);
            if (!token) {
                return;
            }

            // حفظ فقط إذا تغيّر التوكن
            const lastToken = localStorage.getItem(STORAGE_KEY);
            if (lastToken !== token) {
                await registerTokenOnServer(token);
                localStorage.setItem(STORAGE_KEY, token);
            }

            // استقبال الإشعارات والتطبيق مفتوح (Foreground)
            messaging.onMessage(function (payload) {
                handleForegroundMessage(payload);
            });

        } catch (err) {
            // silent fail for push notifications
        }
    }

    // ── حفظ التوكن في user_devices ──────────────────────────
    async function registerTokenOnServer(token) {
        try {
            var body = {
                fcm_token:   token,
                device_type: detectDeviceType(),
                device_name: navigator.userAgent.substring(0, 100),
            };

            var res = await fetch(API_DEVICES, {
                method:      'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type':     'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body),
            });

            await res.json();
        } catch (err) {
            // silent fail
        }
    }

    // ── عرض الإشعار عند وصوله والتطبيق مفتوح ─────────────
    function handleForegroundMessage(payload) {
        var title = (payload.notification && payload.notification.title) || '';
        var body  = (payload.notification && payload.notification.body)  || '';
        var icon  = (payload.notification && payload.notification.icon)  || '/frontend/assets/images/logo.png';

        if (Notification.permission === 'granted' && title) {
            new Notification(title, { body: body, icon: icon });
        }

        // تحديث عداد الإشعارات
        if (typeof window.incrementNotifBadge === 'function') {
            window.incrementNotifBadge();
        }

        window.dispatchEvent(new CustomEvent('fcm:message', { detail: payload }));
    }

    // ── كشف نوع الجهاز ─────────────────────────────────────
    function detectDeviceType() {
        var ua = navigator.userAgent.toLowerCase();
        if (/android/.test(ua))          return 'android';
        if (/iphone|ipad|ipod/.test(ua)) return 'ios';
        if (/mobile|tablet/.test(ua))    return 'other';
        return 'web';
    }

    // ── حذف التوكن عند تسجيل الخروج ─────────────────────────
    window.fcmDeregister = async function () {
        var token = localStorage.getItem(STORAGE_KEY);
        if (!token) return;

        try {
            await fetch(API_DEVICES + '?fcm_token=' + encodeURIComponent(token), {
                method:      'DELETE',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            localStorage.removeItem(STORAGE_KEY);
        } catch (err) {
            // silent fail
        }
    };

    // ── تشغيل بعد تحميل الصفحة ──────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(init, 2000);
        });
    } else {
        setTimeout(init, 2000);
    }

})();