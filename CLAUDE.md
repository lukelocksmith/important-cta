# Blog Lead Magnet — AI Developer Guide

## What is this plugin?

WordPress plugin that auto-injects CTA blocks, content gates (email paywalls), a floating navigation bar, and analytics into blog posts. Configurable per-category with a tabbed admin UI.

**Internal codename:** `important-cta` (historical name, kept for backward compatibility)
**Display name:** Blog Lead Magnet
**Option prefix:** `icta_` (WordPress options table)
**CSS class prefix:** `icta-` (CTA blocks), `blm-` (floating bar)

## File structure

```
important-cta/
├── important-cta.php              # Entry point — constants, auto-updater, module loading
├── includes/
│   ├── class-cta-settings.php     # Admin settings page (tabbed, per-category)
│   ├── class-cta-injector.php     # Injects CTA 1/2/3 into post content via the_content filter
│   ├── class-content-gate.php     # Email paywall — hides content after Nth H2
│   ├── class-analytics.php        # Event tracking — DB table, AJAX endpoint, admin dashboard
│   └── class-floating-bar.php     # Bottom floating bar — TOC + CTA button
├── assets/
│   ├── css/
│   │   ├── cta-block.css          # Frontend styles for CTA 1/2/3
│   │   ├── content-gate.css       # Frontend styles for content gate
│   │   ├── floating-bar.css       # Frontend styles for floating bar
│   │   └── admin.css              # Admin panel styles
│   └── js/
│       ├── admin.js               # WP Color Picker + Media Library picker
│       ├── analytics.js           # IntersectionObserver view tracking + click tracking
│       ├── content-gate.js        # Gate unlock via Fluent Forms + localStorage
│       └── floating-bar.js        # TOC generation + active heading highlight
├── vendor/
│   └── plugin-update-checker/     # YahnisElsts PUC v5.3 — auto-updates from GitHub
├── CLAUDE.md                      # This file
└── RELEASING.md                   # How to release new versions
```

## Architecture

### Module system

Each feature is a self-contained PHP class instantiated in `important-cta.php`:

| Class | Hooks into | WP Option | Admin page |
|-------|-----------|-----------|------------|
| `ICTA_Settings` | `admin_menu`, `admin_init` | `icta_settings` | Settings → Blog Lead Magnet |
| `ICTA_Injector` | `the_content` (priority 20) | reads `icta_settings` | — |
| `ICTA_Content_Gate` | `the_content` (priority 15), `add_meta_boxes` | reads `icta_settings` + post meta `_icta_gate_enabled` | — |
| `ICTA_Analytics` | `wp_ajax_*`, `wp_enqueue_scripts` | DB table `wp_icta_events` | Settings → BLM Analityka |
| `ICTA_Floating_Bar` | `wp_footer`, `wp_enqueue_scripts` | `icta_floating` | Settings → BLM Pływający pasek |

### Content filter execution order

```
Priority 15: ICTA_Content_Gate::apply_gate()   — splits content, adds gate
Priority 20: ICTA_Injector::inject()            — inserts CTA 1/2/3
```

Gate runs FIRST so CTAs are injected into the visible portion only.

### Settings data model

**`icta_settings`** option (serialized array):

```php
[
    '_global_cta1' => ['enabled' => true, 'headline' => '...', 'bg_color' => '#18181b', ...],
    '_global_cta2' => [...],
    '_global_cta3' => [...],
    '_global_gate' => [...],
    'wordpress_cta1' => [...],  // category-specific override (only if explicitly enabled)
    ...
]
```

Key format: `{category_slug}_{position}` where position is `cta1|cta2|cta3|gate`.

**Fallback chain** in `ICTA_Settings::get($cat_slug, $pos)`:
1. Category-specific entry (only if `enabled = true`)
2. Global entry (`_global_{$pos}`)
3. Code defaults from `ICTA_Settings::defaults($pos)`

