=== Bulletin for bbPress ===
Contributors: jtzl, yoren
Tags: bbpress, forum, mobile, responsive, reading
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.2
Requires Plugins: bbpress
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A mobile-first, decluttered reading layer for bbPress. The post is the hero; navigation is deliberately secondary.

== Description ==

Bulletin makes a bbPress forum genuinely pleasant to read on a phone.

On the three reading screens — the forums index, a single forum's threads, and a thread itself — Bulletin renders its own minimal document instead of your theme's, in a comfortable serif reading face with quiet chrome. Everywhere else on your site, including bbPress screens Bulletin does not cover, your theme is left completely untouched.

It is a companion plugin. It never modifies bbPress, and it can be deactivated at any time with no trace.

**What it does**

* Full mobile takeover of the forums index, single forum, and reading view
* Continuous, decluttered reading — the thread is the content, not a card stack
* Inline "load more replies" instead of jarring pagination
* Thread-to-thread previous/next, scoped to the current forum, with boundary stops
* Deep links to any specific reply keep working, including past the first page
* Native bbPress subscribe is retained
* Fully responsive — built for a phone, scales cleanly to desktop

**What it deliberately does not do**

Bulletin is subtractive by design. It adds no navigational furniture, no "best posts" ranking, and no controls that compete with the content. Screens it has no design for are left on your theme rather than half-styled.

== Installation ==

1. Install and activate bbPress first — Bulletin has no effect without it.
2. Upload the plugin to `/wp-content/plugins/`, or install it through the Plugins screen.
3. Activate. There is nothing to configure.

== Frequently Asked Questions ==

= Does it change my theme? =

Only on the three bbPress reading screens, and only while it is active. Every other page renders exactly as it does now.

= Does it modify bbPress? =

No. Bulletin is a companion plugin and never edits bbPress core or its templates. It uses bbPress's own `bbp_template_include` filter.

= Which bbPress version is required? =

Developed and tested against bbPress 2.6.x, the current stable release line.

= Does it work with my forum's private or hidden forums? =

Yes. Bulletin defers to bbPress's own access control, including forums nested under a restricted parent.

== Changelog ==

= 0.1.0 =
* Initial release: mobile reading layer for the forums index, single forum, and thread views.
