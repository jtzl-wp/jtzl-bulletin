<?php
/**
 * Matched search terms, marked inside a result's text.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\View;

/**
 * Wraps the terms a reader searched for in `<mark>`, and escapes everything else.
 *
 * @since 0.3.0
 */
class TermHighlighter {

	/**
	 * Shortest term worth marking. A single character matches inside most words in
	 * any row, which is noise wearing the costume of a signal.
	 */
	private const MIN_TERM_LENGTH = 2;

	/**
	 * Escape a result's text, marking every occurrence of the search terms.
	 *
	 * Returns HTML: safe to echo, and NOT to escape again.
	 *
	 * @since 0.3.0
	 *
	 * @param string $text  Plain text (a title, or a supporting line).
	 * @param string $terms The raw search terms, as the reader typed them.
	 * @return string
	 */
	public function highlight( string $text, string $terms ): string {
		$list = $this->terms( $terms );

		if ( '' === $text || array() === $list ) {
			return esc_html( $text );
		}

		$quoted = array_map(
			static fn( string $term ): string => preg_quote( $term, '/' ),
			$list
		);

		// ONE capture group, wrapping the whole alternation — which is what puts the
		// matched runs at the odd indices below. preg_quote() escapes parentheses, so
		// no term can add a second group and shift them.
		$parts = preg_split( '/(' . implode( '|', $quoted ) . ')/iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE );

		// `/u` against text PCRE reads as invalid UTF-8 answers false, and returning
		// that would print the row's title as nothing. The unmarked text is the right
		// answer to a pattern that could not run.
		if ( ! is_array( $parts ) ) {
			return esc_html( $text );
		}

		$out = '';
		foreach ( $parts as $index => $part ) {
			$out .= 1 === $index % 2
				? '<mark>' . esc_html( $part ) . '</mark>'
				: esc_html( $part );
		}

		return $out;
	}

	/**
	 * The terms to mark, longest first.
	 *
	 * Longest first is what makes a multi-word search mark the phrase rather than
	 * its words: PCRE's alternation takes the first branch that matches at a
	 * position, so "flow control" has to be tried before "flow".
	 *
	 * @since 0.3.0
	 *
	 * @param string $terms The raw search terms.
	 * @return array<int,string>
	 */
	private function terms( string $terms ): array {
		$terms = trim( $terms );
		if ( '' === $terms ) {
			return array();
		}

		$words = preg_split( '/\s+/u', $terms, -1, PREG_SPLIT_NO_EMPTY );
		$list  = is_array( $words ) ? $words : array();

		// The whole phrase as well as its words, so a two-word search marks one run
		// where both are adjacent and two runs where they are not.
		if ( count( $list ) > 1 ) {
			array_unshift( $list, $terms );
		}

		$list = array_filter(
			array_unique( $list ),
			static fn( string $term ): bool => mb_strlen( $term ) >= self::MIN_TERM_LENGTH
		);

		usort(
			$list,
			static fn( string $a, string $b ): int => mb_strlen( $b ) <=> mb_strlen( $a )
		);

		return $list;
	}
}
