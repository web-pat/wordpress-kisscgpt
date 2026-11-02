<?php
/** Durable, non-autoloaded job records with bounded retention. @package DirectOpenAIArticleGenerator */

namespace DOAG;

defined( 'ABSPATH' ) || exit;

final class Job_Store {

	public const CLEANUP_HOOK = 'doag_cleanup_job';

	public static function create( string $id, array $job ): bool {
		// Arrange cleanup before publishing the record to a concurrent worker.
		$scheduled = wp_schedule_single_event( $job['created'] + 30 * MINUTE_IN_SECONDS, self::CLEANUP_HOOK, array( $id ), true );
		if ( ( is_wp_error( $scheduled ) || ! $scheduled ) && ! wp_next_scheduled( self::CLEANUP_HOOK, array( $id ) ) ) {
			return false;
		}
		return self::insert( 'doag_job_' . $id, $job );
	}

	private static function insert( string $name, $value ): bool {
		global $wpdb;
		// add_option() can overwrite a concurrent insert; adoption must never reset a running job.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Insert-only semantics require the unique option_name index.
		$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, maybe_serialize( $value ) ) );
		wp_cache_delete( $name, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		return 1 === $inserted;
	}

	public static function remember_user( int $user_id, string $id ): void {
		$user_key = 'doag_user_job_' . $user_id;
		// Legacy adoption must not replace a newer job's pointer.
		if ( self::insert( $user_key, $id ) || get_option( $user_key ) === $id ) {
			delete_transient( $user_key );
		}
	}

	public static function get( string $id ): ?array {
		$job = get_option( 'doag_job_' . $id );
		if ( ! is_array( $job ) ) {
			// Adopt outstanding jobs from versions that used transient-only storage.
			$job = get_transient( 'doag_job_' . $id );
			if ( ! is_array( $job ) ) {
				return null;
			}
			if ( $job['created'] + 30 * MINUTE_IN_SECONDS <= time() ) {
				delete_transient( 'doag_job_' . $id );
				return null;
			}
			if ( ! self::create( $id, $job ) ) {
				// Another request may have adopted and even claimed the same legacy job.
				$job = get_option( 'doag_job_' . $id );
				if ( ! is_array( $job ) ) {
					return null; // Never call the API without a durable record.
				}
			}
			$user_key = 'doag_user_job_' . $job['user_id'];
			if ( get_transient( $user_key ) === $id && ! get_option( $user_key ) ) {
				self::remember_user( $job['user_id'], $id );
			}
			delete_transient( 'doag_job_' . $id );
		}
		if ( $job['created'] + 30 * MINUTE_IN_SECONDS <= time() ) {
			self::cleanup( $id );
			return null;
		}
		return $job;
	}

	public static function save( string $id, array $job ): bool {
		// A late worker must not resurrect a record after its retention cleanup.
		if ( $job['created'] + 30 * MINUTE_IN_SECONDS <= time() || ! is_array( get_option( 'doag_job_' . $id ) ) ) {
			return false;
		}
		return update_option( 'doag_job_' . $id, $job, false );
	}

	public static function claim( string $id, array $job ): bool {
		if ( 'queued' !== ( $job['status'] ?? '' ) ) {
			return false;
		}
		global $wpdb;
		$running           = $job;
		$running['status'] = 'running';
		$name              = 'doag_job_' . $id;
		// A stale object-cache reader must not reset an already claimed or completed record.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Compare-and-swap the complete queued record before billing.
		$claimed = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", maybe_serialize( $running ), $name, maybe_serialize( $job ) ) );
		wp_cache_delete( $name, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		return 1 === $claimed;
	}

	public static function delete( string $id ): void {
		global $wpdb;
		$job = get_option( 'doag_job_' . $id );
		delete_option( 'doag_job_' . $id );
		if ( is_array( $job ) ) {
			$user_key = 'doag_user_job_' . $job['user_id'];
			// Compare-and-delete: a concurrent enqueue must not lose its newer pointer.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Conditional deletion must be atomic and bypass option caches.
			$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $user_key, $id ) );
			if ( 1 === $deleted ) {
				wp_cache_delete( $user_key, 'options' );
				wp_cache_delete( 'notoptions', 'options' );
			}
			if ( get_transient( $user_key ) === $id ) {
				delete_transient( $user_key );
			}
		}
		delete_transient( 'doag_job_' . $id );
		wp_clear_scheduled_hook( self::CLEANUP_HOOK, array( $id ) );
		wp_clear_scheduled_hook( Background_Generator::HOOK, array( $id ) );
	}

	public static function cleanup( string $id ): void {
		$job = get_option( 'doag_job_' . $id );
		if ( is_array( $job ) && $job['created'] + 30 * MINUTE_IN_SECONDS <= time() ) {
			self::delete( $id );
			Generation_Lock::release( Generation_Lock::QUEUE, $id );
		}
	}
}
