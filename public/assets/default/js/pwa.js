(function () {
  'use strict';

  const body = document.body;
  if (!body) return;

  const csrf = document.querySelector('meta[name="needCSRFToken"]')?.getAttribute('content') || '';
  const currentUserId = Number.parseInt(body.dataset.pwaAuthUserId || '0', 10) || 0;
  let knownSubscription = null;
  let pushState = 'checking';
  let pushBusy = false;
  let statusGeneration = 0;
  let statusPromise = null;
  let statusPromiseGeneration = 0;
  let registrationPromise = null;
  const pushCheckTimeoutMs = 10000;
  const isLocalhost = ['localhost', '127.0.0.1'].includes(window.location.hostname);
  const isSecure = window.location.protocol === 'https:' || isLocalhost;
  const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent)
    || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
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

  const withPushDeadline = async (operation) => {
    let timer;
    try {
      return await Promise.race([
        operation,
        new Promise((_, reject) => {
          timer = window.setTimeout(() => reject(new Error('Push check timed out')), pushCheckTimeoutMs);
        })
      ]);
    } finally { window.clearTimeout(timer); }
  };

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
    close.setAttribute('aria-label', body.dataset.toastCloseLabel || 'Close');
    close.setAttribute('data-pwa-ios-install-dismiss', '');

    content.append(icon, text);
    banner.append(content, close);
    body.appendChild(banner);
  };

  const hideIosHint = () => {
    document.querySelectorAll('[data-pwa-ios-install-hint]').forEach((element) => element.classList.add('d-none'));
  };

  const pushLabels = (() => {
    try { return JSON.parse(document.querySelector('[data-pwa-push-labels]')?.dataset.pwaPushLabels || '{}'); }
    catch (_) { return {}; }
  })();

  const syncPushControls = () => {
    document.querySelectorAll('[data-pwa-enable-push], [data-pwa-disable-push]').forEach((button) => {
      const enable = button.hasAttribute('data-pwa-enable-push');
      const visible = currentUserId > 0 && (enable
        ? ['disabled', 'error'].includes(pushState)
        : pushState === 'enabled');
      button.classList.toggle('d-none', !visible);
      // d-inline-flex is !important too; never leave it competing with d-none.
      button.classList.toggle('d-inline-flex', visible);
      button.disabled = !visible || pushBusy;
      button.toggleAttribute('aria-hidden', !visible);
      if (!visible) button.setAttribute('tabindex', '-1');
      else button.removeAttribute('tabindex');
    });
  };

  const setPushStatusText = (key) => {
    pushState = key;
    document.querySelectorAll('[data-pwa-push-status]').forEach((element) => {
      const value = pushLabels[key] || element.dataset[`status${key.charAt(0).toUpperCase()}${key.slice(1)}`] || '';
      if (value) element.textContent = value;
      element.classList.toggle('text-bg-success', key === 'enabled');
      element.classList.toggle('text-bg-secondary', key !== 'enabled');
    });
    document.querySelectorAll('[data-pwa-push-status-hint]').forEach((element) => {
      if (pushLabels[key + 'Hint']) element.textContent = pushLabels[key + 'Hint'];
    });
    syncPushControls();
  };

  const browserPushStatus = () => {
    if (currentUserId <= 0) return 'disabled';
    const supported = 'serviceWorker' in navigator && 'Notification' in window && 'PushManager' in window;
    if (!supported) return isIos && !isStandalone() ? 'install' : 'unsupported';
    if (body.dataset.pwaEnabled !== '1' || body.dataset.pwaPushEnabled !== '1' || !body.dataset.pwaVapidPublicKey) return 'unavailable';
    if (!isSecure) return 'unavailable';
    if (Notification.permission === 'denied') return 'permission';
    return '';
  };

  const readDeviceStatus = async (subscription, signal) => {
    // Verify ownership/activation without re-enabling a revoked subscription on page load.
    const url = new URL(body.dataset.pwaStatusUrl, window.location.href);
    if (subscription) {
      const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(subscription.endpoint));
      const hash = Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, '0')).join('');
      url.searchParams.set('endpoint_hash', hash);
    }
    const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', signal });
    const result = await response.json();
    if (!response.ok || result.status !== true || Number(result.user_id) !== currentUserId) throw new Error('Push status unavailable');
    const push = result.push || {};
    if (!push.pwa_enabled || !push.global_enabled || !push.vapid_ready || !push.secure_context) return 'unavailable';
    return push.current_device_active === true && push.user_enabled === true ? 'enabled' : 'disabled';
  };

  const getPushRegistration = async () => {
    if (window.FireballPwa.registration) return window.FireballPwa.registration;
    if (typeof navigator.serviceWorker.getRegistration === 'function') {
      const existing = await navigator.serviceWorker.getRegistration(window.location.href);
      const workerUrl = new URL(body.dataset.pwaServiceWorkerUrl || '/service-worker.js', window.location.href).href;
      if (existing?.active?.scriptURL === workerUrl && existing.pushManager) {
        // A current endpoint can be checked while register/update is still pending in iOS.
        window.FireballPwa.registration = existing;
        return existing;
      }
    }
    return registerServiceWorker();
  };

  const syncPushStatus = () => {
    if (pushBusy) return Promise.resolve(pushState);
    const browserStatus = browserPushStatus();
    if (browserStatus) {
      ++statusGeneration;
      setPushStatusText(browserStatus);
      return Promise.resolve(browserStatus);
    }
    if (statusPromise && statusPromiseGeneration === statusGeneration) return statusPromise;
    const generation = ++statusGeneration;
    statusPromiseGeneration = generation;
    statusPromise = (async () => {
      const controller = new AbortController();
      try {
        const { subscription, serverStatus } = await withPushDeadline((async () => {
          const registration = await getPushRegistration();
          if (!registration?.pushManager) throw new Error('Push registration unavailable');
          const subscription = await registration.pushManager.getSubscription();
          const serverStatus = await readDeviceStatus(subscription, controller.signal);
          return { subscription, serverStatus };
        })());
        const status = serverStatus === 'enabled'
          && (Notification.permission !== 'granted' || !subscription || !subscriptionUsesCurrentVapidKey(subscription))
          ? 'disabled' : serverStatus;
        if (generation !== statusGeneration || pushBusy) return pushState;
        knownSubscription = subscription;
        setPushStatusText(Notification.permission === 'denied' ? 'permission' : status);
      } catch (_) {
        if (generation === statusGeneration && !pushBusy) setPushStatusText('error');
      } finally { controller.abort(); }
      return pushState;
    })().finally(() => {
      if (statusPromiseGeneration === generation) statusPromise = null;
    });
    return statusPromise;
  };

  const registerServiceWorker = () => {
    if (registrationPromise) return registrationPromise;
    if (body.dataset.pwaEnabled !== '1' || !isSecure || !('serviceWorker' in navigator)) {
      showIosHint();
      return Promise.resolve(null);
    }
    registrationPromise = withPushDeadline((async () => {
      const registration = await navigator.serviceWorker.register(body.dataset.pwaServiceWorkerUrl || '/service-worker.js', {
        scope: '/', updateViaCache: 'none'
      });
      if (registration.waiting) registration.waiting.postMessage({ type: 'SKIP_WAITING' });
      // Prepare an active worker before showing Enable, not after the user taps it.
      return registration.active ? registration : await navigator.serviceWorker.ready;
    })()).then((ready) => {
      window.FireballPwa.registration = ready;
      return ready;
    }).catch((error) => {
      registrationPromise = null;
      console.warn('PWA service worker registration failed', error);
      return null;
    });
    return registrationPromise;
  };

  let badgeQueue = Promise.resolve();
  let badgeValue = null;
  let badgeWorkerDirty = true;
  const syncWorkerBadge = (value) => {
    const worker = navigator.serviceWorker?.controller || window.FireballPwa.registration?.active;
    if (!worker) return false;
    try {
      worker.postMessage({ type: 'SYNC_BADGE', count: value, user_id: currentUserId });
      return true;
    } catch (error) {
      return false;
    }
  };
  const setBadge = (count) => {
    const numeric = Number(count);
    if (count === null || !Number.isFinite(numeric)) return Promise.resolve();
    const value = currentUserId > 0 ? Math.max(0, Math.min(Number.MAX_SAFE_INTEGER, Math.floor(numeric))) : 0;
    if (!isSecure || body.dataset.pwaEnabled !== '1') return Promise.resolve();
    if (value !== badgeValue || badgeWorkerDirty) {
      badgeValue = value;
      // The worker also handles Android notification-dot cleanup. No permission prompt here.
      badgeWorkerDirty = !syncWorkerBadge(value);
    }
    badgeQueue = badgeQueue.then(async () => {
      try {
        if (value === 0 && typeof navigator.clearAppBadge === 'function') await navigator.clearAppBadge();
        else if (typeof navigator.setAppBadge === 'function') await navigator.setAppBadge(value);
      } catch (error) {
        // Unsupported OS, denied permission or an uninstalled PWA must not break the feed.
      }
    });
    return badgeQueue;
  };

  const subscribePush = async () => {
    if (currentUserId <= 0) return { status: false, reason: 'auth' };
    const browserStatus = browserPushStatus();
    if (browserStatus) return { status: false, reason: browserStatus };
    if (navigator.userActivation && !navigator.userActivation.isActive) return { status: false, reason: 'gesture' };
    const registration = window.FireballPwa.registration;
    if (!registration?.pushManager) {
      await registerServiceWorker();
      return { status: false, reason: 'retry' };
    }
    let existing = knownSubscription;
    if (existing && !subscriptionUsesCurrentVapidKey(existing)) {
      const endpoint = existing.endpoint || '';
      const response = await postJson(body.dataset.pwaUnsubscribeUrl, { endpoint });
      if (!response.ok || (await response.json()).status !== true || !await existing.unsubscribe()) {
        return { status: false, reason: 'server' };
      }
      knownSubscription = null;
      // Key rotation requires async cleanup. Ask for a fresh gesture instead of losing Safari activation.
      return { status: false, reason: 'retry' };
    }
    // subscribe() requests permission itself, directly inside the click's activation.
    // No service-worker registration/getSubscription/network await may precede this call.
    const subscription = (existing && Notification.permission === 'granted') ? existing : await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(body.dataset.pwaVapidPublicKey)
    });

    const response = await postJson(body.dataset.pwaSubscribeUrl, subscription.toJSON());
    const result = await response.json();
    if (!response.ok || result.status !== true) {
      return { status: false, reason: response.status === 401 ? 'auth' : 'server' };
    }
    knownSubscription = subscription;
    return { status: true, subscription };
  };

  const unsubscribePush = async () => {
    if (currentUserId <= 0) return { status: false, reason: 'auth' };
    const registration = window.FireballPwa.registration || await registerServiceWorker();
    if (!registration || !registration.pushManager) {
      return { status: true };
    }
    const subscription = await registration.pushManager.getSubscription();
    if (!subscription) {
      return { status: true };
    }
    const response = await postJson(body.dataset.pwaUnsubscribeUrl, { endpoint: subscription.endpoint });
    const result = await response.json();
    if (!response.ok || result.status !== true) {
      return { status: false, reason: response.status === 401 ? 'auth' : 'server' };
    }
    if (!await subscription.unsubscribe()) return { status: false, reason: 'server' };
    knownSubscription = null;
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
      const button = event.target.closest('[data-pwa-enable-push], [data-pwa-disable-push]');
      if (!button) return;
      event.preventDefault();
      if (!button.disabled && !button.classList.contains('d-none')) {
        runPushAction(button.hasAttribute('data-pwa-enable-push') ? 'subscribe' : 'unsubscribe');
      }
    });
  };

  const runPushAction = async (action) => {
    if (pushBusy) return { status: false, reason: 'busy' };
    pushBusy = true;
    ++statusGeneration;
    syncPushControls();
    document.querySelectorAll('[data-pwa-push-feedback]').forEach((element) => element.classList.add('d-none'));
    let result;
    try {
      result = await (action === 'subscribe' ? subscribePush() : unsubscribePush());
    } catch (_) {
      result = { status: false, reason: window.Notification?.permission === 'denied' ? 'permission' : 'server' };
    } finally { pushBusy = false; }
    await syncPushStatus();
    if (!result.status && pushState !== 'permission') {
      document.querySelectorAll('[data-pwa-push-feedback]').forEach((element) => {
        element.textContent = pushLabels[result.reason === 'retry' ? 'retry' : 'actionError'] || '';
        element.classList.remove('d-none');
      });
    }
    document.dispatchEvent(new CustomEvent('fireball:pwa-' + action + '-result', { detail: result }));
    return result;
  };

  window.FireballPwa = {
    registration: null,
    register: registerServiceWorker,
    subscribe: () => runPushAction('subscribe'),
    unsubscribe: () => runPushAction('unsubscribe'),
    refreshPushStatus: syncPushStatus,
    setBadge
  };

  applyModeClasses();
  syncPushControls();
  window.matchMedia('(display-mode: standalone)').addEventListener?.('change', () => {
    applyModeClasses();
    syncPushStatus();
    if (isStandalone()) hideIosHint();
    else showIosHint();
  });
  bindInstallPrompt();
  bindPwaLinks();
  bindPushButtons();
  // Start status independently: register() itself can remain pending in a resumed iOS PWA.
  syncPushStatus();
  registerServiceWorker().then((registration) => {
    if (badgeValue !== null) syncWorkerBadge(badgeValue);
    // A failed background update must not restart a timed-out check or erase its result.
    if (registration) return syncPushStatus();
  });
  window.addEventListener('pageshow', () => syncPushStatus());
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') syncPushStatus();
  });
  if (currentUserId <= 0) setBadge(0);
  navigator.serviceWorker?.addEventListener('message', (event) => {
    if (event.data?.type === 'PWA_NOTIFICATIONS_CHANGED' && Number(event.data.user_id) === currentUserId) {
      badgeWorkerDirty = true;
      document.dispatchEvent(new CustomEvent('fireball:notifications-changed'));
    }
  });
  showIosHint();
})();
