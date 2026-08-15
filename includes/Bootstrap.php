<?php
/**
 * Runtime hook registration (composition root).
 *
 * @package JTZL\Bulletin
 * @since 0.1.0
 */

namespace JTZL\Bulletin;

use DI\Container;
use JTZL\Bulletin\Ajax\Endpoints;
use JTZL\Bulletin\Asset\AssetManager;
use JTZL\Bulletin\Asset\TakeoverScriptSuppressor;
use JTZL\Bulletin\Chrome\Furniture;
use JTZL\Bulletin\Chrome\Namings;
use JTZL\Bulletin\Chrome\UnreadClasses;
use JTZL\Bulletin\Database\Migrator;
use JTZL\Bulletin\Query\PendingVisibility;
use JTZL\Bulletin\Query\ProtectedStatusGuard;
use JTZL\Bulletin\Query\SearchVisibility;
use JTZL\Bulletin\Query\StableOrder;
use JTZL\Bulletin\Query\StickyHoisting;
use JTZL\Bulletin\Query\SubscribedForumQuery;
use JTZL\Bulletin\Takeover\TemplateController;
use JTZL\Bulletin\Unread\ReadPruner;
use JTZL\Bulletin\Unread\ReadWriter;
use JTZL\Bulletin\View\HeldNotice;
use JTZL\Bulletin\View\ProfileIdentity;
use JTZL\Bulletin\View\ProtectedRowContent;
use JTZL\Bulletin\View\SubscribedForumsMore;
use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Resolves services from the container and binds them to WordPress/bbPress
 * hooks through the context seam. This is the only place hooks are registered.
 *
 * Coupling is exempted deliberately, the same way ContainerFactory is excluded in
 * phpmd.xml: this is the composition root — deptrac's Root layer grants it every
 * internal layer on purpose — so its coupling counts the services the plugin has,
 * which is the thing it exists to wire. Honouring the cap would mean either moving
 * hook registration out of the one place that does it, or declining to add a
 * service. The size and complexity rules still apply, so register_hooks() cannot
 * quietly grow into something unreadable behind this.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 *
 * @since 0.1.0
 */
class Bootstrap {

	/**
	 * The DI container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Container $container The DI container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Register every runtime hook.
	 *
	 * Grouped by what each set of hooks is for rather than kept in one run: the list
	 * grows with every feature, and one method that registers all of it eventually
	 * says nothing about which hooks belong together. The groups are private and
	 * called from here alone, so this is still the one place hooks are registered.
	 *
	 * @since 0.1.0
	 */
	public function register_hooks(): void {
		$this->register_takeover();
		$this->register_assets();
		$this->register_ajax();
		$this->register_reskin();
		$this->register_search_visibility();
		$this->register_pending_visibility();
		$this->register_chrome();
		$this->register_unread();
	}

	/**
	 * Resolve one service from the container.
	 *
	 * Replaces a get-then-assert pair repeated thirty times. The assertion is not
	 * decoration — php-di is typed by convention, so PHPStan has to be told what came
	 * back — and one template says it for every caller.
	 *
	 * @since 0.5.0
	 *
	 * @template T of object
	 * @param string $service Fully-qualified class name.
	 * @phpstan-param class-string<T> $service
	 * @return T
	 */
	private function service( string $service ): object {
		$resolved = $this->container->get( $service );
		assert( $resolved instanceof $service );

		return $resolved;
	}

	/**
	 * Unread: keep the schema current, record what a member reads, mark what they
	 * have not, and forget rows about things that no longer exist (issue #102).
	 *
	 * The schema check runs on every request rather than on activation alone, because
	 * a plugin can reach a new version without its activation hook ever firing — see
	 * Database\Migrator. It costs one option read when nothing has changed.
	 *
	 * @since 0.5.0
	 */
	private function register_unread(): void {
		$wp       = $this->wp();
		$migrator = $this->service( Migrator::class );
		$writer   = $this->service( ReadWriter::class );
		$pruner   = $this->service( ReadPruner::class );
		$classes  = $this->service( UnreadClasses::class );

		$migrator->maybe_upgrade();

		// Late on template_redirect, so anything that redirects away from this screen
		// — the single-reply redirect at priority 9, a login gate, a canonical fix —
		// has already run and we do not record a thread the reader never arrived at.
		$wp->add_action( 'template_redirect', array( $writer, 'record' ), 100 );

		// Priming runs on the loop, marking on the row. Both halves are scoped to the
		// reskin tier inside UnreadClasses; the takeover screens carry the accent in
		// our own row markup instead.
		$wp->add_filter( 'bbp_has_topics', array( $classes, 'prime_topics' ), 10, 2 );
		$wp->add_filter( 'bbp_has_forums', array( $classes, 'prime_forums' ), 10, 2 );
		$wp->add_filter( 'bbp_get_topic_class', array( $classes, 'filter_topic_class' ), 10, 2 );
		$wp->add_filter( 'bbp_get_forum_class', array( $classes, 'filter_forum_class' ), 10, 2 );

		// The dot is drawn in CSS off the class above; these print the words behind
		// it, so unread is never carried by colour alone on this tier either.
		$wp->add_action( 'bbp_theme_before_topic_title', array( $classes, 'announce_topic' ) );
		$wp->add_action( 'bbp_theme_before_forum_title', array( $classes, 'announce_forum' ) );

		// Two args: the type is read off the deleted post rather than looked up, which
		// would depend on a post cache that has not been invalidated yet.
		$wp->add_action( 'deleted_post', array( $pruner, 'forget_deleted_topic' ), 10, 2 );
		$wp->add_action( 'deleted_user', array( $pruner, 'forget_deleted_user' ) );
	}

