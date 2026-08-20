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
 * The website never needs this: it marks a thread read on the server, during the
 * request that rendered it, from data it already has. The API cannot — a client
 * displays a thread and tells us later how far it got, so the position makes a round
 * trip through a device we do not control.
 *
 * **That is the whole reason for the signature.** A position the client could compose
 * itself is a position it could invent: a time in the far future silences a thread
 * permanently, and a position with no topic inside it could be replayed against a
 * different thread than the one it was issued for. So the topic ID travels inside the
 * signed payload rather than being taken from the request path, and every field is
 * checked on the way back in.
 *
 * The payload is not secret and is not encrypted — it says what the client's own
 * screen already showed. It is only proof that the server issued it.
 *
 * ⚠ **Rotating WordPress's auth salt invalidates every outstanding cursor.** That is
 * the correct behaviour rather than a limitation: a rotated salt means every other
 * signed thing on the site has been invalidated too. The client's recovery is to fetch
 * the topic again, which returns a fresh cursor.
 *
 * @since 0.6.0
 */
class ReadCursor {

	/**
	 * Payload version, so a future shape can be told from this one rather than
	 * guessed at. An unrecognised version is refused, not best-guessed.
	 *
	 * @var int
	 * @since 0.6.0
	 */
	private const VERSION = 1;

	/**
	 * Secret the payload is signed with.
	 *
	 * @var string
	 * @since 0.6.0
	 */
	private string $auth_salt;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param string $auth_salt Secret to sign with; WordPress's auth salt in production.
	 */
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
	 * Returns null for everything else — a bad signature, a payload edited under an
	 * old one, another topic's cursor, a version we do not know, a field of the wrong
	 * type, a time that is not a MySQL datetime, or an ID that identifies no post.
	 * The caller cannot tell those apart, and does not need to: all of them mean the
	 * same thing, which is that this did not come from us.
	 *
	 * ⚠ `strtotime()` is not validation. It accepts "yesterday", "next tuesday" and a
	 * date that does not exist, all of which would let a client describe a position in
	 * its own words. The check is that the string round-trips through the exact stored
	 * format, because that format is what the position is compared against in SQL.
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
