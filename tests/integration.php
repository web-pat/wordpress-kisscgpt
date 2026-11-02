<?php
/**
 * Offline integration checks against a real, disposable WordPress installation.
 * Run: wp eval-file wp-content/plugins/direct-openai-article-generator/tests/integration.php
 * No live OpenAI calls; the HTTP pre-request filter fails closed.
 *
 * @package DirectOpenAIArticleGenerator
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

( static function () {
	$assert = static function ( bool $condition, string $message ): void {
		if ( ! $condition ) {
			throw new RuntimeException( $message );
		}
		WP_CLI::log( 'PASS: ' . $message );
	};
	$original = get_option( DOAG\Settings::OPTION );
	$user     = self_or_admin_doag_test_user();
	$post_ids = array();
	$job_ids  = array();
	$secret   = 'sk-test-' . str_repeat( 'x', 32 );
	$settings = new DOAG\Settings();
	$article  = array(
		'title' => 'A "quoted" title',
		'content_html' => '<p>A useful article with \\ paths.</p><script>alert(1)</script><p onclick="bad()">Text</p><img src="https://example.com/tracker"><!-- wp:shortcode -->[dangerous]<!-- /wp:shortcode --><a href="javascript:alert(1)">link</a>',
		'excerpt' => 'A useful summary.', 'seo_title' => 'SEO title', 'meta_description' => 'Description',
		'keywords' => array( 'one', 'two' ), 'faq' => array( array( 'question' => 'Why?', 'answer' => 'Because [example].' ) ),
	);
	$mode     = 'ok';
	$calls    = 0;
	$http     = static function ( $pre, $args, $url ) use ( &$mode, &$calls, $article, $secret, $assert ) {
		++$calls;
		$assert( 'https://api.openai.com/v1/chat/completions' === $url, 'Fixed OpenAI endpoint' );
		$assert( 0 === $args['redirection'] && true === $args['sslverify'], 'No redirects; TLS verification enabled' );
		$body = json_decode( $args['body'], true );
		$assert( ! isset( $body['temperature'] ), 'Empty temperature omitted' );
		$assert( false === $body['store'] && true === $body['response_format']['json_schema']['strict'], 'Structured output and store=false' );
		if ( 'network' === $mode || 'timeout' === $mode ) {
			return new WP_Error( 'http_request_failed', ( 'timeout' === $mode ? 'cURL error 28: timed out ' : 'Network failure ' ) . $secret );
		}
		if ( is_int( $mode ) ) {
			return array( 'response' => array( 'code' => $mode ), 'body' => $secret );
		}
		$response = array( 'choices' => array( array( 'finish_reason' => 'incomplete' === $mode ? 'length' : 'stop', 'message' => array( 'content' => 'bad_article' === $mode ? 'not json' : wp_json_encode( $article ) ) ) ) );
		return array( 'response' => array( 'code' => 200 ), 'body' => 'empty' === $mode ? '' : ( 'bad_json' === $mode ? 'not json' : wp_json_encode( $response ) ) );
	};
	add_filter( 'pre_http_request', $http, PHP_INT_MAX, 3 );
	try {
		wp_set_current_user( $user );
		update_option( DOAG\Settings::OPTION, array_merge( $settings->defaults(), array( 'api_key' => $secret ) ), false );
		$input                  = DOAG\Article_Input::defaults( $settings );
		$input['topic']          = 'Testing';
		$input['include_faq']    = true;
		$client                 = new DOAG\OpenAI_Client( $settings );
		$service                = new DOAG\Article_Service( $client, new DOAG\Post_Meta_Adapter() );
		$assert( true === DOAG\Article_Input::validate( $input ), 'Valid input accepted' );
		$unsafe                 = $input;
		$unsafe['status']       = 'publish';
		$assert( is_wp_error( $service->generate( $unsafe ) ) && 0 === $calls, 'Publication rejected before HTTP' );
		wp_set_current_user( 0 );
		$assert( is_wp_error( $service->generate( $input ) ) && 0 === $calls, 'Anonymous generation denied' );
		wp_set_current_user( $user );
		$assert( $secret === $settings->sanitize( array( 'api_key' => '' ) )['api_key'], 'Blank key preserves saved secret' );
		ob_start();
		( new DOAG\Admin\Settings_Page( $settings ) )->field( array( 'key' => 'api_key' ) );
		$html = ob_get_clean();
		$assert( ! str_contains( $html, $secret ) && str_contains( $html, 'value=""' ), 'Settings HTML never contains saved key' );
		foreach ( array( 401, 403, 429, 400, 404, 422, 500, 'network', 'timeout', 'empty', 'bad_json', 'bad_article', 'incomplete' ) as $mode ) {
			$result = $client->generate( $input );
			$assert( is_wp_error( $result ) && ! str_contains( $result->get_error_message(), $secret ), 'Safe error for ' . $mode );
		}
		$mode = 'ok';
		foreach ( array( 'draft', 'pending', 'private' ) as $status ) {
			$input['status'] = $status;
			$id              = $service->generate( $input );
			$assert( is_int( $id ) && $id > 0, 'Post inserted: ' . $status );
			$post_ids[] = $id;
			$post       = get_post( $id );
			$assert( $status === $post->post_status && 'post' === $post->post_type && $user === (int) $post->post_author, 'Correct post type, owner and status' );
			$assert( ! preg_match( '/<script|onclick|<img|javascript:|<!--|\[dangerous\]/i', $post->post_content ), 'Executable and tracking markup removed' );
			$assert( str_contains( $post->post_content, '<h2>Why?</h2>' ) && str_contains( $post->post_content, '\\ paths' ), 'FAQ and backslashes preserved' );
			$assert( 1 === preg_match( '/&#0*91;example&#0*93;/', $post->post_content ) && ! str_contains( $post->post_content, '&amp;#' ), 'FAQ brackets escaped once without enabling shortcodes' );
			$assert( array( 'one', 'two' ) === get_post_meta( $id, '_doag_keywords', true ), 'SEO keyword array saved' );
		}
		$broken          = $article;
		$broken['title'] = array();
		$assert( is_wp_error( DOAG\Article_Validator::clean( $broken, $input ) ), 'Malformed model types rejected' );
		$filter = static function ( $data ) { $data['content_html'] = '<p onclick="evil()">Filtered [dangerous]</p>'; return $data; };
		add_filter( 'doag_generated_article_data', $filter );
		$id = $service->generate( $input );
		remove_filter( 'doag_generated_article_data', $filter );
		$assert( is_int( $id ), 'Filtered article inserted' );
		$post_ids[] = $id;
		$assert( ! str_contains( get_post( $id )->post_content, 'onclick' ) && ! str_contains( get_post( $id )->post_content, '[dangerous]' ), 'Extension output sanitized again' );
		$reject = static function () { return true; };
		add_filter( 'wp_insert_post_empty_content', $reject );
		$result = $service->generate( $input );
		remove_filter( 'wp_insert_post_empty_content', $reject );
		$assert( is_wp_error( $result ) && 'save' === $result->get_error_code(), 'Post insertion failure handled safely' );

		// Lease contention, expiry and ownership-aware release.
		$lease_name = 'doag_test_lease';
		delete_option( $lease_name );
		$assert( DOAG\Generation_Lock::acquire( $lease_name, 'first', 60 ), 'First lease acquired' );
		$assert( ! DOAG\Generation_Lock::acquire( $lease_name, 'second', 60 ), 'Live lease cannot be overwritten' );
		DOAG\Generation_Lock::release( $lease_name, 'second' );
		$assert( ! DOAG\Generation_Lock::acquire( $lease_name, 'third', 60 ), 'Non-owner cannot release lease' );
		DOAG\Generation_Lock::release( $lease_name, 'first' );
		$assert( DOAG\Generation_Lock::acquire( $lease_name, 'expired', -1 ), 'Expired lease fixture created' );
		// Interleave a competing acquisition immediately before the stale reader's UPDATE.
		$interleave = static function ( $query ) use ( $lease_name, &$interleave ) {
			global $wpdb;
			if ( str_starts_with( $query, 'UPDATE ' ) && str_contains( $query, $lease_name ) ) {
				remove_filter( 'query', $interleave );
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s", wp_json_encode( array( 'owner' => 'winner', 'expires' => time() + 60 ) ), $lease_name ) );
			}
			return $query;
		};
		add_filter( 'query', $interleave );
		$assert( ! DOAG\Generation_Lock::acquire( $lease_name, 'stale-reader', 60 ), 'Stale reader cannot replace a newly acquired lease' );
		remove_filter( 'query', $interleave );
		DOAG\Generation_Lock::release( $lease_name, 'expired' );
		$assert( ! DOAG\Generation_Lock::acquire( $lease_name, 'another', 60 ), 'Expired owner cannot release successor lease' );
		DOAG\Generation_Lock::release( $lease_name, 'winner' );

		$jobs = new DOAG\Background_Generator( $service );
		$enqueue = static function () use ( $jobs, $input, $user, $assert, &$job_ids ) {
			$token = wp_generate_uuid4();
			set_transient( 'doag_form_' . $token, $user, 60 );
			$id = $jobs->enqueue( $input, $token );
			$assert( is_string( $id ), 'Background job queued' );
			$job_ids[] = $id;
			$assert( false === get_transient( 'doag_form_' . $token ), 'Queue consumes single-use form token' );
			return $id;
		};
		$before = $calls;
		$id     = $enqueue();
		$queued_job = get_option( 'doag_job_' . $id );
		$assert( $before === $calls && 'queued' === $jobs->current()['status'], 'Admin submission makes no HTTP request' );
		global $wpdb;
		$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", 'doag_job_' . $id ) );
		$assert( ! in_array( $autoload, wp_autoload_values_to_autoload(), true ), 'Durable job briefs are not autoloaded' );
		$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", 'doag_user_job_' . $user ) );
		$assert( ! in_array( $autoload, wp_autoload_values_to_autoload(), true ), 'Durable status pointers are not autoloaded' );
		$assert( false !== wp_next_scheduled( DOAG\Job_Store::CLEANUP_HOOK, array( $id ) ), 'Job retention cleanup is scheduled' );
		// Simulate transient eviction as well as loss of the options object cache.
		delete_transient( 'doag_job_' . $id );
		delete_transient( 'doag_user_job_' . $user );
		wp_cache_flush();
		$assert( 'queued' === $jobs->current()['status'], 'Queued job and status survive cache eviction' );
		$assert( is_wp_error( $jobs->enqueue( $input, wp_generate_uuid4() ) ), 'Second queued generation rejected' );
		wp_set_current_user( 0 );
		$jobs->run( $id );
		wp_cache_flush();
		$assert( 0 === get_current_user_id(), 'Cron worker restores previous user context' );
		wp_set_current_user( $user );
		$job = $jobs->current();
		$assert( 'complete' === $job['status'] && $before + 1 === $calls, 'Worker creates article in one HTTP request' );
		$post_ids[] = $job['post_id'];
		$assert( $user === (int) get_post( $job['post_id'] )->post_author, 'Background article belongs to submitting user' );
		$jobs->run( $id );
		$assert( $before + 1 === $calls, 'Duplicate cron delivery does not repeat API call' );
		$stale_read = static function () use ( $queued_job ) { return $queued_job; };
		add_filter( 'pre_option_doag_job_' . $id, $stale_read );
		try {
			$jobs->run( $id );
		} finally {
			remove_filter( 'pre_option_doag_job_' . $id, $stale_read );
		}
		$assert( $before + 1 === $calls && 'complete' === get_option( 'doag_job_' . $id )['status'], 'Stale queued reads cannot reset completion or repeat API call' );

		$id    = $enqueue();
		$throw = static function () use ( $secret ) { throw new RuntimeException( $secret ); };
		add_filter( 'doag_openai_request_body', $throw );
		try {
			$jobs->run( $id );
		} finally {
			remove_filter( 'doag_openai_request_body', $throw );
		}
		$job = $jobs->current();
		$assert( 'error' === $job['status'] && ! str_contains( $job['error'], $secret ), 'Worker catches extension exceptions without exposing secrets' );
		$assert( $user === get_current_user_id(), 'Worker restores user after exception' );
		$id = $enqueue();
		$job = get_option( 'doag_job_' . $id );
		$job['created'] = time() - 11 * MINUTE_IN_SECONDS;
		update_option( 'doag_job_' . $id, $job, false );
		$before = $calls;
		$jobs->run( $id );
		$assert( 'error' === $jobs->current()['status'] && $before === $calls, 'Expired queued job fails without HTTP' );
		$id = $enqueue();
		$job = get_option( 'doag_job_' . $id );
		$job['user_id'] = 0; // Model a submitting account that no longer has permission.
		update_option( 'doag_job_' . $id, $job, false );
		$before = $calls;
		$jobs->run( $id );
		$job = get_option( 'doag_job_' . $id );
		$assert( 'error' === $job['status'] && $before === $calls, 'Worker rechecks author permissions before HTTP' );
		$assert( $user === get_current_user_id(), 'Permission failure restores user context' );
		$token = wp_generate_uuid4();
		set_transient( 'doag_form_' . $token, $user, 60 );
		$block_schedule = static function ( $pre, $event ) { return DOAG\Background_Generator::HOOK === $event->hook ? false : $pre; };
		add_filter( 'pre_schedule_event', $block_schedule, 10, 2 );
		try {
			$result = $jobs->enqueue( $input, $token );
		} finally {
			remove_filter( 'pre_schedule_event', $block_schedule );
		}
		$assert( is_wp_error( $result ) && 'queue' === $result->get_error_code(), 'Scheduling failure is reported safely' );
		$assert( $user === (int) get_transient( 'doag_form_' . $token ), 'Scheduling failure preserves form token' );
		delete_transient( 'doag_form_' . $token );
		$id = $enqueue();
		$job = get_option( 'doag_job_' . $id );
		$job['created'] = time() - 11 * MINUTE_IN_SECONDS;
		update_option( 'doag_job_' . $id, $job, false );
		$jobs->run( $id );
		$assert( 'error' === $jobs->current()['status'], 'Scheduling failure releases queue lease for later submissions' );
		$token = wp_generate_uuid4();
		set_transient( 'doag_form_' . $token, $user, 60 );
		$user_key = 'doag_user_job_' . $user;
		$previous = get_option( $user_key );
		$storage_filter = 'pre_update_option_' . $user_key;
		$reject_storage = static function ( $value, $old ) { return $old; };
		add_filter( $storage_filter, $reject_storage, 10, 2 );
		try {
			$result = $jobs->enqueue( $input, $token );
		} finally {
			remove_filter( $storage_filter, $reject_storage );
		}
		$assert( is_wp_error( $result ) && 'queue' === $result->get_error_code(), 'Status storage failure prevents scheduling a hidden job' );
		$assert( $previous === get_option( $user_key ) && $user === (int) get_transient( 'doag_form_' . $token ), 'Storage failure preserves prior status and unused token' );
		delete_transient( 'doag_form_' . $token );

		// Failing cleanup scheduling must not leave durable briefs without cleanup.
		$token = wp_generate_uuid4();
		set_transient( 'doag_form_' . $token, $user, 60 );
		$block_cleanup = static function ( $pre, $event ) { return DOAG\Job_Store::CLEANUP_HOOK === $event->hook ? false : $pre; };
		add_filter( 'pre_schedule_event', $block_cleanup, 10, 2 );
		try {
			$result = $jobs->enqueue( $input, $token );
		} finally {
			remove_filter( 'pre_schedule_event', $block_cleanup );
		}
		$assert( is_wp_error( $result ) && $previous === get_option( $user_key ), 'Cleanup scheduling failure preserves previous status' );
		$assert( $user === (int) get_transient( 'doag_form_' . $token ), 'Cleanup scheduling failure preserves unused token' );
		delete_transient( 'doag_form_' . $token );

		$id = $enqueue();
		$before = $calls;
		$reject_claim = static function ( $query ) use ( $id ) {
			if ( str_starts_with( $query, 'UPDATE ' ) && str_contains( $query, 'doag_job_' . $id ) ) {
				global $wpdb;
				return $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = option_value WHERE option_name = %s", 'doag_missing_test' );
			}
			return $query;
		};
		add_filter( 'query', $reject_claim );
		try {
			$jobs->run( $id );
		} finally {
			remove_filter( 'query', $reject_claim );
		}
		$assert( $before === $calls && 'queued' === get_option( 'doag_job_' . $id )['status'], 'Failed durable claim prevents billable request' );
		$old_id = $id;
		$id = $enqueue();
		DOAG\Job_Store::cleanup( $id );
		$assert( is_array( get_option( 'doag_job_' . $id ) ), 'Early cleanup does not delete a retained job' );
		$job = get_option( 'doag_job_' . $old_id );
		$job['created'] = time() - 31 * MINUTE_IN_SECONDS;
		update_option( 'doag_job_' . $old_id, $job, false );
		update_option( $user_key, $old_id, false );
		$replace_pointer = static function ( $query ) use ( $user_key, $id, &$replace_pointer ) {
			if ( str_starts_with( $query, 'DELETE FROM ' ) && str_contains( $query, $user_key ) ) {
				remove_filter( 'query', $replace_pointer );
				update_option( $user_key, $id, false );
			}
			return $query;
		};
		add_filter( 'query', $replace_pointer );
		try {
			do_action( DOAG\Job_Store::CLEANUP_HOOK, $old_id );
		} finally {
			remove_filter( 'query', $replace_pointer );
		}
		$assert( false === get_option( 'doag_job_' . $old_id ) && $id === get_option( $user_key ), 'Cleanup removes expired brief without deleting newer status' );
		DOAG\Job_Store::remember_user( $user, $old_id );
		$assert( $id === get_option( $user_key ), 'Legacy status adoption cannot overwrite a newer pointer' );
		$assert( ! DOAG\Generation_Lock::acquire( DOAG\Generation_Lock::QUEUE, 'cleanup-test', 60 ), 'Old cleanup cannot release a newer queue lease' );
		$job = get_option( 'doag_job_' . $id );
		$job['created'] = time() - 31 * MINUTE_IN_SECONDS;
		update_option( 'doag_job_' . $id, $job, false );
		$jobs->run( $id );
		$assert( $before === $calls && false === get_option( 'doag_job_' . $id ) && false === get_option( $user_key ), 'Lazy expiry removes old job and pointer without HTTP' );
		$assert( ! DOAG\Job_Store::save( $id, $job ) && false === get_option( 'doag_job_' . $id ), 'Late worker cannot resurrect an expired brief' );
		$assert( false === wp_next_scheduled( DOAG\Job_Store::CLEANUP_HOOK, array( $id ) ) && false === wp_next_scheduled( DOAG\Background_Generator::HOOK, array( $id ) ), 'Expired job cleanup removes both scheduled events' );

		// Upgrade an outstanding transient-backed job before its first API call.
		$id = wp_generate_uuid4();
		$job_ids[] = $id;
		$legacy = array( 'user_id' => $user, 'input' => $input, 'status' => 'queued', 'created' => time(), 'expires' => time() + 900 );
		set_transient( 'doag_job_' . $id, $legacy, 1800 );
		set_transient( $user_key, $id, 1800 );
		$jobs->run( $id );
		wp_cache_flush();
		$job = $jobs->current();
		$assert( 'complete' === $job['status'] && $before + 1 === $calls && $id === get_option( $user_key ), 'Legacy job is adopted durably and generated once' );
		$post_ids[] = $job['post_id'];
		$jobs->run( $id );
		$assert( $before + 1 === $calls, 'Adopted job cannot be generated twice' );

		$id = wp_generate_uuid4();
		$job_ids[] = $id;
		set_transient( 'doag_job_' . $id, $legacy, 1800 );
		$competing_claim = static function ( $query ) use ( $id, $legacy, &$competing_claim ) {
			if ( str_starts_with( $query, 'INSERT IGNORE INTO ' ) && str_contains( $query, 'doag_job_' . $id ) ) {
				global $wpdb;
				remove_filter( 'query', $competing_claim );
				$claimed = $legacy;
				$claimed['status'] = 'running';
				$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", 'doag_job_' . $id, maybe_serialize( $claimed ) ) );
			}
			return $query;
		};
		add_filter( 'query', $competing_claim );
		try {
			$jobs->run( $id );
		} finally {
			remove_filter( 'query', $competing_claim );
		}
		$assert( $before + 1 === $calls && 'running' === get_option( 'doag_job_' . $id )['status'], 'Concurrent legacy adoption cannot reset a running claim or repeat HTTP' );
		define( 'DIRECT_OPENAI_ARTICLE_GENERATOR_API_KEY', 'sk-config-' . str_repeat( 'y', 32 ) );
		$assert( DIRECT_OPENAI_ARTICLE_GENERATOR_API_KEY === $settings->api_key(), 'wp-config constant takes precedence' );
		$assert( '' === $settings->sanitize( array() )['api_key'], 'Saving constant configuration clears stale database secret' );
		ob_start();
		( new DOAG\Admin\Settings_Page( $settings ) )->field( array( 'key' => 'api_key' ) );
		$html = ob_get_clean();
		$assert( ! str_contains( $html, DIRECT_OPENAI_ARTICLE_GENERATOR_API_KEY ) && ! str_contains( $html, 'type="password"' ), 'Constant key has no rendered input or value' );
		// Exercise uninstall without deleting the installed plugin or generated posts.
		add_option( 'doagXjob_test', 'unrelated', '', false );
		define( 'WP_UNINSTALL_PLUGIN', DOAG_FILE );
		require dirname( __DIR__ ) . '/uninstall.php';
		foreach ( $job_ids as $job_id ) {
			$assert( false === get_option( 'doag_job_' . $job_id ) && false === wp_next_scheduled( DOAG\Job_Store::CLEANUP_HOOK, array( $job_id ) ), 'Uninstall removes durable job and cleanup event' );
		}
		$assert( false === get_option( $user_key ) && false === get_option( DOAG\Settings::OPTION ), 'Uninstall removes durable status pointer and settings' );
		$assert( 'unrelated' === get_option( 'doagXjob_test' ) && get_post( $post_ids[0] ) && array( 'one', 'two' ) === get_post_meta( $post_ids[0], '_doag_keywords', true ), 'Uninstall preserves unrelated options, posts and metadata' );
		WP_CLI::success( 'All offline integration checks passed.' );
	} finally {
		if ( isset( $interleave ) ) {
			remove_filter( 'query', $interleave );
		}
		delete_option( 'doag_test_lease' );
		delete_option( 'doagXjob_test' );
		foreach ( $job_ids as $job_id ) {
			DOAG\Job_Store::delete( $job_id );
			DOAG\Generation_Lock::release( DOAG\Generation_Lock::QUEUE, $job_id );
		}
		delete_option( 'doag_user_job_' . $user );
		delete_transient( 'doag_user_job_' . $user );
		remove_filter( 'pre_http_request', $http, PHP_INT_MAX );
		foreach ( $post_ids as $id ) {
			wp_delete_post( $id, true );
		}
		false === $original ? delete_option( DOAG\Settings::OPTION ) : update_option( DOAG\Settings::OPTION, $original, false );
	}
} )();

function self_or_admin_doag_test_user(): int {
	$users = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	if ( ! $users || defined( 'DIRECT_OPENAI_ARTICLE_GENERATOR_API_KEY' ) ) {
		WP_CLI::error( 'Use a disposable site with an administrator and without a configured API-key constant.' );
	}
	return (int) $users[0];
}