**Critical design decision:** Only the active tab's form fields are rendered in the HTML. This prevents saving one tab from overwriting other tabs' checkbox states (unchecked checkboxes aren't submitted in HTTP POST).

### CTA positions

| Position | Class | Where | Trigger |
|----------|-------|-------|---------|
| CTA 1 — Expert Block | `icta-block--1` | Before Nth H2 (default: 2nd) | `h2_trigger` field |
| CTA 2 — Mini Nudge | `icta-block--2` | Before Nth H2 (default: 4th) | `h2_trigger` field |
| CTA 3 — Lead Magnet | `icta-block--3` | End of article | always |
| Content Gate | `icta-gate` | Replaces content after Nth H2 | `h2_trigger` field or post meta |

### Content Gate unlock flow

1. PHP splits content at H2 trigger → visible part + hidden `<div class="icta-gated-content" style="display:none">`
2. Gate CTA with Fluent Forms shortcode rendered between visible and hidden
3. `content-gate.js` watches for form submission via MutationObserver (detects `.ff-message-success`)
4. On success: sets `localStorage('icta_unlocked_{post_id}')`, reveals hidden content
5. On next visit: JS checks localStorage, shows content immediately (no server round-trip)

### Analytics tracking

**DB table:** `wp_icta_events`
```sql
id          bigint PRIMARY KEY AUTO_INCREMENT
post_id     bigint NOT NULL
cta_type    varchar(20)  -- cta1, cta2, cta3, gate
event_type  varchar(20)  -- view, click, unlock
created_at  datetime DEFAULT CURRENT_TIMESTAMP
INDEX (post_id, cta_type, event_type)
```

**Frontend:** `analytics.js` uses IntersectionObserver (50% threshold) for views, delegated click events for buttons, and `icta:unlocked` custom event for gate unlocks. Each event type fires once per page load (deduped via Set).

**AJAX:** `wp_ajax_icta_track_event` with nonce validation + rate limiting (10/IP/minute via transients).

### Floating Bar

Rendered via `wp_footer` (priority 50). Three modes:
- `both` — author avatar + CTA button + TOC toggle
- `cta_only` — author + button only
- `toc_only` — TOC toggle only

TOC auto-generated from `#post-content h2, h3` headings. Active heading tracked via IntersectionObserver.

## Default CTA colors

```
bg_color:   #18181b   (dark background — stands out from white article)
btn_color:  #e22007   (red accent)
text_color: #ffffff   (white text)
```

## CSS conventions

- **BEM naming** — `.block__element--modifier`
- **No `border-left` or `border-right` with accent color** on any element
- **Use `var(--color-accent)` instead of hardcoded `#e22007`** in theme CSS (plugin CSS can use hex directly)
- **Tables:** `display: inline-block; width: auto` — never stretch to 100%
- CTA prefix: `icta-` | Floating bar prefix: `blm-`

## Deployment

### To production server

```bash
cd /path/to/important-cta

# 1. Bump version in important-cta.php (both Version: header and ICTA_VERSION constant)

# 2. Zip (exclude .git)
zip -r /tmp/important-cta.zip . --exclude "*.git*"

# 3. Upload & deploy
scp /tmp/important-cta.zip root@SERVER:/tmp/
ssh root@SERVER "
  docker cp /tmp/important-cta.zip CONTAINER:/tmp/
  docker exec CONTAINER bash -c '
    rm -rf /tmp/icta-tmp
    unzip -q /tmp/important-cta.zip -d /tmp/icta-tmp
    rm -rf /var/www/html/wp-content/plugins/important-cta
    mkdir -p /var/www/html/wp-content/plugins/important-cta
    cp -r /tmp/icta-tmp/assets /tmp/icta-tmp/includes /tmp/icta-tmp/templates \
          /tmp/icta-tmp/vendor /tmp/icta-tmp/important-cta.php \
          /tmp/icta-tmp/RELEASING.md /tmp/icta-tmp/CLAUDE.md \
          /var/www/html/wp-content/plugins/important-cta/
    echo Done
  '
"
```

**WARNING:** Never `unzip -d important-cta` directly — creates nested structure. Always unzip to temp dir, then copy files.

### GitHub Release (triggers auto-update)

```bash
git add . && git commit -m "Release vX.Y.Z"
git push origin main
gh release create vX.Y.Z --title "vX.Y.Z" --notes "Changelog..."
```

Tag MUST have `v` prefix. WordPress detects updates via plugin-update-checker polling GitHub Releases.

## Key gotchas

1. **Checkboxes in tabbed forms** — unchecked checkboxes don't submit. Only render the active tab's fields in the form. Sanitize merges with existing `get_option()` data.

2. **Fluent Forms formSettings** — forms created via raw `$wpdb->insert` won't render. `wp_fluentform_form_meta` MUST have a `formSettings` row. Use FF UI or insert meta manually.

3. **`is_singular('post')` in WP-CLI** — always returns false (no HTTP request context). Test CTA injection via `curl`, not `wp eval`.

4. **Content Gate priority** — must run BEFORE CTA injector (15 < 20), otherwise CTAs get injected into hidden content.

5. **CSS cache busting** — ICTA_VERSION is used as the enqueue version parameter. Always bump when changing CSS/JS.

## Adding a new feature

1. Create `includes/class-your-feature.php` with a class
2. Add CSS to `assets/css/` and JS to `assets/js/`
3. `require_once` and `new YourClass()` in `important-cta.php`
4. If it needs settings, either:
   - Add a position to `ICTA_Settings::defaults()` and render in `render_cta_fields()` (for per-category CTA-like features)
   - Create its own WP option and settings page (for global features like Floating Bar)
5. Bump ICTA_VERSION
