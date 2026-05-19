/**
 * RECTEM Portal - Client Notification System
 * Handles: Service Worker registration, push subscription, AJAX polling, in-app toasts
 */

(function () {
  'use strict';

  const POLL_INTERVAL = 12000; // 12 seconds
  const BASE_URL = '/rectem_portal';
  let lastNotificationId = 0;
  let pollTimer = null;
  let swRegistration = null;
  let isSubscribed = false;

  // =========================================
  // INITIALIZATION
  // =========================================

  function init() {
    createToastContainer();
    createPermissionBanner();
    registerServiceWorker();
    startPolling();
  }

  // =========================================
  // SERVICE WORKER + PUSH SUBSCRIPTION
  // =========================================

  async function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
      console.log('[Notif] Service Workers not supported');
      return;
    }

    try {
      // Register or update the service worker
      swRegistration = await navigator.serviceWorker.register(BASE_URL + '/sw.js', {
        scope: BASE_URL + '/',
      });
      console.log('[Notif] Service Worker registered, scope:', swRegistration.scope);

      // Wait for the service worker to be ready
      await navigator.serviceWorker.ready;
      console.log('[Notif] Service Worker ready');

      // Check current subscription status
      const subscription = await swRegistration.pushManager.getSubscription();
      isSubscribed = subscription !== null;

      if (isSubscribed) {
        console.log('[Notif] Already subscribed to push');
        // Verify the subscription is still valid by re-sending to server
        await syncSubscription(subscription);
        hidePermissionBanner();
      } else if (Notification.permission === 'granted') {
        // Permission was granted before but subscription was lost (e.g., keys changed)
        console.log('[Notif] Permission granted but not subscribed — re-subscribing');
        await subscribeToPush();
      } else if (Notification.permission === 'default') {
        showPermissionBanner();
      } else {
        // Permission denied
        console.log('[Notif] Notification permission denied');
      }
    } catch (error) {
      console.error('[Notif] SW registration failed:', error);
    }
  }

  /**
   * Sync an existing subscription to the server (in case the server lost it).
   */
  async function syncSubscription(subscription) {
    try {
      const subJSON = subscription.toJSON();
      const response = await fetch(BASE_URL + '/api/push-subscribe.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'subscribe',
          endpoint: subJSON.endpoint,
          keys: subJSON.keys,
        }),
      });
      const data = await response.json();
      if (data.success) {
        console.log('[Notif] Subscription synced with server');
      }
    } catch (e) {
      console.warn('[Notif] Failed to sync subscription:', e);
    }
  }

  async function subscribeToPush() {
    if (!swRegistration) return;

    try {
      // Get VAPID public key from server
      const response = await fetch(BASE_URL + '/api/push-subscribe.php');
      const data = await response.json();

      if (!data.publicKey || data.publicKey === '') {
        console.log('[Notif] VAPID keys not generated yet');
        showToast('info', 'Push Setup', 'Push notifications are being configured. Please try again later.');
        return;
      }

      const applicationServerKey = urlBase64ToUint8Array(data.publicKey);

      // Unsubscribe any existing subscription first (handles key changes)
      const existingSub = await swRegistration.pushManager.getSubscription();
      if (existingSub) {
        await existingSub.unsubscribe();
        console.log('[Notif] Unsubscribed old subscription');
      }

      const subscription = await swRegistration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: applicationServerKey,
      });

      console.log('[Notif] Push subscription created');

      // Send subscription to server
      const subJSON = subscription.toJSON();
      const saveResponse = await fetch(BASE_URL + '/api/push-subscribe.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'subscribe',
          endpoint: subJSON.endpoint,
          keys: subJSON.keys,
        }),
      });

      const saveData = await saveResponse.json();
      if (saveData.success) {
        isSubscribed = true;
        hidePermissionBanner();
        showToast('success', 'Notifications Enabled', 'You will now receive push notifications on this device.');
        console.log('[Notif] Subscription saved to server');
      } else {
        console.error('[Notif] Server rejected subscription:', saveData.message);
        showToast('error', 'Subscription Failed', saveData.message || 'Could not enable notifications.');
      }
    } catch (error) {
      console.error('[Notif] Push subscription failed:', error);
      if (Notification.permission === 'denied') {
        showToast('error', 'Notifications Blocked', 'Please enable notifications in your browser settings.');
      } else {
        showToast('error', 'Subscription Error', 'Could not enable push notifications. Please try again.');
      }
    }
  }

  // =========================================
  // AJAX POLLING
  // =========================================

  function startPolling() {
    // Initial fetch
    checkForNewNotifications();

    // Set up interval
    pollTimer = setInterval(checkForNewNotifications, POLL_INTERVAL);

    // Also check when page becomes visible
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) {
        checkForNewNotifications();
      }
    });
  }

  async function checkForNewNotifications() {
    try {
      const response = await fetch(
        BASE_URL + '/api/check-notifications.php?last_id=' + lastNotificationId
      );
      const data = await response.json();

      if (!data.success) return;

      // Update badge count
      updateBadgeCount(data.unread_count);

      // Show toasts for new notifications
      if (data.notifications && data.notifications.length > 0) {
        // Only show toasts if this isn't the first poll (avoid spam on page load)
        if (lastNotificationId > 0) {
          data.notifications.forEach((notif) => {
            const type = getToastType(notif.type);
            showToast(type, notif.title, notif.message, notif.type);

            // Show browser notification if page is hidden and push isn't active
            if (document.hidden && Notification.permission === 'granted' && !isSubscribed) {
              showBrowserNotification(notif.title, notif.message);
            }
          });
        }
      }

      if (data.last_id !== undefined) {
        lastNotificationId = data.last_id;
      }
    } catch (error) {
      // Silently fail — network might be temporarily unavailable
    }
  }

  function getToastType(notifType) {
    switch (notifType) {
      case 'result':
        return 'success';
      case 'performance_comment':
        return 'info';
      case 'registration':
        return 'success';
      case 'consultation':
        return 'info';
      case 'profile':
        return 'success';
      default:
        return 'info';
    }
  }

  /**
   * Get a contextual icon for the notification type.
   */
  function getToastIcon(notifType, fallbackType) {
    const typeIcons = {
      'result': '📊',
      'performance_comment': '🤖',
      'registration': '✅',
      'consultation': '💬',
      'profile': '📸',
      'general': '📢',
    };
    if (typeIcons[notifType]) return typeIcons[notifType];

    const fallbackIcons = {
      success: '✅',
      error: '❌',
      info: '🔔',
      warning: '⚠️',
    };
    return fallbackIcons[fallbackType] || '🔔';
  }

  // =========================================
  // BADGE COUNT
  // =========================================

  function updateBadgeCount(count) {
    const buttons = document.querySelectorAll('.notification-btn');

    buttons.forEach((btn) => {
      let badge = btn.querySelector('.notification-badge');

      if (count > 0) {
        if (!badge) {
          badge = document.createElement('span');
          badge.className = 'notification-badge';
          btn.appendChild(badge);
        }
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = 'grid';
      } else if (badge) {
        badge.style.display = 'none';
      }
    });

    // Also update sidebar notification link
    const sidebarLink = document.querySelector('a.sidebar-link[href*="notifications"]');
    if (sidebarLink && count > 0) {
      let sidebarBadge = sidebarLink.querySelector('.sidebar-notif-badge');
      if (!sidebarBadge) {
        sidebarBadge = document.createElement('span');
        sidebarBadge.className = 'sidebar-notif-badge';
        sidebarLink.appendChild(sidebarBadge);
      }
      sidebarBadge.textContent = count;
      sidebarBadge.style.display = 'inline-flex';
    }
  }

  // =========================================
  // IN-APP TOAST NOTIFICATIONS
  // =========================================

  function createToastContainer() {
    if (document.getElementById('rectem-toast-container')) return;

    const container = document.createElement('div');
    container.id = 'rectem-toast-container';
    document.body.appendChild(container);
  }

  function showToast(type, title, message, notifType) {
    const container = document.getElementById('rectem-toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = 'rectem-toast rectem-toast--' + type;

    const icon = getToastIcon(notifType, type);

    toast.innerHTML =
      '<div class="rectem-toast__icon">' + icon + '</div>' +
      '<div class="rectem-toast__content">' +
        '<div class="rectem-toast__title">' + escapeHtml(title) + '</div>' +
        '<div class="rectem-toast__message">' + escapeHtml(message) + '</div>' +
      '</div>' +
      '<button class="rectem-toast__close" type="button">&times;</button>' +
      '<div class="rectem-toast__progress"></div>';

    // Close button
    toast.querySelector('.rectem-toast__close').addEventListener('click', () => {
      dismissToast(toast);
    });

    container.appendChild(toast);

    // Trigger animation
    requestAnimationFrame(() => {
      toast.classList.add('rectem-toast--visible');
    });

    // Auto-dismiss after 6 seconds
    setTimeout(() => {
      dismissToast(toast);
    }, 6000);
  }

  function dismissToast(toast) {
    if (!toast || toast.classList.contains('rectem-toast--dismissed')) return;

    toast.classList.add('rectem-toast--dismissed');
    toast.addEventListener('animationend', () => {
      toast.remove();
    });
  }

  // =========================================
  // PERMISSION BANNER
  // =========================================

  function createPermissionBanner() {
    if (document.getElementById('rectem-push-banner')) return;
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

    const banner = document.createElement('div');
    banner.id = 'rectem-push-banner';
    banner.className = 'rectem-push-banner';
    banner.style.display = 'none';

    banner.innerHTML =
      '<div class="rectem-push-banner__content">' +
        '<span class="rectem-push-banner__icon">🔔</span>' +
        '<div class="rectem-push-banner__text">' +
          '<strong>Enable Notifications</strong>' +
          '<p>Get instant alerts for results, registration updates, and important announcements — even when you\'re not on the portal.</p>' +
        '</div>' +
        '<div class="rectem-push-banner__actions">' +
          '<button class="rectem-push-banner__btn rectem-push-banner__btn--allow" type="button">Enable</button>' +
          '<button class="rectem-push-banner__btn rectem-push-banner__btn--dismiss" type="button">Later</button>' +
        '</div>' +
      '</div>';

    // Insert after the topbar
    const topbar = document.querySelector('.student-topbar');
    if (topbar && topbar.parentNode) {
      topbar.parentNode.insertBefore(banner, topbar.nextSibling);
    } else {
      document.body.appendChild(banner);
    }

    // Event listeners
    banner.querySelector('.rectem-push-banner__btn--allow').addEventListener('click', async () => {
      const permission = await Notification.requestPermission();
      if (permission === 'granted') {
        await subscribeToPush();
      } else {
        showToast('warning', 'Notifications Blocked', 'You can enable notifications later in your browser settings.');
      }
      hidePermissionBanner();
    });

    banner.querySelector('.rectem-push-banner__btn--dismiss').addEventListener('click', () => {
      hidePermissionBanner();
      // Don't show again for this session
      sessionStorage.setItem('rectem_push_dismissed', '1');
    });
  }

  function showPermissionBanner() {
    if (sessionStorage.getItem('rectem_push_dismissed') === '1') return;
    const banner = document.getElementById('rectem-push-banner');
    if (banner) {
      banner.style.display = 'block';
      requestAnimationFrame(() => {
        banner.classList.add('rectem-push-banner--visible');
      });
    }
  }

  function hidePermissionBanner() {
    const banner = document.getElementById('rectem-push-banner');
    if (banner) {
      banner.classList.remove('rectem-push-banner--visible');
      setTimeout(() => {
        banner.style.display = 'none';
      }, 400);
    }
  }

  // =========================================
  // BROWSER NOTIFICATION (Fallback when SW push not available)
  // =========================================

  function showBrowserNotification(title, body) {
    if (Notification.permission !== 'granted') return;

    new Notification(title, {
      body: body,
      icon: BASE_URL + '/rectem-logo.png',
      badge: BASE_URL + '/rectem-logo.png',
    });
  }

  // =========================================
  // UTILITIES
  // =========================================

  function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; i++) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  }

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  // =========================================
  // BOOT
  // =========================================

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
