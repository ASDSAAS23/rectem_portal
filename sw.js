/**
 * RECTEM Portal - Service Worker for Push Notifications
 * v3 - Logo display + click-to-login redirect
 */

const CACHE_VERSION = 'rectem-sw-v3';
const BASE = '/rectem_portal';
const LOGO = BASE + '/rectem-logo.png';

// Install event - immediately activate
self.addEventListener('install', (event) => {
  console.log('[SW] Installing v3');
  self.skipWaiting();
});

// Activate event - take control immediately
self.addEventListener('activate', (event) => {
  console.log('[SW] Activating v3');
  event.waitUntil(self.clients.claim());
});

// Push event - show notification with RECTEM logo
self.addEventListener('push', (event) => {
  console.log('[SW] Push received');

  let data = {
    title: 'RECTEM Portal',
    body: 'You have a new notification.',
    url: BASE + '/student/notifications.php',
  };

  if (event.data) {
    try {
      const payload = event.data.json();
      data = { ...data, ...payload };
    } catch (e) {
      data.body = event.data.text();
    }
  }

  const options = {
    body: data.body,
    icon: LOGO,
    badge: LOGO,
    image: undefined, // No large image — keeps it clean
    vibrate: [200, 100, 200],
    data: {
      url: data.url || BASE + '/student/notifications.php',
    },
    actions: [
      { action: 'view', title: '👁️ View' },
      { action: 'dismiss', title: '✕ Dismiss' },
    ],
    tag: 'rectem-' + Date.now(),
    renotify: true,
    requireInteraction: true,
    silent: false,
  };

  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// Notification click — redirect through login page with redirect parameter
self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  if (event.action === 'dismiss') return;

  // The target page the user should end up on after login
  const targetPage = event.notification.data?.url || BASE + '/student/notifications.php';

  // Build the login URL with a redirect parameter
  // If user is already logged in, the redirect page will handle it
  const loginUrl = BASE + '/login.php?redirect=' + encodeURIComponent(targetPage);

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
      // Check if there's already a portal window open
      for (const client of clients) {
        if (client.url.includes('/rectem_portal/') && 'focus' in client) {
          // If already on the portal, navigate directly to the target page
          client.navigate(targetPage);
          return client.focus();
        }
      }
      // No portal window open — open login page with redirect
      return self.clients.openWindow(loginUrl);
    })
  );
});

// Notification close event
self.addEventListener('notificationclose', (event) => {
  console.log('[SW] Notification dismissed');
});

// Ping-pong for health checks
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'PING') {
    event.ports[0].postMessage({ type: 'PONG', status: 'alive' });
  }
});
