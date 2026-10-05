<?php
/**
 * Allowlists for wp_kses(), for markup Bulletin prints as a whole.
 *
 * @package JTZL\Bulletin
 * @since 0.6.5
 */

namespace JTZL\Bulletin\Support;

/**
 * Allowed tags for markup that arrives already built, so it is escaped where printed.
 *
 * @since 0.6.5
 */
class AllowedHtml {

	/**
	 * The inline SVG glyphs in Icons.
	 *
	 * The output spells `viewBox` as `viewbox`; the HTML parser restores the SVG casing.
	 *
	 * @since 0.6.5
	 *
	 * @return array<string,array<string,bool>>
	 */
	public static function icon(): array {
		$shape = array(
			'd'  => true,
			'cx' => true,
			'cy' => true,
			'r'  => true,
		);

		return array(
			'svg'    => array(
				'viewbox'         => true,
				'width'           => true,
				'height'          => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'aria-hidden'     => true,
			),
			'path'   => $shape,
			'circle' => $shape,
		);
	}

	/**
	 * WordPress's get_the_password_form().
	 *
	 * The form passes through `the_password_form`, which themes filter, so post
	 * markup is the base rather than a list of only the tags core emits. Post markup
	 * has neither `<form>` nor `<input>`.
	 *
	 * @since 0.6.5
	 *
	 * @return array<string,array<string,bool>>
	 */
	public static function password_form(): array {
		$allowed = wp_kses_allowed_html( 'post' );

		$allowed['form']  = array(
			'action' => true,
			'method' => true,
			'class'  => true,
			'id'     => true,
		);
		$allowed['input'] = array(
			'type'             => true,
			'name'             => true,
			'id'               => true,
			'class'            => true,
			'value'            => true,
			'size'             => true,
			'spellcheck'       => true,
			'required'         => true,
			'placeholder'      => true,
			'autocomplete'     => true,
			'aria-label'       => true,
			'aria-describedby' => true,
			'aria-invalid'     => true,
		);

		return $allowed;
	}
}
