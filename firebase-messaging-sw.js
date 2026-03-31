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
    const data  = payload.data || {};
    // Use icon from notification, then from data payload, then default
    const icon  = payload.notification?.icon  || data.icon_url || "/admin/assets/img/default-image.png";
    const badge = "/admin/assets/img/default-image.png";
    // Support large image display from notification or data
    const image = payload.notification?.image || data.image_url || "";

    // بناء رابط النقر (إذا أُرسل في data)
    const clickUrl = data.click_url || data.url || "/";

    var options = {
        body: body,
        icon: icon,
        badge: badge,
        data: Object.assign({ url: clickUrl }, data),
        requireInteraction: false,
        tag: data.notification_id || "qooqz-notification",
    };

    // Add large image if available (shows as banner in notification)
    if (image) {
        options.image = image;
    }

    self.registration.showNotification(title, options);
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
