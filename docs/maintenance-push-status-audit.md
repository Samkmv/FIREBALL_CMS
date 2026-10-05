# Shared maintenance page and bounded Push check — 2026-10-05

## Maintenance page

The screenshot came from `public/runtime-gate.php`, not the application's error view.
The pre-bootstrap guard had its own complete copy of the page, including the removed
brand mark and a different refresh SVG. Editing only the normal view did not change it.

There is now one presentation in `app/Views/system/update.php`. The normal error view
passes site settings/translations; the runtime guard passes public maintenance metadata
and uses the shared built-in translations when application services are unavailable.
HTML, styles, icons, theme initialization, animation and retry behavior live in this one file.
It uses only built-in PHP and inline assets: no autoload, DB, external font or script is needed.
The `ci-refresh-cw` element contains an inline refresh-cw symbol so it still works while
the site's icon font is being replaced. Only the icon inside the stationary loader circle
rotates; the button icon stays static, including during a request. Reduced motion is respected.
The brand above the card is text only. Existing CMS colors, spacing, safe areas and four
languages remain; the guard's home link also preserves the detected locale.

Changed:

- `app/Views/system/update.php` — single standalone page.
- `app/Views/themes/default/errors/update.php` — adapter for settings and translations.
- `public/runtime-gate.php` — adapter before bootstrap; preserve 503/Retry-After/no-store
  and file-lock behavior. If the shared file is missing during initial deployment, a minimal
  localized 503 fallback retries after 12 seconds rather than loading partially replaced code.
- `tests/fixtures/update_maintenance.php`, `tests/update_maintenance_browser.cjs` — actual
  template through both entry points in a temporary isolated installation, no application DB.

For manual deployment, publish the shared view **before** either adapter. Normal CMS
package copying replaces files atomically; once the shared file exists there is no extra
dependency on the rest of application bootstrapping. Do not remove the maintenance marker
or change locking to bypass a failed update.

## Push status

Confirmed in `public/assets/default/js/pwa.js`: the previous activation timeout started
only after `serviceWorker.register()` completed. Registration itself, `getSubscription()`,
the endpoint digest and the status request/JSON response had no deadline. A pending browser
API or request could therefore leave the server-rendered “Checking” label indefinitely.
This is a reproducible failure path, not proof of which native API stalled on the pictured iPhone.

Changes in that file:

- Bound the entire device status check to 10 seconds; abort its network request on completion
  or failure. A timeout yields the existing localized error/retry state, not a false enabled state.
- Read the existing active worker for the configured script URL when available. Checking an
  endpoint no longer has to wait for a background worker registration/update to finish.
- Bound both registration and activation. Late results cannot replace a newer status or a
  timeout result. A failed background registration does not restart/erase a completed check.
- Start status verification independently at load. Returning to the page continues to verify
  the current endpoint, not the total number of other devices.
- Concurrent load/pageshow/background-registration checks share the in-flight promise,
  rather than invalidating its result and extending the deadline. Changed permission and
  explicit Enable/Disable invalidate stale results immediately.
- Keep endpoint verification read-only. An endpoint detached by an administrator becomes
  disabled after a successful lookup, even if browser permission/subscription remain present.
  No automatic Push re-subscription or endpoint POST is performed; reactivation requires Enable.

`tests/profile_push_browser.cjs` now covers suspended registration, subscription, digest and
network checks, timeout recovery, a late non-abortable result, existing-worker lookup while
registration hangs, both profile templates, and a remaining active second device. Browser APIs
and backend are test doubles. Only the test clock's 10-second deadlines are compressed to
100ms; production code keeps 10,000ms. No real device binding was changed during testing.

## Verification

- 338 profile/Push browser checks and 541 existing profile/Push unit checks passed.
- 817 maintenance browser checks passed: both paths, four languages, dark/light, 320/390/768/1440,
  normal/reduced motion, static refresh button, retry after 5xx, successful reload, no asset
  dependencies, and fail-closed behavior if the shared view is missing.
- PHP/JavaScript syntax and whitespace checks passed; the mobile page preview was reviewed.
- Local content-versioned assets were rebuilt. Deploy the revised `pwa.js` and run the usual
  asset rebuild; already open PWA pages need reloading to execute the new script.

Physical iPhone PWA/Push APIs and live APNs delivery have not been tested here. There was no
production deployment or real CMS update, database migration, permission reset, device deletion
or plugin update/version change.
