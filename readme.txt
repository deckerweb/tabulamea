=== TabulaMea ===
Contributors: daveshine
Tags: statistics, privacy, pageviews, dashboard
Tested up to: 7.1.3
Requires at least: 7.1.2
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Privacy-friendly WordPress statistics, content rankings and editorial pageview tools. No cookies or visitor profiles.

== Description ==

TabulaMea brings pageviews, popular content and referring websites into WordPress. The name comes from the Latin “tabula mea”: “my board”, and for this plugin also “my overview”.

* Count pageviews without cookies, persistent browser storage or visitor profiles. Statistics stay in your WordPress database.
* Read clear charts, compare periods, and explore exact values by mouse, touch or keyboard.
* Create content rankings and yearly roundups; choose a live list or a saved snapshot.
* Sort WordPress post and page lists by recorded pageviews, where editorial work happens.
* Choose a compact or modular dashboard widget. Control access by role and user.
* Present selected rankings and counts through native blocks and shortcodes, with separate public-output permissions.
* Optional country and technology categories and selected non-personal search topics remain coarse, independent aggregates.
* Run per website in Multisite, with network activation and isolated website statistics.

TabulaMea counts page loads, including reloads; it does not count unique people or sessions. DNT and GPC are respected. Public signatures validate paths and time, not human visits. Live referrers are bounded to 1,000 distinct domains per website and day; pageviews continue counting at that limit.

Illustrated guide: https://github.com/deckerweb/tabulamea/wiki/Guide
German guide: https://github.com/deckerweb/tabulamea/wiki/Guide-de

== Installation ==

1. Upload the installable TabulaMea ZIP in Plugins → Add New → Upload Plugin and activate it.
2. After installation or an update, clear the full-page/CDN HTML cache. Keep the HTML cache lifetime below seven days; one day (24 hours) is recommended. Reload already open test tabs.
3. Open Settings → TabulaMea → Overview and run the guided measurement test. It confirms delivery without counting a regular pageview.
4. Review measurement scope, permissions and dashboard preferences. Optional country downloads require separate consent.
5. Open TabulaMea → Statistics overview. Historical Koko Analytics or Statify data may be imported separately; the source tables are read only.

Cache setup: https://github.com/deckerweb/tabulamea/wiki/Cache

== Frequently Asked Questions ==


= What does TabulaMea count? =

TabulaMea counts visible page loads. Reloading a page counts as a new pageview. Only repeated technical delivery of the same page load, for example after a network error, is not counted twice. Collection exclusions and privacy signals still apply. It does not calculate unique visitors or sessions, track click events or build visitor profiles. Search pages are counted separately only when search reporting is enabled; 404 pages are excluded.

= Does it use cookies or external statistics services? =

TabulaMea does not set statistics cookies or use persistent browser storage for measurement. Statistics are stored locally. Optional country database downloads are separate server connections requiring consent; visitor information is not sent to the database provider.

= Who can see the numbers? =

Administrators by default. Access supports role and personal rules. The compact dashboard widget has separate permissions. Public output and exact public numbers need separate consent switches, and saved rankings also require individual publication.

= Why is my own test visit missing? =

Logged-in administrators and editors are excluded by default. Do Not Track, Global Privacy Control, browser blocking and disabled JavaScript can also prevent collection. Private windows may send privacy signals too. The guided test under Overview intentionally creates no regular pageview.

= How should I configure the page/CDN cache? =

Clear the full-page HTML cache in your caching plugin and, if present, at your host or CDN. Keep the HTML cache lifetime below seven days; one day (24 hours) is recommended. Reload already open test tabs. Measurement signatures expire after seven days, so old cached HTML can stop contributing counts. This does not require disabling page caching or changing browser image/CSS caches.

= Can public rankings show their order without displaying counts? =

Yes. Recorded counts remain available to authorized admin users. Public presentation has a separate choice: show the ranking order alone, or also display its counts. Saved rankings need individual publication; public counters need number permission.

= What happens on deactivation or uninstall? =

Deactivation preserves data. Uninstall removes plugin-owned temporary data and tasks; permanent statistics and settings remain by default. Only the explicit deletion option removes the relevant website’s permanent TabulaMea data. Other plugins’ data is never deleted.

Full FAQ: https://github.com/deckerweb/tabulamea/wiki/FAQ


== Changelog ==

= 1.0.0 — 2026-11-10 =

* New: Personal saved report views, clear active filters and shared periods; daily, weekly and monthly charts with exact-value tables.
* New: Native block patterns and illustrated guides for content maintenance, period comparisons and yearly rankings.
* Improved: Reused report queries, ranking caches with bounded rebuilds, maintenance diagnostics, import summaries and contextual CSV exports.
* Improved: Time-limited measurement signatures and bounded live referrer collection; clear cache setup and measurement-integrity guidance.
* Improved: deckerweb Library 1.3.0 and Updater 2.4.0; installed-plugin updates remain independent of the discovery catalog.
* Fixed: Registered shortcode calls, target-network lifecycle isolation, completed-day comparisons, abandoned cache locks and country database integrity checks.
* Misc: After installation or updating, clear the full-page/CDN HTML cache. Keep HTML cache lifetimes below seven days; one day is recommended. WordPress 7.1.2 and PHP 8.2 remain the minimum requirements.

= 0.9.1 — 2026-10-21 =

* Improved: Updated deckerweb Library 1.1.0 and Updater 2.2.1 with approved catalog downloads and localized notices.
* Improved: Load the measurement script with a deferred strategy to avoid blocking rendering.
* Fixed: GitHub update checks remain available when another plugin loads an older updater first.
* Fixed: Explicit SVG coordinate escaping and identifier-safe storage inspection. Completed translator guidance.

= 0.9.0 — 2026-10-19 =

* New: Independent, data-minimizing website statistics with content rankings, yearly roundups and pageview columns for editorial work.
* Improved: Charts, modular dashboard widgets, public output blocks, access controls and documentation. Library 1.0.0 and Updater 2.1.0 integrated for GitHub distribution. Faster broad yearly content rankings, ordinary content lists and chart queries without additional measurement writes or indexes. Bounded cleanup continuations for larger backlogs.
* Fixed: Statistics access, pageview columns, chart presentation and the placement of explanations and controls. Editor font-size previews and localized documentation dialogs. Atomic rollback and safe delivery retries after database errors or lost connections, including search counters. Atomic import transactions and import-source conflict checks. Public query-loop counter context, late-registered content types, dense annual charts and mobile settings layout.

= 0.1.0 – 0.8.0 =

* Misc: Early test versions of the independent website statistics plugin.
== License and credits ==

TabulaMea: GPL v2 or later. David Decker – DECKERWEB.
MaxMind DB Reader 1.13.1: Apache-2.0, used only to read the local MMDB format.
Optional DB-IP Country Lite: CC BY 4.0, https://db-ip.com/ . Downloads connect only after explicit administrator consent.
Inter font: SIL Open Font License; license included with the runtime artwork.
