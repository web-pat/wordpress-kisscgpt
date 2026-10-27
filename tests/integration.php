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
	$secret   = 'sk-test-' . str_repeat( 'x', 32 );
	$settings = new DOAG\Settings();
	$article  = array(
		'title' => 'A "quoted" title',
		'content_html' => '<p>A useful article with \\ paths.</p><script>alert(1)</script><p onclick="bad()">Text</p><img src="https://example.com/tracker"><!-- wp:shortcode -->[dangerous]<!-- /wp:shortcode --><a href="javascript:alert(1)">link</a>',
		'excerpt' => 'A useful summary.', 'seo_title' => 'SEO title', 'meta_description' => 'Description',
		'keywords' => array( 'one', 'two' ), 'faq' => array( array( 'question' => 'Why?', 'answer' => 'Because.' ) ),
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
		define( 'DIRECT_OPENAI_ARTICLE_GENERATOR_API_KEY', 'sk-config-' . str_repeat( 'y', 32 ) );
		$assert( DIRECT_OPENAI_ARTICLE_GENERATOR_API_KEY === $settings->api_key(), 'wp-config constant takes precedence' );
		$assert( '' === $settings->sanitize( array() )['api_key'], 'Saving constant configuration clears stale database secret' );
		ob_start();
		( new DOAG\Admin\Settings_Page( $settings ) )->field( array( 'key' => 'api_key' ) );
		$html = ob_get_clean();
		$assert( ! str_contains( $html, DIRECT_OPENAI_ARTICLE_GENERATOR_API_KEY ) && ! str_contains( $html, 'type="password"' ), 'Constant key has no rendered input or value' );
		WP_CLI::success( 'All offline integration checks passed.' );
	} finally {
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
