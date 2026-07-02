const fireballPwaVersion = new URL(self.location.href).searchParams.get('v') || '';
importScripts('/sw.js' + (fireballPwaVersion ? '?v=' + encodeURIComponent(fireballPwaVersion) : ''));
