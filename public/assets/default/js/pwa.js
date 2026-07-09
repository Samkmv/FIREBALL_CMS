(function () {
  'use strict';

  const body = document.body;
  if (!body) return;

  const csrf = document.querySelector('meta[name="needCSRFToken"]')?.getAttribute('content') || '';
  const isLocalhost = ['localhost', '127.0.0.1'].includes(window.location.hostname);
  const isSecure = window.location.protocol === 'https:' || isLocalhost;
  const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
  const isSafari = /^((?!chrome|android|crios|fxios|edg|yabrowser).)*safari/i.test(navigator.userAgent);
  const iosHintStorageKey = 'fireball.pwa.iosInstallHintDismissed';

  const applyModeClasses = () => {
    const standalone = isStandalone();
    body.classList.toggle('pwa', standalone);
    body.classList.toggle('standalone', standalone);
    body.classList.toggle('pwa-standalone', standalone);
    document.documentElement.classList.toggle('pwa', standalone);
    document.documentElement.classList.toggle('standalone', standalone);
    document.documentElement.classList.toggle('pwa-standalone', standalone);
  };

  const postJson = (url, payload, method = 'POST') => fetch(url, {
    method,
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-Token': csrf
    },
    body: JSON.stringify(Object.assign({ needCSRFToken: csrf }, payload || {}))
  });

  const urlBase64ToUint8Array = (value) => {
    const padding = '='.repeat((4 - value.length % 4) % 4);
    const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    const output = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i++) output[i] = raw.charCodeAt(i);
    return output;
  };

  const buffersEqual = (left, right) => {
    if (!left || !right || left.byteLength !== right.byteLength) return false;
    const leftView = new Uint8Array(left);
    const rightView = new Uint8Array(right);
    for (let i = 0; i < leftView.length; i++) {
      if (leftView[i] !== rightView[i]) return false;
    }
    return true;
  };

  const subscriptionUsesCurrentVapidKey = (subscription) => {
    const currentKey = body.dataset.pwaVapidPublicKey ? urlBase64ToUint8Array(body.dataset.pwaVapidPublicKey) : null;
    const subscriptionKey = subscription?.options?.applicationServerKey || null;
    if (!currentKey || !subscriptionKey) return true;

    return buffersEqual(subscriptionKey, currentKey);
  };

  const showIosHint = () => {
    if (!isIos || !isSafari || isStandalone()) return;
    if (!body.dataset.pwaSafariHint || localStorage.getItem(iosHintStorageKey) === '1') return;

    const existing = document.querySelector('[data-pwa-ios-install-hint]');
    if (existing) {
      existing.classList.remove('d-none');
      return;
    }

    const banner = document.createElement('div');
    banner.className = 'pwa-ios-install-banner alert alert-info alert-dismissible fade show shadow-sm';
    banner.setAttribute('role', 'status');
    banner.setAttribute('data-pwa-ios-install-hint', '');

    const content = document.createElement('div');
    content.className = 'd-flex align-items-start gap-2';

    const icon = document.createElement('i');
    icon.className = 'ci-smartphone fs-base mt-1 flex-shrink-0';

    const text = document.createElement('div');
    text.className = 'small';
    text.textContent = body.dataset.pwaSafariHint;

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn-close';
    close.setAttribute('aria-label', 'Close');
    close.setAttribute('data-pwa-ios-install-dismiss', '');

    content.append(icon, text);
    banner.append(content, close);
    body.appendChild(banner);
  };

  const hideIosHint = () => {
    document.querySelectorAll('[data-pwa-ios-install-hint]').forEach((element) => element.classList.add('d-none'));
  };

  const syncPushControls = () => {
    const hidePush = isIos && !isStandalone();
    document.querySelectorAll('[data-pwa-enable-push]').forEach((button) => {
      button.classList.toggle('d-none', hidePush);
      button.toggleAttribute('aria-hidden', hidePush);
      if (hidePush) button.setAttribute('tabindex', '-1');
      else button.removeAttribute('tabindex');
    });
  };

  const setPushStatusText = (key) => {
    document.querySelectorAll('[data-pwa-push-status]').forEach((element) => {
      const value = element.dataset[`status${key.charAt(0).toUpperCase()}${key.slice(1)}`] || '';
      if (value) element.textContent = value;
      element.classList.toggle('text-bg-success', key === 'enabled');
      element.classList.toggle('text-bg-secondary', key !== 'enabled');
    });
  };

  const browserPushStatus = () => {
    if (body.dataset.pwaPushEnabled !== '1' || !body.dataset.pwaVapidPublicKey) return 'unavailable';
    if (!isSecure) return 'unavailable';
    if (isIos && !isStandalone()) return 'unavailable';
    if (!('Notification' in window) || !('PushManager' in window)) return 'unsupported';
    if (Notification.permission === 'denied') return 'permission';
    return '';
  };

  const syncPushStatus = async () => {
    const browserStatus = browserPushStatus();
    if (browserStatus) {
      setPushStatusText(browserStatus);
      return;
    }

    const registration = window.FireballPwa.registration || await registerServiceWorker();
    const subscription = registration?.pushManager ? await registration.pushManager.getSubscription() : null;
    if (subscription) {
      setPushStatusText('enabled');
      return;
    }

    if (!body.dataset.pwaStatusUrl) {
      setPushStatusText('disabled');
      return;
    }

    try {
      const response = await fetch(body.dataset.pwaStatusUrl, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const data = await response.json();
      const push = data && data.push ? data.push : {};
      setPushStatusText(push.user_enabled && Number(push.active_subscriptions || 0) > 0 ? 'enabled' : 'disabled');
    } catch (error) {
      setPushStatusText('disabled');
    }
  };

  const registerServiceWorker = async () => {
    if (body.dataset.pwaEnabled !== '1' || !isSecure || !('serviceWorker' in navigator)) {
      showIosHint();
      return null;
    }

    try {
      const registration = await navigator.serviceWorker.register(body.dataset.pwaServiceWorkerUrl || '/service-worker.js', {
        scope: '/',
        updateViaCache: 'none'
      });
      window.FireballPwa.registration = registration;
      if (registration.waiting) registration.waiting.postMessage({ type: 'SKIP_WAITING' });
      return registration;
    } catch (error) {
      console.warn('PWA service worker registration failed', error);
      return null;
    }
  };

  const setBadge = (count) => {
    if (!('setAppBadge' in navigator) || !('clearAppBadge' in navigator)) return;
    const value = Number.parseInt(count, 10) || 0;
    if (value > 0) navigator.setAppBadge(value).catch(() => {});
    else navigator.clearAppBadge().catch(() => {});
  };

  const subscribePush = async () => {
    if (body.dataset.pwaPushEnabled !== '1') return { status: false, reason: 'disabled' };
    if (!isSecure) return { status: false, reason: 'https' };
    if (isIos && !isStandalone()) {
      showIosHint();
      return { status: false, reason: 'ios_not_standalone' };
    }
    if (!('Notification' in window) || !('PushManager' in window)) return { status: false, reason: 'unsupported' };
    if (!body.dataset.pwaVapidPublicKey) return { status: false, reason: 'vapid' };

    const registration = window.FireballPwa.registration || await registerServiceWorker();
    if (!registration) return { status: false, reason: 'service_worker' };

    let permission = Notification.permission;
    if (permission === 'default') permission = await Notification.requestPermission();
    if (permission !== 'granted') return { status: false, reason: 'permission' };

    let existing = await registration.pushManager.getSubscription();
    if (existing && !subscriptionUsesCurrentVapidKey(existing)) {
      const endpoint = existing.endpoint || '';
      await existing.unsubscribe().catch(() => {});
      if (endpoint && body.dataset.pwaUnsubscribeUrl) {
        await postJson(body.dataset.pwaUnsubscribeUrl, { endpoint }).catch(() => {});
      }
      existing = null;
    }

    const subscription = existing || await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(body.dataset.pwaVapidPublicKey)
    });

    await postJson(body.dataset.pwaSubscribeUrl, subscription.toJSON());
    await syncPushStatus();
    return { status: true, subscription };
  };

  const unsubscribePush = async () => {
    const registration = window.FireballPwa.registration || await registerServiceWorker();
    if (!registration || !registration.pushManager) {
      await postJson(body.dataset.pwaUnsubscribeUrl, { endpoint: '' });
      await syncPushStatus();
      return { status: true };
    }
    const subscription = await registration.pushManager.getSubscription();
    if (!subscription) {
      await postJson(body.dataset.pwaUnsubscribeUrl, { endpoint: '' });
      await syncPushStatus();
      return { status: true };
    }
    await postJson(body.dataset.pwaUnsubscribeUrl, { endpoint: subscription.endpoint });
    await subscription.unsubscribe();
    await syncPushStatus();
    return { status: true };
  };

  const bindInstallPrompt = () => {
    let deferredPrompt = null;
    window.addEventListener('beforeinstallprompt', (event) => {
      event.preventDefault();
      deferredPrompt = event;
      body.classList.add('pwa-install-available');
      document.querySelectorAll('[data-pwa-install]').forEach((button) => button.classList.remove('d-none'));
    });

    window.addEventListener('appinstalled', () => {
      deferredPrompt = null;
      body.classList.remove('pwa-install-available');
      localStorage.setItem('fireball.pwa.installed', '1');
    });

    document.addEventListener('click', (event) => {
      const dismissIosHint = event.target.closest('[data-pwa-ios-install-dismiss]');
      if (dismissIosHint) {
        localStorage.setItem(iosHintStorageKey, '1');
        dismissIosHint.closest('[data-pwa-ios-install-hint]')?.remove();
        return;
      }

      const installButton = event.target.closest('[data-pwa-install]');
      if (!installButton) return;
      event.preventDefault();
      if (!deferredPrompt) {
        showIosHint();
        return;
      }
      deferredPrompt.prompt();
      deferredPrompt.userChoice.finally(() => {
        deferredPrompt = null;
        body.classList.remove('pwa-install-available');
      });
    });
  };

  const bindPwaLinks = () => {
    document.addEventListener('click', (event) => {
      if (!isStandalone()) return;
      const link = event.target.closest('a[href]');
      if (!link || link.target || link.hasAttribute('download')) return;
      const url = new URL(link.href, window.location.href);
      if (url.origin !== window.location.origin) {
        event.preventDefault();
        window.open(url.href, '_blank', 'noopener,noreferrer');
      }
    });
  };

  const bindPushButtons = () => {
    document.addEventListener('click', (event) => {
      const enable = event.target.closest('[data-pwa-enable-push]');
      if (enable) {
        event.preventDefault();
        subscribePush().then((result) => {
          document.dispatchEvent(new CustomEvent('fireball:pwa-subscribe-result', { detail: result }));
          syncPushStatus();
        });
        return;
      }
      const disable = event.target.closest('[data-pwa-disable-push]');
      if (disable) {
        event.preventDefault();
        unsubscribePush().then((result) => {
          document.dispatchEvent(new CustomEvent('fireball:pwa-unsubscribe-result', { detail: result }));
          syncPushStatus();
        });
      }
    });
  };

  window.FireballPwa = {
    registration: null,
    register: registerServiceWorker,
    subscribe: subscribePush,
    unsubscribe: unsubscribePush,
    setBadge
  };

  applyModeClasses();
  syncPushControls();
  window.matchMedia('(display-mode: standalone)').addEventListener?.('change', () => {
    applyModeClasses();
    syncPushControls();
    if (isStandalone()) hideIosHint();
    else showIosHint();
  });
  bindInstallPrompt();
  bindPwaLinks();
  bindPushButtons();
  registerServiceWorker().then(syncPushStatus);
  showIosHint();
})();
