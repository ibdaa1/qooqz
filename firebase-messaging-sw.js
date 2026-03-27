// htdocs/firebase-messaging-sw.js
// Service Worker للإشعارات في الخلفية (Background Push Notifications)
// يجب أن يكون في جذر الموقع: /firebase-messaging-sw.js

importScripts("https://www.gstatic.com/firebasejs/10.7.0/firebase-app-compat.js");
importScripts("https://www.gstatic.com/firebasejs/10.7.0/firebase-messaging-compat.js");

firebase.initializeApp({
    apiKey:            "AIzaSyDgH1GCINXK-sqmhtKZizQH_tmf7C-u0sA",
    authDomain:        "qooqz-2011.firebaseapp.com",
    projectId:         "qooqz-2011",
    storageBucket:     "qooqz-2011.firebasestorage.app",
    messagingSenderId: "724587252286",
    appId:             "1:724587252286:web:71de19d19446a960c6df6f",
});

const messaging = firebase.messaging();

// استقبال الإشعار عندما يكون التطبيق مغلقاً أو في الخلفية
messaging.onBackgroundMessage(function (payload) {
    console.log("[SW] Background message received:", payload);

    const title = payload.notification?.title || "إشعار جديد";
    const body  = payload.notification?.body  || "";
    const icon  = payload.notification?.icon  || "/frontend/assets/images/logo.png";
    const badge = "/frontend/assets/images/logo.png";
    const data  = payload.data || {};

    // بناء رابط النقر (إذا أُرسل في data)
    const clickUrl = data.click_url || data.url || "/";

    self.registration.showNotification(title, {
        body,
        icon,
        badge,
        data: { url: clickUrl, ...data },
        // إظهار الإشعار فوق باقي الإشعارات
        requireInteraction: false,
        // تجميع الإشعارات من نفس المصدر
        tag: data.notification_id || "qooqz-notification",
    });
});

// عند النقر على الإشعار
self.addEventListener("notificationclick", function (event) {
    event.notification.close();

    const url = event.notification.data?.url || "/";

    event.waitUntil(
        clients
            .matchAll({ type: "window", includeUncontrolled: true })
            .then(function (clientList) {
                // إذا كانت هناك نافذة مفتوحة → اذهب إليها
                for (const client of clientList) {
                    if (client.url.includes(self.location.origin) && "focus" in client) {
                        client.navigate(url);
                        return client.focus();
                    }
                }
                // لا توجد نافذة مفتوحة → افتح نافذة جديدة
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            })
    );
});
