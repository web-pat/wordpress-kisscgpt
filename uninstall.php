<?php
/** Remove configuration only; retain all generated content. @package DirectOpenAIArticleGenerator */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

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
			restore_current_blog();
		}
		$doag_offset += 100;
		$doag_count   = count( $doag_sites );
	} while ( 100 === $doag_count );
} else {
	delete_option( 'doag_settings' );
	delete_option( 'doag_generation_lock' );
	delete_option( 'doag_generation_lock_owner' );
}
