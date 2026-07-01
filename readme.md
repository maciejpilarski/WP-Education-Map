# WP Education Map

**Contributors:** Maciej Pilarski
**Tags:** map, education, meetup, wpcc, wpcredits
**Requires at least:** 5.8
**Tested up to:** 6.7
**Requires PHP:** 7.4
**Stable tag:** 1.3.0
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

Displays a world map with city-level markers for WPCC, WPCredits, and Student Club activity, with a Dashboard settings section for managing institutions.

## Description

Implements https://github.com/WordPress/wordpress.org/issues/584.

This plugin adds an "Education Map" section to the WordPress Dashboard where an administrator can add, edit, and delete institutions (name, city, country, coordinates, program, and event count) for WordPress Campus Connect (WPCC), WPCredits, and Student Club activity. Coordinates can be set by clicking on an interactive map instead of typing them by hand. Additional program types beyond the built-in three can be added from the Programs screen.

Use the `[wp_education_map]` shortcode on any page or post to display a world map with city-level markers sized by event count, filterable by program, styled to match the existing Meetup map on events.wordpress.org.

The map's on-page size (width and height) can be set once for the whole site under Dashboard > Education Map > Settings, or overridden per shortcode instance.

### Shortcode

```
[wp_education_map]
```

Optional attributes:

- `program` — pre-filter the map to one of `wpcc`, `wpcredits`, or `student_club`.
- `height` — CSS height of the map container (defaults to the value set in Education Map > Settings, e.g. `520px`).
- `width` — CSS width of the map container (defaults to the value set in Education Map > Settings, e.g. `100%`).

## Installation

1. Upload the `wp-education-map` folder to `/wp-content/plugins/`.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Go to Dashboard > Education Map > Add New to add your first institution.
4. Add `[wp_education_map]` to any page to display the map.

## Changelog

### 1.3.0

- Pass WordPress-Extra coding standards cleanly (0 errors, 0 warnings): fixed unescaped output, unsanitized `$_POST` reads, formatting/alignment, and pre/post-increment style.
- Pass WordPressVIPMinimum with 0 errors; the only remaining warnings are "direct DB query without object caching," expected for a plugin with its own custom table.

### 1.2.2

- Fix: the map's REST URL and program list were passed to the browser via `wp_localize_script()`, which some hosts' JS-deferral/optimization features strip from the page, leaving the map with no data and no markers. This data now travels as `data-*` attributes on the map container itself, which is more resilient to that kind of output manipulation.

### 1.2.1

- Harden the REST API response by re-validating the `website` field with `esc_url_raw()` before output.

### 1.2.0

- Add a Programs screen (Dashboard > Education Map > Programs) to add or delete custom program types, in addition to the built-in WPCC, WPCredits, and Student Club.
- Add an interactive map picker to the Add New / Edit Institution screen — click or drag a marker to set coordinates instead of typing them by hand.

### 1.1.0

- Add a Settings screen (Dashboard > Education Map > Settings) to control the map's width and height site-wide, with per-shortcode overrides.

### 1.0.0

- Initial release: Dashboard CRUD for institutions, REST endpoint, and frontend map shortcode.
