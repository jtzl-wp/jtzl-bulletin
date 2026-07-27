<?php
/**
 * Where the built stylesheet and script actually are.
 *
 * @package JTZL\Bulletin
 * @since 0.3.0
 */

namespace JTZL\Bulletin\Asset;

/**
 * Resolves the URL of a built asset from what the build wrote to disk: the hashed
 * filenames in `build/asset-manifest.json` for the script, and the hashed-or-plain
 * stylesheet beside it. An unbuilt plugin answers '' rather than a 404.
 *
 * Split out of AssetManager, which decides *whether* an asset loads on a given
 * screen and which stylesheets have to be dequeued to keep our chrome ours. Where a
 * file is is a separate question from who gets it, it is the only part of the class
 * that touched the filesystem, and it is what made the class outgrow its size guard.
 *
 * @since 0.3.0
 */
class BuiltAssets {

	/**
	 * Absolute plugin directory path (trailing slash).
	 *
	 * @var string
	 */
	private string $plugin_dir;

	/**
	 * Plugin base URL (trailing slash).
	 *
	 * @var string
	 */
	private string $plugin_url;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param string $plugin_dir Absolute plugin directory (trailing slash).
	 * @param string $plugin_url Plugin base URL (trailing slash).
	 */
	public function __construct( string $plugin_dir, string $plugin_url ) {
		$this->plugin_dir = $plugin_dir;
		$this->plugin_url = $plugin_url;
	}

	/**
	 * Resolved URL of the hashed reading-view script, or '' if unbuilt.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function script_url(): string {
		$manifest = $this->read_manifest();
		$file     = $manifest['jtzl-bltn-reading.js'] ?? '';
		return '' !== $file ? $this->plugin_url . 'build/' . $file : '';
	}

	/**
	 * Resolved URL of the built stylesheet (hashed if present), or '' if unbuilt.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function style_url(): string {
		$hashed = glob( $this->plugin_dir . 'build/jtzl-bltn.*.css' );
		if ( is_array( $hashed ) && array() !== $hashed ) {
			return $this->plugin_url . 'build/' . basename( $hashed[0] );
		}
		$plain = $this->plugin_dir . 'build/jtzl-bltn.css';
		return file_exists( $plain ) ? $this->plugin_url . 'build/jtzl-bltn.css' : '';
	}

	/**
	 * Read build/asset-manifest.json as a name => hashed-filename map.
	 *
	 * @since 0.3.0
	 *
	 * @return array<string,string>
	 */
	private function read_manifest(): array {
		$path = $this->plugin_dir . 'build/asset-manifest.json';
		if ( ! file_exists( $path ) ) {
			return array();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local build artifact, not a remote request.
		$json = file_get_contents( $path );

		// A read failure ((string) false === '') or malformed JSON both decode to a
		// non-array, so one guard covers both.
		$data = json_decode( (string) $json, true );
		if ( ! is_array( $data ) ) {
			return array();
		}

		$map = array();
		foreach ( $data as $key => $value ) {
			if ( is_string( $key ) && is_string( $value ) ) {
				$map[ $key ] = $value;
			}
		}
		return $map;
	}
}
