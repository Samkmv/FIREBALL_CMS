
# FIREBALL CMS Project Map

## Stack
- Custom PHP 8+ MVC CMS
- Composer autoload
- MySQL/MariaDB
- Bootstrap-style admin UI
- AJAX interfaces
- PWA/service worker

## Entry Point
- public/index.php

## Core
- core/Application.php — bootstrapping
- core/Router.php — routing
- core/Controller.php — base controller
- core/Model.php — base model
- core/Database.php — DB wrapper
- core/View.php — rendering
- core/Auth.php — auth
- core/Request.php — request helper
- core/Response.php — redirects/json
- core/Session.php — flash/session
- core/Pagination.php — pagination

## Main Controllers
- app/Controllers/AdminController.php — admin dashboard, contact requests, categories, users, roles, settings, updates
- app/Controllers/AdminPostController.php — admin posts list/create/edit/autosave/preview/publish/delete
- app/Controllers/PostsController.php — public blog list and post page
- app/Controllers/AdminPagesController.php — admin CRUD, autosave and preview for CMS pages
- app/Controllers/PagesController.php — public CMS page output by slug
- app/Controllers/HomeController.php — homepage
- app/Controllers/AuthController.php — login/auth
- app/Controllers/FileManagerController.php — media/file manager
- app/Controllers/ChatController.php — chat
- app/Controllers/SearchController.php — search
- app/Controllers/NotificationController.php — notifications

## Main Models
- app/Models/Post.php — public post queries, search, views, featured/popular posts, post schema compatibility
- app/Models/Category.php — blog categories from `post_categories`, category schema, navigation/sidebar categories, category SEO
- app/Models/Page.php — CMS pages, menu placement, slug validation, page schema, admin pagination
- app/Models/User.php — users
- app/Models/Admin.php — admin-related data
- app/Models/FileManager.php — files/media
- app/Models/SiteSetting.php — settings
- app/Models/ChatMessage.php — chat
- app/Models/NotificationCenter.php — notifications
- app/Models/Search.php — search
- app/Models/ContactRequest.php — contact requests
- app/Models/Analytics.php — analytics

## Services
- app/Services/PostSeo.php — builds post SEO descriptions from excerpt/content/title
- app/Services/ChatCipher.php — chat encryption helpers
- app/Services/UpdateCenter.php — update-center logic

## Views
- app/Views/layouts — layouts
- app/Views/pagination — pagination templates
- app/Views/themes/default — frontend/admin theme templates

## Posts Flow
- Public list/show: app/Controllers/PostsController.php
- Admin management: app/Controllers/AdminPostController.php
- Public data layer: app/Models/Post.php
- Admin data layer for CRUD/table operations: app/Models/Admin.php
- Blog category helper model: app/Models/Category.php
- Post SEO helper service: app/Services/PostSeo.php
- Shared editor view: app/Views/themes/default/admin/post_form.php
- Shared block editor JS: public/assets/default/js/admin-post-editor.js
- DB fields include:
    - title
    - slug
    - category_id
    - excerpt
    - content
    - image
    - seo_title
    - seo_description
    - seo_keywords
    - seo_image
    - hide_placeholder_image
    - show_on_home
    - priority
    - author_id
    - author_name
    - author_role
    - views_count
    - published_at
    - is_published

## Posts Routing Rules
- Old admin URLs must stay unchanged:
    - `/admin/posts`
    - `/admin/posts/create`
    - `/admin/posts/edit/{id}`
    - `/admin/posts/autosave`
    - `/admin/posts/preview/{id}`
    - `/admin/posts/toggle-published`
    - `/admin/posts/delete`
- These routes point to `AdminPostController`, not `AdminController`.
- Public post URLs stay under `/posts`:
    - `/posts`
    - `/posts/{slug}`

## Pages Flow
- Public show route: `/{slug}` handled by app/Controllers/PagesController.php
- Admin list/create/edit/delete/publish: app/Controllers/AdminPagesController.php
- Admin preview for drafts and published pages: `/admin/pages/preview/{id}`
- Data layer: app/Models/Page.php
- Admin table view: app/Views/themes/default/admin/pages.php
- Public view: app/Views/themes/default/pages/show.php
- Pages reuse the existing post editor view with `editor_mode = page`.
- Pages reuse the same HTML block content format as posts; there is no separate blocks table.
- Page content is stored in `pages.content` and rendered directly by the public page template.
- Public `/{slug}` must only show published pages; missing pages and drafts must 404.
- Draft previews must use the admin preview route and `noindex,nofollow`.
- Admin Pages table intentionally mirrors Posts:
    - published/draft tabs
    - separate pagination for each tab
    - live search
    - view/edit/publish/delete action buttons

