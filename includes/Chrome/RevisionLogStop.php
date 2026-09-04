<?php
/**
 * The doubled full stop at the end of an edit record.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\Chrome;

/**
 * Removes the second full stop from "…was modified 3 days ago by Mara K..".
 *
 * @since 0.5.0
 */
class RevisionLogStop {

	private const DOUBLED = '~\.((?:</[a-z]+>)*)</a>\.~i';

	/**
	 * Drop the second stop. Hooked on both revision-log getters.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $log Rendered revision log, or false when there is none.
	 * @return mixed
	 */
	public function filter_revision_log( $log ) {
		if ( ! is_string( $log ) ) {
			return $log;
		}

		$tidied = preg_replace( self::DOUBLED, '.$1</a>', $log );

		return is_string( $tidied ) ? $tidied : $log;
	}
}
