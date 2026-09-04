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
 * @since 0.3.0
 */
class ReplyOrder {

	/**
	 * Depth-first reading order for a reply tree.
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