	/**
	 * Takeover: redirect single replies early, strip theme-compat, swap our document.
	 *
	 * @since 0.3.0
	 */
	private function register_takeover(): void {
		$wp       = $this->wp();
		$takeover = $this->service( TemplateController::class );

		$wp->add_action( 'template_redirect', array( $takeover, 'redirect_single_reply' ), 9 );
		$wp->add_action( 'template_redirect', array( $takeover, 'prime_takeover' ) );
		$wp->add_filter( 'bbp_template_include', array( $takeover, 'filter_template_include' ), 20 );
	}

	/**
	 * Assets: enqueue ours, then suppress foreign styles and dead scripts late.
	 *
	 * @since 0.3.0
	 */
	private function register_assets(): void {
		$wp      = $this->wp();
		$assets  = $this->service( AssetManager::class );
		$scripts = $this->service( TakeoverScriptSuppressor::class );

		$wp->add_action( 'wp_enqueue_scripts', array( $assets, 'enqueue' ) );
		$wp->add_action( 'wp_enqueue_scripts', array( $assets, 'suppress_foreign_styles' ), 100 );
		$wp->add_action( 'wp_enqueue_scripts', array( $scripts, 'suppress' ), 100 );
		$wp->add_action( 'wp_print_scripts', array( $scripts, 'suppress' ), PHP_INT_MAX );
		$wp->add_action( 'wp_footer', array( $scripts, 'suppress' ), 19 );
		$wp->add_filter( 'generate_print_a11y_script', array( $scripts, 'filter_generatepress_a11y' ) );
	}

	/**
	 * Load-more over bbPress's front-end AJAX router: replies inside a thread,
	 * threads inside a forum, forums inside the index or a parent forum — and the
	 * first continuation on a reskin screen, the Subscribed Forums list on a member
	 * profile, which bbPress renders in its own markup and truncates at the same
	 * 50-forum ceiling (issue #50) — and results inside a search (issue #35), the
	 * only one of the five whose subject is a set of terms rather than a post.
	 *
	 * @since 0.3.0
	 */
	private function register_ajax(): void {
		$this->service( Endpoints::class )->register();
	}

	/**
	 * Reskin: what we add to, or correct in, the markup bbPress renders itself.
	 *
	 * @since 0.3.0
	 */
	private function register_reskin(): void {
		$wp              = $this->wp();
		$identity        = $this->service( ProfileIdentity::class );
		$order           = $this->service( StableOrder::class );
		$stickies        = $this->service( StickyHoisting::class );
		$subscriptions   = $this->service( SubscribedForumQuery::class );
		$subscribed_more = $this->service( SubscribedForumsMore::class );
		$protected       = $this->service( ProtectedRowContent::class );

		// Give the member-profile header a coherent identity block (name + @handle +
		// role beside the avatar). The hook fires only inside bbPress's user-details
		// template — i.e. the reskinned profile screens — so it never touches the
		// takeover documents.
		$wp->add_action( 'bbp_template_before_user_details_menu_items', array( $identity, 'render' ) );

		// Hang a continuation off the Subscribed Forums list, the one reskin loop
		// bbPress leaves with no way past its first page. Both halves are scoped to
		// the subscriptions tab: the control by the hook and the conditional (see
		// View\SubscribedForumsMore), the query by the conditional alone, since
		// bbPress's own template is what calls it and passes no arguments to intercept.
		$wp->add_action( 'bbp_template_after_forums_loop', array( $subscribed_more, 'render' ) );
		$wp->add_filter( 'bbp_after_has_forums_parse_args', array( $subscriptions, 'filter_forum_args' ) );

		// Give the loops bbPress queries for us a deterministic order. These fire in
		// the moment before bbPress runs the query, on every call — including the
		// profile tabs, which reach bbp_has_topics() with args of their own.
		$wp->add_filter( 'bbp_after_has_topics_parse_args', array( $order, 'filter_topic_args' ) );
		$wp->add_filter( 'bbp_after_has_replies_parse_args', array( $order, 'filter_reply_args' ) );

		// And decline bbPress's sticky hoisting there, which serves a sticky twice and
		// miscounts the page it hoisted onto. Same hook, separate decision.
		$wp->add_filter( 'bbp_after_has_topics_parse_args', array( $stickies, 'filter_topic_args' ), 11 );

		// Keep WordPress's password form out of the description slot of a loop row,
		// where bbPress's own row templates would otherwise print it as though it were
		// what the forum is about (issue #54). Armed by the four actions bbPress fires
		// around those two slots and by nothing else, so the forms Bulletin renders on
		// purpose — the in-shell prompt (#18), and a protected reply's own prompt in the
		// reading view — cannot be reached by it. See View\ProtectedRowContent.
		$wp->add_action( 'bbp_theme_before_forum_description', array( $protected, 'open_row_slot' ) );
		$wp->add_action( 'bbp_theme_after_forum_description', array( $protected, 'close_row_slot' ) );
		$wp->add_action( 'bbp_theme_before_reply_content', array( $protected, 'open_row_slot' ) );
		$wp->add_action( 'bbp_theme_after_reply_content', array( $protected, 'close_row_slot' ) );
		$wp->add_filter( 'the_password_form', array( $protected, 'filter_password_form' ) );
	}

