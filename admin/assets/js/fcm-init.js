/**
 * /assets/js/fcm-init.js
 * تسجيل FCM Token وحفظه في user_devices
 * يُحمَّل مرة واحدة في footer.php بعد تسجيل الدخول
 */
(function () {
    'use strict';

    // ── إعداد Firebase (اكمل القيم من Firebase Console) ──────────────
    const FIREBASE_CONFIG = {
        apiKey:            window.APP_CONFIG?.FCM_API_KEY            || 'YOUR_API_KEY',
        authDomain:        window.APP_CONFIG?.FCM_AUTH_DOMAIN        || 'YOUR_PROJECT.firebaseapp.com',
        projectId:         window.APP_CONFIG?.FCM_PROJECT_ID         || 'YOUR_PROJECT_ID',
        messagingSenderId: window.APP_CONFIG?.FCM_MESSAGING_SENDER_ID|| 'YOUR_SENDER_ID',
        appId:             window.APP_CONFIG?.FCM_APP_ID             || 'YOUR_APP_ID',
    };

    const VAPID_KEY     = window.APP_CONFIG?.FCM_VAPID_KEY  || 'YOUR_VAPID_KEY';
    const API_DEVICES   = (window.APP_CONFIG?.API_BASE || '/api') + '/user_devices';
    const SW_PATH       = '/firebase-messaging-sw.js';
    const CSRF_TOKEN    = () => window.APP_CONFIG?.CSRF_TOKEN || window.CSRF_TOKEN || '';
    const STORAGE_KEY   = 'fcm_token_registered';

    // ── نقطة الدخول ─────────────────────────────────────────────────
    async function init() {
        // لا تشغّل إذا المتصفح لا يدعم Service Workers أو Notifications
        if (!('serviceWorker' in navigator) || !('Notification' in window)) {
            console.info('[FCM] Browser does not support push notifications.');
            return;
        }

        // إذا كان المستخدم رفض الإذن سابقاً لا تسأل مرة أخرى
        if (Notification.permission === 'denied') {
            console.info('[FCM] Notification permission denied by user.');
            return;
        }

        try {
            // تحميل Firebase SDK (يجب أن يكون محمّلاً في الصفحة قبل هذا الملف)
            if (typeof firebase === 'undefined') {
                console.warn('[FCM] Firebase SDK not loaded.');
                return;
            }

            // تهيئة Firebase (تجنّب التهيئة المزدوجة)
            if (!firebase.apps.length) {
                firebase.initializeApp(FIREBASE_CONFIG);
            }

            const messaging = firebase.messaging();

            // تسجيل Service Worker يدوياً حتى نتحكم في مساره
            const swRegistration = await navigator.serviceWorker.register(SW_PATH, {
                scope: '/',
            });
            console.info('[FCM] Service Worker registered.');

            // طلب إذن الإشعارات (يظهر popup للمستخدم فقط إذا لم يقرر بعد)
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                console.info('[FCM] Permission not granted:', permission);
                return;
            }

            // جلب FCM Token
            const token = await messaging.getToken({
                vapidKey: VAPID_KEY,
                serviceWorkerRegistration: swRegistration,
            });

            if (!token) {
                console.warn('[FCM] No token received.');
                return;
            }

            console.info('[FCM] Token received:', token.substring(0, 20) + '...');

            // حفظ في DB (فقط إذا تغيّر التوكن)
            const lastToken = localStorage.getItem(STORAGE_KEY);
            if (lastToken !== token) {
                await registerTokenOnServer(token);
                localStorage.setItem(STORAGE_KEY, token);
            }

            // استقبال الإشعارات والتطبيق مفتوح (Foreground)
            messaging.onMessage((payload) => {
                console.info('[FCM] Foreground message:', payload);
                handleForegroundMessage(payload);
            });

            // عند تجديد التوكن تلقائياً
            messaging.onTokenRefresh(async () => {
                const newToken = await messaging.getToken({
                    vapidKey: VAPID_KEY,
                    serviceWorkerRegistration: swRegistration,
                });
                if (newToken) {
                    await registerTokenOnServer(newToken);
                    localStorage.setItem(STORAGE_KEY, newToken);
                    console.info('[FCM] Token refreshed.');
                }
            });

        } catch (err) {
            console.error('[FCM] Init error:', err);
        }
    }

    // ── حفظ التوكن في user_devices ──────────────────────────────────
    async function registerTokenOnServer(token) {
        try {
            const body = {
                fcm_token:   token,
                device_type: detectDeviceType(),
                device_name: navigator.userAgent.substring(0, 100),
                csrf_token:  CSRF_TOKEN(),
            };

            const res = await fetch(API_DEVICES, {
                method:      'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type':    'application/json',
                    'X-Requested-With':'XMLHttpRequest',
                    'X-CSRF-Token':    CSRF_TOKEN(),
                },
                body: JSON.stringify(body),
            });

            const json = await res.json();

            if (res.ok && json.success) {
                console.info('[FCM] Token registered on server.');
            } else {
                console.warn('[FCM] Server registration failed:', json.message || res.status);
            }
        } catch (err) {
            console.error('[FCM] registerTokenOnServer error:', err);
        }
    }

    // ── عرض الإشعار عند وصوله والتطبيق مفتوح ────────────────────────
    function handleForegroundMessage(payload) {
        const title = payload.notification?.title || 'إشعار جديد';
        const body  = payload.notification?.body  || '';
        const icon  = payload.notification?.icon  || '/frontend/assets/images/logo.png';

        // استخدام toast إن وُجد (من AdminFramework)
        if (window.AdminFramework?.toast) {
            window.AdminFramework.toast(`${title}: ${body}`, 'info');
        } else if (window.showToast) {
            window.showToast(`${title}: ${body}`, 'info');
        } else {
            // Fallback: native browser notification
            if (Notification.permission === 'granted') {
                new Notification(title, { body, icon });
            }
        }

        // تحديث عداد الإشعارات في الـ UI إن وُجد
        if (typeof window.incrementNotifBadge === 'function') {
            window.incrementNotifBadge();
        }

        // رفع event مخصص يمكن للصفحة الاستماع إليه
        window.dispatchEvent(new CustomEvent('fcm:message', { detail: payload }));
    }

    // ── كشف نوع الجهاز ───────────────────────────────────────────────
    function detectDeviceType() {
        const ua = navigator.userAgent.toLowerCase();
        if (/android/.test(ua))              return 'android';
        if (/iphone|ipad|ipod/.test(ua))     return 'ios';
        if (/mobile|tablet/.test(ua))        return 'mobile';
        return 'web';
    }

    // ── حذف التوكن عند تسجيل الخروج ─────────────────────────────────
    window.fcmDeregister = async function () {
        const token = localStorage.getItem(STORAGE_KEY);
        if (!token) return;

        try {
            await fetch(API_DEVICES + '/deregister', {
                method:      'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type':    'application/json',
                    'X-Requested-With':'XMLHttpRequest',
                    'X-CSRF-Token':    CSRF_TOKEN(),
                },
                body: JSON.stringify({ fcm_token: token, csrf_token: CSRF_TOKEN() }),
            });
            localStorage.removeItem(STORAGE_KEY);
            console.info('[FCM] Token deregistered.');
        } catch (err) {
            console.error('[FCM] Deregister error:', err);
        }
    };

    // ── تشغيل بعد تحميل الصفحة ──────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // تأخير بسيط حتى لا يُبطئ تحميل الصفحة الأولي
        setTimeout(init, 1500);
    }

})();