## Pages DB Fields
- id
- title
- menu_title
- slug
- content
- meta_title
- meta_description
- is_published
- show_in_header
- show_in_footer
- menu_order
- created_at
- updated_at

## Pages Navigation Rules
- Header menu pages come from `Page::getMenuPages('header')`.
- Footer menu pages come from `Page::getMenuPages('footer')`.
- Only published pages may appear in menus.
- Menu label is `menu_title` when present, otherwise `title`.
- Sorting uses `menu_order ASC, title ASC, id ASC`.
- The admin form exposes one Choices select named `menu_visibility`:
    - `none` => `show_in_header = 0`, `show_in_footer = 0`
    - `header` => `show_in_header = 1`, `show_in_footer = 0`
    - `footer` => `show_in_header = 0`, `show_in_footer = 1`
    - `both` => `show_in_header = 1`, `show_in_footer = 1`

## Caching
- Uses the existing `core/Cache.php` file cache via `cache()`.
- `SiteSetting::all()` caches `site_settings:all`; saving settings calls `SiteSetting::clearPublicCache()`.
- `Post::clearPublicCache()` bumps `posts:public_version` and clears legacy post/category keys.
- `Page::clearPublicCache()` bumps `pages:public_version` and clears legacy page menu keys.
- `Page::getMenuPages('header'|'footer')` is cached and should only return published pages.
- `Post` caches public navigation categories, sidebar categories, featured posts, popular posts, trending posts, and category SEO/name lookups.
- Do not cache user/session/form/CSRF data.

## Database / Schema Notes
- Runtime schema compatibility is handled in models with safe `SHOW COLUMNS` / `SHOW INDEX` checks.
- Do not add an index before confirming the required column exists.
- `posts.category_id` must exist before adding `KEY category_id`.
- Project currently uses `users.role`; `users.role_id` may not exist, so index creation for `role_id` must remain conditional.
- Blog categories use `post_categories`; store/product categories use `categories`.
- `categories.is_active` was not found in the current schema.
- Important posts indexes:
    - `slug`
    - `category_id`
    - `published_lookup`
    - `category_published`
    - `show_on_home`
    - `priority`
    - `home_featured`
    - `popular_published`

## Search
- Public search is handled by app/Models/Search.php and app/Models/Post.php.
- Live suggestions are rendered by `public/assets/default/js/main.js`.
- Search dropdown must remain scrollable on small screens.
- Current LIKE-based search is acceptable for now but will not scale as well as FULLTEXT.

## Admin UI Notes
- `public/assets/default/js/select-init.js` initializes Choices/select behavior and floating dropdowns.
- `public/assets/default/js/admin-post-editor.js` controls the shared post/page editor, block menu, autosave, and related editor UI.
- Dropdown/select menus in the editor should avoid parent clipping; keep z-index/portal/floating behavior intact.
- Mobile admin sidebar must fit viewport and keep logout visible.

## PWA / SEO
- `public/assets/default/manifest.json` is the active web manifest.
- `public/service-worker.js` currently clears old caches and unregisters itself.
- SEO meta tags are generated in `app/Views/layouts/default.php`.
- The layout includes description, robots, canonical, OG, Twitter card, manifest and favicon links.
- No `public/robots.txt` or `public/sitemap.xml` is currently present.

## Pages Routing Rules
- Keep fixed routes before the public pages catch-all route.
- Public pages catch-all route is `/(?P<slug>[a-z0-9-]+)/?`.
- Do not allow page slugs that shadow first-level system routes such as `admin`, `posts`, `search`, `login`, `contacts`, etc.
- Do not change the DB field name `slug`; the admin label should say `URL`.

## Implementation Rules
- Do not create a second block editor for Pages.
- Do not rewrite the Posts editor just to support Pages.
- Keep Posts behavior compatible when modifying `post_form.php` or `admin-post-editor.js`.
- Prefer shared view/JS behavior controlled by `editor_mode`.
- If a feature is table-related, mirror the existing Posts table UX unless there is a strong reason not to.
- Keep changes small and compatible; this project has accumulated staged edits and dirty files.
- Do not revert unrelated user changes.
- When checking locally, note that MAMP/HTTP/MySQL may be unavailable from the terminal; use PHP lint/autoload checks when live testing is blocked.
