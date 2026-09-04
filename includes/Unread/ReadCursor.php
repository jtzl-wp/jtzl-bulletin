<?php
/**
 * Signed read positions.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Unread;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Turns a read position into a token a client may hold, and back again.
 *
 * @since 0.6.0
 */
class ReadCursor {

	private const VERSION = 1;

	private string $auth_salt;

	public function __construct( string $auth_salt ) {
		$this->auth_salt = $auth_salt;
	}

	/**
	 * A token naming how far a reader has got in one topic.
	 *
	 * Nothing is validated here. The server issues from its own data, and a position
	 * it invented is not the threat this class exists for — parse() is where a value
	 * arrives from outside.
	 *
	 * @since 0.6.0
	 *
	 * @param int    $topic_id  Topic the position belongs to.
	 * @param string $read_time MySQL datetime of the topic's last activity.
	 * @param int    $read_id   ID of the post that activity was.
	 * @return string Opaque token.
	 */
	public function issue( int $topic_id, string $read_time, int $read_id ): string {
		$payload = wp_json_encode(
			array(
				'v'     => self::VERSION,
				'topic' => $topic_id,
				'time'  => $read_time,
				'id'    => $read_id,
			)
		);

		$encoded = $this->encode( (string) $payload );

		return $encoded . '.' . hash_hmac( 'sha256', $encoded, $this->auth_salt );
	}

	/**
	 * The position inside a token, when the token is one of ours and names this topic.
	 *
	 * @since 0.6.0
	 *
	 * @param string $cursor            Token from the client.
	 * @param int    $expected_topic_id Topic the caller is acting on.
	 * @return array{read_time:string,read_id:int}|null
	 */
	public function parse( string $cursor, int $expected_topic_id ): ?array {
		$parts = explode( '.', $cursor, 2 );

		// hash_equals() rather than ===: the comparison is against a value an attacker
		// supplies and can vary a byte at a time.
		if (
			2 !== count( $parts )
			|| '' === $parts[0]
			|| ! hash_equals( hash_hmac( 'sha256', $parts[0], $this->auth_salt ), $parts[1] )
		) {
			return null;
		}

		$payload = $this->payload( $this->decode( $parts[0] ) );

		if (
			null === $payload
			|| self::VERSION !== $payload['v']
			|| $expected_topic_id !== $payload['topic']
			|| $payload['id'] <= 0
			|| ! $this->is_mysql_datetime( $payload['time'] )
		) {
			return null;
		}

		return array(
			'read_time' => $payload['time'],
			'read_id'   => $payload['id'],
		);
	}

	/**
	 * A decoded payload with every field present and of the right type, or null.
	 *
	 * Shape first, meaning second: this only establishes that the four fields exist
	 * and are what they claim to be, so parse() can compare them without guarding
	 * every comparison. The types are checked rather than cast because a cast would
	 * quietly turn `"41"` into topic 41 — and a client that sends a string where the
	 * server sends an integer did not get this token from us.
	 *
	 * @since 0.6.0
	 *
	 * @param mixed $decoded Whatever came out of the payload.
	 * @return array{v:int,topic:int,time:string,id:int}|null
	 */
	private function payload( $decoded ): ?array {
		if (
			! is_array( $decoded )
			|| ! isset( $decoded['v'], $decoded['topic'], $decoded['time'], $decoded['id'] )
			|| ! is_int( $decoded['v'] )
			|| ! is_int( $decoded['topic'] )
			|| ! is_string( $decoded['time'] )
			|| ! is_int( $decoded['id'] )
		) {
			return null;
		}

		return array(
			'v'     => $decoded['v'],
			'topic' => $decoded['topic'],
			'time'  => $decoded['time'],
			'id'    => $decoded['id'],
		);
	}

	/**
	 * Base64url, which survives a URL and a JSON body without escaping.
	 *
	 * @since 0.6.0
	 *
	 * @param string $payload Raw payload.
	 * @return string
	 */
	private function encode( string $payload ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Transport encoding for a signed token, not obfuscation.
		return rtrim( strtr( base64_encode( $payload ), '+/', '-_' ), '=' );
	}

	/**
	 * The payload inside a base64url segment, or null when it is not decodable JSON.
	 *
	 * The padding rtrim() dropped has to be put back: strict decoding is what refuses
	 * a payload with stray characters in it, and strict decoding also refuses an
	 * unpadded string.
	 *
	 * @since 0.6.0
	 *
	 * @param string $encoded Base64url segment.
	 * @return mixed Decoded JSON value, or null.
	 */
	private function decode( string $encoded ) {
		$padding = ( 4 - strlen( $encoded ) % 4 ) % 4;
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Transport encoding for a signed token, not obfuscation.
		$json = base64_decode( strtr( $encoded, '-_', '+/' ) . str_repeat( '=', $padding ), true );

		return false === $json ? null : json_decode( $json, true );
	}

	/**
	 * Whether a string is exactly a MySQL datetime.
	 *
	 * The `!` resets every field the format does not name, so a partial match cannot
	 * be completed from the current time; the round-trip comparison then rejects
	 * anything PHP normalised on the way in, such as the 30th of February.
	 *
	 * @since 0.6.0
	 *
	 * @param string $value Candidate.
	 * @return bool
	 */
	private function is_mysql_datetime( string $value ): bool {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value );

		return false !== $date && $date->format( 'Y-m-d H:i:s' ) === $value;
	}
}
