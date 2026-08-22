=== Bulletin for bbPress ===
Contributors: jtzl, yoren
Tags: bbpress, forum, mobile, responsive, reading
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.2
Requires Plugins: bbpress
Stable tag: 0.5.3
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A mobile-first, decluttered reading layer for bbPress. The post is the hero; navigation is deliberately secondary.

== Description ==

Bulletin makes a bbPress forum genuinely pleasant to read on a phone.

On the reading screens — the forums index, a single forum's threads, a thread itself, and search — Bulletin renders its own minimal document instead of your theme's, in a comfortable serif reading face with quiet chrome. Every other bbPress screen a reader can reach — member profiles, the topic archive, tag archives, registered views — keeps bbPress's own markup, but inside Bulletin's chrome, so the forum reads as one place. Nothing outside bbPress is touched: the rest of your site renders exactly as it does now.

Taking part happens in the same place. Replying, starting a thread and editing your own post are Bulletin screens too, so a member never lands on a bare theme form halfway through answering someone. And every list of forums or threads marks what is new since that member last looked.

It is a companion plugin. It never modifies bbPress, and it can be deactivated at any time with no trace.

**What it does**

* Full mobile takeover of the forums index, single forum, reading view and search
* Every other bbPress screen wrapped in the same chrome rather than dropped on your theme
* Continuous, decluttered reading — the thread is the content, not a card stack
* Threaded replies placed under the reply they answer, when bbPress threading is on
* Inline "load more replies" instead of jarring pagination
* Reply, start a thread, and edit your own post without leaving Bulletin
* Unread marks on the forum and thread lists, per member, cleared by opening the thread
* Thread-to-thread previous/next, scoped to the current forum, with boundary stops
* Deep links to any specific reply keep working, including past the first page
* Moderation as a mode, off by default — the controls appear only when asked for
* Native bbPress subscribe is retained, on forums and on threads
* Fully responsive — built for a phone, scales cleanly to desktop

**What it deliberately does not do**

Bulletin is subtractive by design. It adds no navigational furniture, no "best posts" ranking, and no controls that compete with the content. The reply composer rests as a single control rather than an open form, and no compose control appears anywhere bbPress would not actually accept the post.

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

= Does it store anything? =

One table, `{prefix}jtzl_bltn_topic_reads`, recording which member has read which thread, plus the option holding that table's schema version. Both are removed on uninstall. Nothing is stored for logged-out visitors, who see no unread marks at all.

= Does Bulletin provide an API for a mobile app? =

Yes. Public reads are available under `/wp-json/jtzl-bulletin/v1/`. Personal state and posting use the authenticated WordPress user. For bearer-token mobile login, install and configure the JWT provider named in Bulletin's API documentation; Bulletin does not store or issue tokens itself.

= What can the API not do? =

By design, quite a lot. There is no delete route for a member's own topic or reply, no image or file upload, no way to unlock a password-protected forum from an app, no push notifications, no anonymous posting, and no moderation actions. Editing is authorship only: a member may edit their own post inside bbPress's edit window, and moderators use the website. The API applies exactly the same forum visibility, status, password and capability rules as your site does — it is a second way in, not a second set of permissions.

= Does it work with my forum's private or hidden forums? =

Yes. Bulletin defers to bbPress's own access control, including forums nested under a restricted parent.

== Changelog ==

= 0.5.3 =

A way out. Opening a form to edit a post now offers the same way to change your mind that writing a new one always has.

* Fixed: The topic and reply edit forms had a Submit and nothing beside it, while the reply composer has always offered Cancel — two forms that look alike behaving differently. Editing now carries a Cancel that returns a member to the post they were editing, and the back control at the top of those screens goes there too, instead of to the forums index.

= 0.5.2 =

Two more places a member could not see what had just happened. Both were reachable the moment 0.5.1 made a composer easy to open.

* Fixed: Starting a thread put the cursor in the post body, past the title — the one field the forum will not accept a thread without. A member could write a whole post and be refused for a field they had never been shown. The cursor now starts where each form starts: the title on a new thread, the message on a reply, and the name field for a member posting without an account.
* Fixed: A reply held for review by someone posting without an account left them at the top of the thread, with "Your reply is awaiting review." out of sight at the foot. Nothing else marks such a reply — no row, no badge, no profile to check — so from their side the post had simply vanished. They now land on the message.

= 0.5.1 =

Answering, without having to go looking for the form. Every way a member reaches a composer now puts it on the screen in front of them.

* Fixed: "Start a thread" opened the form at the foot of a long thread list, off the bottom of the screen. The control is fixed to the foot of the viewport and the form is not, so on a busy forum nothing appeared to happen and the button read as broken. Tapping it now moves the screen to the form.
* Fixed: A post the forum refused — an empty reply, a thread with no title — sent the member back to the top of the screen, with the reason and their own unsent draft out of sight at the foot. Both are now where they land.
* Fixed: The fixed "Start a thread" bar no longer sits over a form that is already open, where it offered a second time what the screen was already doing.

= 0.5.0 =

Reading, and answering. Bulletin has been somewhere to read a forum; it is now somewhere to take part in one. Replying, starting a thread and editing a post happen on Bulletin's own screens rather than dropping a member onto your theme mid-sentence — and every list of forums or threads now says what is new since that member last looked.

* Added: Reply from the thread you are reading. The composer rests as a single "Write a reply" control at the foot of the thread and opens on tap, so an unopened form never sits between the last reply and the end of the screen.
* Added: Start a thread from the forum screen, in the same vocabulary as the reply composer, and only where bbPress would actually accept the topic.
* Added: Unread marks on the forums index, a forum's threads, subscribed forums, and the bbPress screens Bulletin wraps. A thread is unread until that member opens it, and unread again when someone replies. Logged-out visitors see none of it, and nothing is stored for them.
* Added: A member can edit their own post from its byline, for as long as bbPress allows it. There is no mode to turn on first, and no control on a post that is not theirs.
* Added: A reply held for review is shown to its own author, marked as awaiting review, so it does not simply vanish on submission. Nobody else sees it.
* Added: The topic, reply and forum edit forms render inside Bulletin's chrome instead of dropping to your theme.
* Changed: Amber means unread and nothing else. The notice on wrapped bbPress screens now carries Bulletin's own caution colour instead of borrowing amber's.
* Changed: A closed forum or thread says so, in the place the composer would have been.
* Changed: Takeover screens no longer load bbPress scripts they have no use for.
* Fixed: The previous/next bar no longer shows a position count on a pinned thread, where the number contradicted the order the reader had just scrolled past.
* Fixed: The author of a closed thread was offered a reply form that bbPress would then refuse.

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
