=== Bulletin for bbPress ===
Contributors: jtzl, yoren
Tags: bbpress, forum, mobile, responsive, reading
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.2
Requires Plugins: bbpress
Stable tag: 0.4.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A mobile-first, decluttered reading layer for bbPress. The post is the hero; navigation is deliberately secondary.

== Description ==

Bulletin makes a bbPress forum genuinely pleasant to read on a phone.

On the reading screens — the forums index, a single forum's threads, a thread itself, and search — Bulletin renders its own minimal document instead of your theme's, in a comfortable serif reading face with quiet chrome. Every other bbPress screen a reader can reach — member profiles, the topic archive, tag archives, registered views — keeps bbPress's own markup, but inside Bulletin's chrome, so the forum reads as one place. Nothing outside bbPress is touched: the rest of your site renders exactly as it does now.

It is a companion plugin. It never modifies bbPress, and it can be deactivated at any time with no trace.

**What it does**

* Full mobile takeover of the forums index, single forum, reading view and search
* Every other bbPress screen wrapped in the same chrome rather than dropped on your theme
* Continuous, decluttered reading — the thread is the content, not a card stack
* Threaded replies placed under the reply they answer, when bbPress threading is on
* Inline "load more replies" instead of jarring pagination
* Thread-to-thread previous/next, scoped to the current forum, with boundary stops
* Deep links to any specific reply keep working, including past the first page
* Moderation as a mode, off by default — the controls appear only when asked for
* Native bbPress subscribe is retained, on forums and on threads
* Fully responsive — built for a phone, scales cleanly to desktop

**What it deliberately does not do**

Bulletin is subtractive by design. It adds no navigational furniture, no "best posts" ranking, and no controls that compete with the content. bbPress's own edit forms for a topic, reply or forum are left on your theme rather than half-styled.

== Installation ==

1. Install and activate bbPress first — Bulletin has no effect without it.
2. Upload the plugin to `/wp-content/plugins/`, or install it through the Plugins screen.
3. Activate. There is nothing to configure.

== Frequently Asked Questions ==

= Does it change my theme? =

Only on bbPress screens, and only while it is active. Every other page on your site renders exactly as it does now.

= Does it modify bbPress? =

No. Bulletin is a companion plugin and never edits bbPress core or its templates. It uses bbPress's own `bbp_template_include` filter.

= Which bbPress version is required? =

Developed and tested against bbPress 2.6.x, the current stable release line.

= Does it work with my forum's private or hidden forums? =

Yes. Bulletin defers to bbPress's own access control, including forums nested under a restricted parent.

== Changelog ==

= 0.4.0 =

One surface for every list. The bbPress screens Bulletin wraps rather than replaces were striping their rows while the reading screens were not, so a list of threads changed appearance one tap apart.

* Changed: List rows on member profiles, the topic archive, tag archives and registered views no longer alternate between two background tints. Every row sits on the same surface, and the divider between rows now reads at full strength on all of them — carrying the boundary the tint was kept for, the way the reading screens already did.
* Fixed: On the five member profile tabs, a row's background and the divider under it stopped short of both screen edges. They run the full width now, like every other list in the plugin.

= 0.3.0 =

The complete forum surface. Bulletin no longer covers only the three reading screens — every bbPress screen a reader can reach now renders inside Bulletin's chrome, so the forum stops changing character halfway through.

* Added: Search is a Bulletin screen, reachable from a magnifier in the app bar everywhere. Forums, threads and replies are interleaved by date, and each row says which of the three it is.
* Added: Threaded replies. With bbPress threading on, each reply is placed under the one it answers and carries a link back to it — an order, not an indent that runs out of width on a phone.
* Added: Member profiles and their tabs, the topic archive, tag archives and registered views now render inside Bulletin's chrome instead of dropping to your theme.
* Added: Moderation is a mode on the reading view, off by default — a moderator turns it on, and everyone else never sees the controls.
* Added: A thread's tags sit with the thread rather than in a separate strip.
* Added: A home button in the app bar, and the way back up is named instead of an unlabelled chevron.
* Added: Password-protected forums and threads keep the reader in Bulletin. WordPress's own password form renders in place of the content until the password is supplied, and a protected post gives nothing away in a list row.
* Added: "Load more" for forum lists past bbPress's 50-forum ceiling, for the thread list past its first page, and for the Subscribed Forums profile tab.
* Changed: One type scale across the whole plugin, replacing eighteen ad-hoc font sizes.
* Changed: Every touch target in bbPress's own markup is at least 24px, meeting WCAG 2.2 target size, without moving a single row.
* Changed: bbPress's forms — profile edit, moderation, and the login, register and lost-password shortcodes — are dressed to match the rest of Bulletin instead of sitting on browser defaults.
* Changed: The WordPress admin bar is hidden for everyone but administrators, so a logged-in reader gets the whole screen.
* Changed: Thread previous/next seeks the adjacent thread instead of listing every thread in the forum.
* Fixed: Reply and thread paging could duplicate or drop rows when two posts shared a timestamp.
* Fixed: Search could return private or hidden topics, and replies belonging to a topic the reader cannot read.
* Fixed: A closed thread now says it is closed, in search results as well as in lists.
* Fixed: Super stickies sit above forum stickies, and a forum-level sticky is no longer hoisted onto the topic archive.
* Fixed: A long screen title could set the width of the whole app, clipping the right edge of every screen — worst at 200% zoom, where it cut content.
* Fixed: The shell could be pushed out of position by anything that scrolls the page programmatically: a deep link, find-in-page, or assistive technology.
* Fixed: Each reply has its own permalink, on its timestamp, and it survives past the first page.
* Security: AJAX responses no longer let a restricted forum be told apart from one that does not exist.
* Security: Screen headings are escaped, and a continuation request is bounded to the page it answers for.

= 0.2.0 =
* Added: reading-view Subscribe control — subscribe to the thread you are reading, matching the native forum-level subscribe. Gated on active subscriptions and a logged-in user.

= 0.1.0 =
* Initial release: mobile reading layer for the forums index, single forum, and thread views.