	/**
	 * Search visibility: give a search query back the post statuses bbPress
	 * computed for the reader running it and then overwrote (issue #68) — which
	 * loses every `closed` topic and admits `private` and `hidden` ones.
	 *
	 * Its own group because it belongs to neither tier. The captured list rides
	 * the query object, so these fire on any query built by
	 * `bbp_has_search_results()` and on nothing else — bbPress's own search
	 * template included, the defect being upstream of our takeover.
	 *
	 * `pre_get_posts` at 5 lands immediately after bbPress's normalizer at 4, the
	 * narrowest way to undo one substitution. See Query\SearchVisibility.
	 *
	 * @since 0.3.0
	 */
	private function register_search_visibility(): void {
		$wp     = $this->wp();
		$search = $this->service( SearchVisibility::class );

		// Last on the arguments filter, so what is copied is what the query will
		// actually use: a site that widens or narrows post_status through the same
		// hook has had its say by then, and a copy taken before it would restore
		// bbPress's answer over the site's own.
		$wp->add_filter( 'bbp_after_has_search_results_parse_args', array( $search, 'capture_statuses' ), PHP_INT_MAX );
		$wp->add_action( 'pre_get_posts', array( $search, 'widen_statuses' ), 5 );
		$wp->add_filter( 'posts_where', array( $search, 'restrict_statuses' ), 10, 2 );
	}

	/**
	 * Moderation held a reply: withhold it from everyone, show it back to its author,
	 * and acknowledge the two cases where no row comes back (§3 decision 6).
	 *
	 * Nothing here arms either query rule — Query\ReplyQuery and Query\TopicQuery do,
	 * at every one of their sites, which is what keeps those sites agreeing about a
	 * held row rather than paging around one (CLAUDE.md trap #4).
	 *
	 * @since 0.5.0
	 */
	private function register_pending_visibility(): void {
		$wp      = $this->wp();
		$pending = $this->service( PendingVisibility::class );
		$guard   = $this->service( ProtectedStatusGuard::class );
		$held    = $this->service( HeldNotice::class );
		// ⚠ Guard early, widening last, and the order is load-bearing: the guard
		// subtracts, the widening then wraps the whole clause and OR-s one row back
		// in. Swapped, the subtraction lands outside the wrap and removes it again —
		// see Query\ProtectedStatusGuard.
		$wp->add_filter( 'posts_where', array( $guard, 'restrict' ), 10, 2 );
		$wp->add_filter( 'posts_where', array( $pending, 'widen' ), PHP_INT_MAX, 2 );
		$wp->add_filter( 'bbp_new_reply_redirect_to', array( $held, 'filter_redirect' ), 10, 3 );
	}

	/**
	 * Chrome: the furniture the shell paints around a screen, and the names bbPress
	 * leaves off its own controls.
	 *
	 * ⚠ **Two classes rather than the run of bindings that used to live here.** The
	 * group was eight services deep and `Bootstrap` had spent three PRs a handful of
	 * lines under PHPMD's class-length ceiling — close enough that the next hook would
	 * have been paid for by deleting an explanation. It is split on a seam the
	 * comments had already drawn: `Chrome\Namings` supplies a name where bbPress
	 * supplies none, `Chrome\Furniture` changes or removes something it already
	 * renders. Both keep the comments the bindings were written with.
	 *
	 * @since 0.3.0
	 */
	private function register_chrome(): void {
		$this->service( Furniture::class )->register();
		$this->service( Namings::class )->register();
	}

	/**
	 * The WordPress/bbPress seam every group binds through.
	 *
	 * @since 0.3.0
	 *
	 * @return ContextInterface
	 */
	private function wp(): ContextInterface {
		$wp = $this->service( ContextInterface::class );

		return $wp;
	}
}
