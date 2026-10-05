# Profile date and dashboard layout — 2026-10-05

## Confirmed causes

- The profile metadata row allowed `flex-wrap`, and its date value allowed wrapping.
  A full registration label plus the date/time did not fit the narrow sidebar.
- Dashboard cards shared a three-column grid with unequal tracks (`2fr`, `.72fr`, `.85fr`).
  This made the source chart wider than the device/country cards. Popular pages used one
  track while latest visitors spanned two; their sizes depended on those same unequal tracks.
- The visit total used absolute positioning inside the chart body, overlapping the plot.
- Circular chart legends were drawn inside a fixed-height canvas. Extra legend rows
  competed with the plot for space. The CMS theme also sets ordinary lists to a vertical
  flex layout, so external legends must explicitly specify horizontal flow.

## Changes

| Files | Change |
| --- | --- |
| `app/Views/themes/default/admin/dashboard.php` | Keep the existing card order and traffic/quick-actions layout. Put the three circular-chart cards in their own row and retain popular pages/latest visitors as two separate cards in another row. Add explicit latest-visitor column proportions. |
| `public/assets/default/css/admin-ui.css` | Three equal chart columns and two equal table columns from 768px; both rows stack below that. Keep existing CMS colors, spacing and borders. Move visit total into normal flow above the plot. Allow long table values to wrap; the five-column visitor table keeps a 520px minimum width inside its existing responsive scroller. |
| `public/assets/default/js/admin-analytics.js`, `themes/default/assets/js/admin-analytics.js` | Keep circular plots in a sized inner container and render wrapping legends outside the canvas. Preserve legend toggling, keyboard activation, range controls and theme re-rendering. Bound chart height to its actual container. Preserve pre-existing differences between the two script variants. |
| `themes/default/partials/auth/profile.php`, `app/Views/themes/default/auth/profile.php` | Registration label and full date/time share one row; keep the original full label for accessibility and tooltip. |
| `themes/default/assets/css/profile.css`, `public/assets/default/css/profile.css` | Scope the one-line grid to the registration row only. Use compact typography at narrow sidebar widths without changing other metadata. The two asset copies match. |
| `app/Languages/{ru,en,de,zh-cn}/auth/profile.php` | Add the compact registration label in all four languages; do not shorten the date/time itself. |
| `tests/fixtures/dashboard_analytics.php`, `tests/dashboard_analytics_browser.cjs` | Real dashboard template/assets with isolated populated and empty data. Check equal card/table rows, preserved desktop order, chart bounds, complete legends, theme/range controls and legend interaction. |
| `tests/profile_registration_browser.cjs` | Real active/fallback profile templates across four languages, both themes and twelve viewport widths. Check the full date/time, one-line alignment, accessible label and overflow. |

The bundled `apexcharts.min.js` is a compatibility adapter backed by Chart.js, not the
full ApexCharts library. It ignores `legend.show` and does not implement `toggleSeries`.
The new legend integration uses its actual underlying Chart instance when present;
the pre-existing full-Apex fallback remains supported in code. No vendor files changed.

## Verification and deployment

Run `php tests/profile_push_unit.php`, `node tests/profile_registration_browser.cjs`,
`node tests/dashboard_analytics_browser.cjs`, and `php bin/cms.php assets:rebuild`.
Browser tests require Playwright/Chromium; `PHP_BIN` can select the PHP executable.
Dashboard widths: 320, 390, 768, 1024, 1440, 1996px; ru/en/de/zh-cn; light/dark;
both script copies; native Chart.js and the bundled compatibility adapter.
Profile widths additionally cover narrow desktop sidebars and breakpoint boundaries.

Passed locally: 1,600 dashboard browser checks, 1,152 profile registration browser checks,
and 541 existing profile/Push unit checks. PHP/JavaScript syntax and whitespace checks passed.
The dashboard screenshot was also reviewed after chart animation completed.

Tests use CLI-only fixtures and local assets intercepted in Chromium. They do not access
the configured application DB or modify analytics data. Native iPhone Safari and a custom
installation of full ApexCharts still need staging smoke testing. No production deployment,
plugin update/version bump, schema change or real visitor/device operation was performed.
The local asset manifest was rebuilt; deploy these files with the usual asset rebuild so
old cached CSS/JS URLs are replaced.
