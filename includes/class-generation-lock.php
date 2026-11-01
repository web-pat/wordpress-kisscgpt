<?php
/** Atomic, expiring leases independent of the WordPress options cache. @package DirectOpenAIArticleGenerator */

namespace DOAG;

defined( 'ABSPATH' ) || exit;

final class Generation_Lock {

	public const QUEUE  = 'doag_generation_lease';
	public const WORKER = 'doag_generation_worker';

	public static function acquire( string $name, string $owner, int $seconds ): bool {
		global $wpdb;
		$value = wp_json_encode(
			array(
				'owner'   => $owner,
				'expires' => time() + $seconds,
			)
		);
		// add_option() is not a compare-and-swap primitive. Use the unique option_name index.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic lease operations must bypass option caches.
		$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, $value ) );
		if ( 1 === $inserted ) {
			self::invalidate( $name );
			return true;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$previous = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
		$lease    = is_string( $previous ) ? json_decode( $previous, true ) : null;
		if ( ! is_array( $lease ) || ! isset( $lease['expires'] ) || $lease['expires'] > time() ) {
			return false;
		}
		// A stale reader cannot replace a lease renewed or acquired by another request.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $value, $name, $previous ) );
		if ( 1 === $updated ) {
			self::invalidate( $name );
			return true;
		}
		return false;
	}

	public static function release( string $name, string $owner ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
		$lease = is_string( $value ) ? json_decode( $value, true ) : null;
		if ( ! is_array( $lease ) || ( $lease['owner'] ?? '' ) !== $owner ) {
			return;
		}
		// Compare the entire lease so an expired owner cannot delete its successor.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $name, $value ) );
		if ( 1 === $deleted ) {
			self::invalidate( $name );
		}
	}

	private static function invalidate( string $name ): void {
		wp_cache_delete( $name, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}
}
