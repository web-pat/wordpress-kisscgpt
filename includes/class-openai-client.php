<?php
/** Small server-side OpenAI client; never expose upstream error bodies. @package DirectOpenAIArticleGenerator */

namespace DOAG;

defined( 'ABSPATH' ) || exit;

final class OpenAI_Client {

	public const API_BASE_URL = 'https://api.openai.com/v1';
	public const TIMEOUT      = 90;

	public function __construct( private Settings $settings ) {}

	public function generate( array $input ): array|\WP_Error {
		$key = $this->settings->api_key();
		if ( '' === $key || ! preg_match( '/\A[A-Za-z0-9_\-]{20,512}\z/', $key ) ) {
			return new \WP_Error( 'missing_key', __( 'Ask an administrator to configure a valid OpenAI API key.', 'direct-openai-article-generator' ) );
		}
		$body = array(
			'model'                 => apply_filters( 'doag_model', $input['model'], $input ),
			'messages'              => Prompt_Builder::messages( $input, $this->settings->all()['system_prompt'] ),
			'max_completion_tokens' => 12000,
			'store'                 => false,
			'response_format'       => array(
				'type'        => 'json_schema',
				'json_schema' => array(
					'name'   => 'article',
					'strict' => true,
					'schema' => Prompt_Builder::schema(),
				),
			),
		);
		if ( '' !== $input['temperature'] ) {
			$body['temperature'] = (float) $input['temperature'];
		}
		$body = apply_filters( 'doag_openai_request_body', $body, $input );
		if ( ! is_array( $body ) ) {
			return new \WP_Error( 'request', __( 'The API request configuration is invalid.', 'direct-openai-article-generator' ) );
		}
		$encoded = wp_json_encode( $body );
		if ( false === $encoded || strlen( $encoded ) > 150000 ) {
			return new \WP_Error( 'request', __( 'The API request is invalid or too large.', 'direct-openai-article-generator' ) );
		}
		$response = wp_remote_post(
			self::API_BASE_URL . '/chat/completions',
			array(
				'timeout'             => self::TIMEOUT,
				'redirection'         => 0,
				'sslverify'           => true,
				'limit_response_size' => 1048576,
				'headers'             => array(
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
				),
				'body'                => $encoded,
				'data_format'         => 'body',
			)
		);
		if ( is_wp_error( $response ) ) {
			// Inspect only to classify. Never pass transport messages to the UI or logs.
			$timeout = preg_match( '/timed?\s*out|timeout|cURL error 28/i', $response->get_error_message() );
			return new \WP_Error( $timeout ? 'timeout' : 'network', $timeout ? __( 'OpenAI did not respond in time. Try a shorter article. The request may still incur API costs.', 'direct-openai-article-generator' ) : __( 'Could not connect to OpenAI. Check the server network connection and try again.', 'direct-openai-article-generator' ) );
		}
		$status = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			$message = match ( $status ) {
				401 => __( 'OpenAI rejected the API key. Ask an administrator to check it.', 'direct-openai-article-generator' ),
				403 => __( 'This OpenAI project does not have permission for this request.', 'direct-openai-article-generator' ),
				429 => __( 'OpenAI rate limit or quota reached. Check billing and limits, then retry later.', 'direct-openai-article-generator' ),
				400, 404, 422 => __( 'OpenAI rejected the request. Check model access, structured-output support and temperature compatibility. Try leaving temperature empty.', 'direct-openai-article-generator' ),
				default => __( 'OpenAI returned a service error. Please try again later.', 'direct-openai-article-generator' ),
			};
			return new \WP_Error( 'api_http', $message );
		}
		$raw = wp_remote_retrieve_body( $response );
		if ( '' === trim( $raw ) ) {
			return new \WP_Error( 'empty', __( 'OpenAI returned an empty response.', 'direct-openai-article-generator' ) );
		}
		$data = json_decode( $raw, true, 32 );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) || isset( $data['error'] ) ) {
			return new \WP_Error( 'response', __( 'OpenAI returned an invalid API response.', 'direct-openai-article-generator' ) );
		}
		$choice = $data['choices'][0] ?? null;
		if ( ! is_array( $choice ) || 'stop' !== ( $choice['finish_reason'] ?? '' ) || ! empty( $choice['message']['refusal'] ) ) {
			return new \WP_Error( 'incomplete', __( 'The response was refused or incomplete. Adjust the topic or request a shorter article.', 'direct-openai-article-generator' ) );
		}
		$content = $choice['message']['content'] ?? null;
		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			return new \WP_Error( 'empty', __( 'OpenAI returned no article content.', 'direct-openai-article-generator' ) );
		}
		$article = json_decode( $content, true, 32 );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $article ) ) {
			return new \WP_Error( 'json', __( 'The generated article was not valid JSON. Please retry.', 'direct-openai-article-generator' ) );
		}
		return $article;
	}
}
