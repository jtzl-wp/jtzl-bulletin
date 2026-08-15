<?php
/**
 * Keep WordPress's admin status list out of a front-end loop.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\Query;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * Stops a Bulletin loop returning `draft`, `future` and `pending` posts to readers
 * who may not see them — which, in an AJAX request, is what WordPress does by
 * default.
 *
 * ## The hazard, measured — and its true reach, also measured
 *
 * When `is_admin()` is true, `WP_Query::parse_query()` copies it to `$this->is_admin`
 * unconditionally (`class-wp-query.php:1026`), and the branch that builds a status
 * clause for a query with **no explicit `post_status`** then adds every protected
 * status flagged `show_in_admin_all_list` (`:2735`). For a status-less reply query,
 * to a **logged-out** caller:
 *
 * ```sql
 * AND post_type = 'reply' AND ( post_status = 'publish' OR post_status = 'closed'
 *   OR post_status = 'future' OR post_status = 'draft' OR post_status = 'pending' )
 * ```
 *
 * — no author restriction, no capability test. Verified on WordPress 6.9 with bbPress
 * 2.6.14 under `WP_Ajax_UnitTestCase`, against both affected loops.
 *
 * ⚠ **This is latent, not live, and the distinction was checked rather than assumed.**
 * `is_admin()` is true in `admin-ajax.php`; it is **false** on the route Bulletin's
 * load-more endpoints actually use. bbPress runs its own theme-side dispatcher —
 * `bbp_do_ajax()` on `bbp_post_request`, off `bbp_template_redirect`
 * (`common/ajax.php:68`, `core/actions.php:449`) — reached at `bbp_get_ajax_url()`,
 * a front-end URL carrying `bbp-ajax=true`, which is exactly what
 * `Asset\AssetManager` hands the client. That route defines `DOING_AJAX` and never
 * `WP_ADMIN`, so `is_admin()` returns false and WordPress builds the ordinary
 * front-end clause. **No shipped Bulletin release has disclosed anything this way.**
 *
 * So this is insurance, and it is worth its weight for one reason: the invariant it
 * states — *a Bulletin loop returns publicly visible statuses only* — currently holds
 * by accident of routing rather than by anything the query says. Move one endpoint to
 * `admin-ajax.php`, or call these argument builders from anywhere admin-side, and the
 * hole opens with nothing to notice it. It cost one narrow clause and its failure mode
 * is a missing row.
 *
 * ## Which loops, and why only those
 *
 * The branch only runs when `post_status` is **empty**, so it could only ever reach
 * the two bbPress loops that lean on `perm => readable` instead of naming statuses:
 * `bbp_has_replies()` and `bbp_has_topics()`. `bbp_has_forums()` sets
 * `post_status` to the public status and `bbp_has_search_results()` builds an
 * explicit list, so neither was ever exposed — checked rather than assumed.
 *
 * ## The rule
 *
 * Subtract the statuses WordPress admits *only* because it thinks this is an admin
 * screen, and only on a query that left `post_status` to WordPress — the same
 * condition WP's own branch is guarded by. A query that names its statuses has
 * already had its say, which is what keeps a moderator's `view=all` (where bbPress
 * names `pending` deliberately) working exactly as before.
 *
 * Applied on every request rather than only under `is_admin()`, because then the page
 * render and its AJAX continuation are the same measurement — the property CLAUDE.md's
 * trap #4 exists to protect. Off an admin request the clause subtracts statuses that
 * were never admitted, and costs one `NOT IN` nobody notices.
 *
 * ## Ordering against Query\PendingVisibility
 *
 * ⚠ **These two must stay on opposite ends of `posts_where`, and it is not
 * cosmetic.** This one narrows and registers early; the widening rewrites the whole
 * clause and registers at `PHP_INT_MAX`, so the result is
 *
 * ```sql
 * AND ( 1=1 <original AND this subtraction> OR ( the reader's own held reply ) )
 * ```
 *
 * The author's own held reply is removed by this and put back by that. Registered the
 * other way round, the subtraction would land outside the wrap and take the row away
 * again — the feature would silently do nothing, and only on a threaded forum's
 * second page would anyone notice.
 *
 * @since 0.5.0
 */
class ProtectedStatusGuard {

	/**
	 * The query variable marking a loop as one of ours.
	 *
	 * @var string
	 */
	private const MARKER = 'bltn_front_end';

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param ContextInterface $wp WordPress/bbPress seam.
	 */
	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * The marker, for a caller that merges argument sets itself.
	 *
	 * @since 0.5.0
	 *
	 * @return array<string,mixed>
	 */
	public function marker(): array {
		return array( self::MARKER => true );
	}

	/**
	 * Arm a set of query arguments.
	 *
	 * @since 0.5.0
	 *
	 * @param array<string,mixed> $args Arguments to arm.
	 * @return array<string,mixed>
	 */
	public function arm( array $args ): array {
		return array_merge( $args, $this->marker() );
	}

	/**
	 * Subtract the admin-only statuses from a marked query. Hooked on `posts_where`
	 * early, so `Query\PendingVisibility`'s widening can still put one row back.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $where The WHERE clause built so far.
	 * @param mixed $query The query it belongs to.
	 * @return mixed
	 */
	public function restrict( $where, $query = null ) {
		if ( ! is_string( $where ) || true !== $this->wp->get_query_arg( $query, self::MARKER ) ) {
			return $where;
		}

		// The same guard WordPress's own branch carries: a query that named its
		// statuses never went near the admin list, and narrowing it here would take
		// away a moderator's `view=all` rather than a stranger's disclosure.
		$named = $this->wp->get_query_arg( $query, 'post_status' );

		if ( ! in_array( $named, array( null, '', array() ), true ) ) {
			return $where;
		}

		return $where . $this->wp->status_exclusion_where_clause( $this->wp->get_admin_only_statuses() );
	}
}
