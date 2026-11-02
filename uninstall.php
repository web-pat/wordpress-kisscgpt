<?php
/** Remove configuration only; retain all generated content. @package DirectOpenAIArticleGenerator */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$doag_cleanup_jobs = static function () {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Discover plugin-owned, non-autoloaded job options for uninstall.
	$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( 'doag_job_' ) . '%', $wpdb->esc_like( 'doag_user_job_' ) . '%' ) );
	foreach ( $names as $name ) {
		delete_option( $name );
	}
	wp_unschedule_hook( 'doag_cleanup_job' );
};

if ( is_multisite() ) {
	$doag_offset = 0;
	do {
		$doag_sites = get_sites(
			array(
				'fields' => 'ids',
				'number' => 100,
				'offset' => $doag_offset,
			)
		);
		foreach ( $doag_sites as $doag_site ) {
			switch_to_blog( (int) $doag_site );
			delete_option( 'doag_settings' );
			delete_option( 'doag_generation_lock' );
			delete_option( 'doag_generation_lock_owner' );
			delete_option( 'doag_generation_lease' );
			delete_option( 'doag_generation_worker' );
			wp_unschedule_hook( 'doag_generate_article' );
			$doag_cleanup_jobs();
			restore_current_blog();
		}
		$doag_offset += 100;
		$doag_count   = count( $doag_sites );
	} while ( 100 === $doag_count );
} else {
	delete_option( 'doag_settings' );
	delete_option( 'doag_generation_lock' );
	delete_option( 'doag_generation_lock_owner' );
	delete_option( 'doag_generation_lease' );
	delete_option( 'doag_generation_worker' );
	wp_unschedule_hook( 'doag_generate_article' );
	$doag_cleanup_jobs();
}
