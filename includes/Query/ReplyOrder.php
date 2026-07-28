<?php
/**
 * Reading order for a threaded topic's replies.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Query;

/**
 * Flattens a topic's reply tree into the order it reads in.
 *
 * A reply belongs under the one it answers (Yoren, 2026-07-28). That is an
 * ordering decision, not a nesting one: the sequence carries the structure, and
 * View\ReplyView names the parent for the cases a sequence cannot show.
 *
 * bbPress cannot supply this order and page it. Its own hierarchical mode loads
 * every reply of the topic at once (posts_per_page = -1) and counts pages by ROOT
 * replies, which is the whole of issue #12. So the order is computed here from the
 * cheapest thing that can answer it — each reply's ID and its `_bbp_reply_to` — and
 * the page is then an ordinary slice of a total order. Query\ReplyQuery holds the
 * slice; this class holds the order.
 *
 * Pure and array-in, array-out on purpose: every case worth worrying about is a
 * shape of tree, so they are unit tests rather than fixtures.
 *
 * @since 0.3.0
 */
class ReplyOrder {

	/**
	 * Depth-first reading order for a reply tree.
	 *
	 * Input is `reply_id => parent_id`, already in (date, ID) order — so each
	 * sibling list inherits that order for free, and the (date, ID) tiebreak that
	 * keeps paging stable (CLAUDE.md trap #4) survives the flatten. Parent 0 means
	 * the reply answers the thread itself.
	 *
	 * Three shapes bbPress permits that a naive walk gets wrong:
	 *
	 *  - **Cycles.** bbp_validate_reply_to() checks that a parent is a reply and is
	 *    not the reply itself — never that it is not a descendant. Two moderator
	 *    edits are enough to make A answer B and B answer A. A depth-first walk over
	 *    that never returns, which takes the page down rather than looking wrong, so
	 *    the visited set is load-bearing rather than defensive. Whatever is left
	 *    unvisited once the roots are walked is in a cycle, and its earliest member
	 *    is promoted to a root so the thread still reads — which puts the cycle after
	 *    every well-formed reply rather than in the middle of them. That is the
	 *    honest placement: a cycle has no root, so it has no position in the tree,
	 *    and any other slot would be a guess dressed up as an answer. Replies outside
	 *    it keep their proper places, which is what matters.
	 *  - **Orphans.** A parent that is trashed, spammed, or in another thread is not
	 *    in this reader's set at all — the map is built from what they may read. Its
	 *    children must still appear, as roots, or replies would vanish from a thread
	 *    for everyone but a moderator.
	 *  - **Forward references.** Nothing makes a parent older than its child, so a
	 *    parent can sort after it. Walking from roots rather than down the input
	 *    order handles that without a special case.
	 *
	 * Every input ID comes back exactly once, whatever the shape.
	 *
	 * @since 0.3.0
	 *
	 * @param array<int,int> $parents Reply ID => parent reply ID, in (date, ID) order.
	 * @return int[] Reply IDs in reading order.
	 */
	public function flatten( array $parents ): array {
		$children = $this->children_of( $parents );
		$order    = array();
		$visited  = array();

		foreach ( $parents as $id => $parent ) {
			if ( 0 === $parent || ! isset( $parents[ $parent ] ) ) {
				$this->walk( (int) $id, $children, $visited, $order );
			}
		}

		// Anything still unvisited is inside a cycle, so it has no root to be
		// reached from. Its earliest member becomes one; $visited stops the walk
		// coming back around.
		foreach ( array_keys( $parents ) as $id ) {
			$this->walk( (int) $id, $children, $visited, $order );
		}

		return $order;
	}

	/**
	 * Invert the parent map into a child list, in the order the input arrived.
	 *
	 * @since 0.3.0
	 *
	 * @param array<int,int> $parents Reply ID => parent reply ID.
	 * @return array<int,int[]> Parent reply ID => child reply IDs.
	 */
	private function children_of( array $parents ): array {
		$children = array();
		foreach ( $parents as $id => $parent ) {
			// A parent outside the set is no parent here: the reply is a root.
			if ( $parent > 0 && isset( $parents[ $parent ] ) ) {
				$children[ $parent ][] = (int) $id;
			}
		}

		return $children;
	}

	/**
	 * Append a reply and everything under it, depth-first.
	 *
	 * An explicit stack rather than recursion. Depth here is not bounded by
	 * `_bbp_thread_replies_depth` — that setting governs bbPress's reply form, not
	 * what `_bbp_reply_to` may hold — so an import can produce a chain thousands
	 * deep, and a recursive walk would exhaust the call stack on data that this
	 * loop merely takes a moment over. Children are pushed in reverse so they pop
	 * in their (date, ID) order.
	 *
	 * @since 0.3.0
	 *
	 * @param int              $start    Reply to start from.
	 * @param array<int,int[]> $children Parent reply ID => child reply IDs.
	 * @param array<int,bool>  $visited  Reply IDs already placed, by reference.
	 * @param int[]            $order    Reading order so far, by reference.
	 */
	private function walk( int $start, array $children, array &$visited, array &$order ): void {
		$stack = array( $start );

		while ( array() !== $stack ) {
			$id = (int) array_pop( $stack );
			if ( isset( $visited[ $id ] ) ) {
				continue;
			}

			$visited[ $id ] = true;
			$order[]        = $id;

			$kids = $children[ $id ] ?? array();
			for ( $i = count( $kids ) - 1; $i >= 0; $i-- ) {
				$stack[] = $kids[ $i ];
			}
		}
	}
}
