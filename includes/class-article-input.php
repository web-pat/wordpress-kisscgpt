<?php
/** Validate editorial input independently of the UI. @package DirectOpenAIArticleGenerator */

namespace DOAG;

defined( 'ABSPATH' ) || exit;

final class Article_Input {

	public static function defaults( Settings $settings ): array {
		$config = $settings->all();
		return array(
			'topic'        => '',
			'audience'     => __( 'Interested general readers', 'direct-openai-article-generator' ),
			'language'     => $config['language'],
			'length'       => 1000,
			'tone'         => __( 'Clear, factual and approachable', 'direct-openai-article-generator' ),
			'instructions' => '',
			'keywords'     => '',
			'outline'      => '',
			'include_faq'  => false,
			'include_meta' => true,
			'include_seo'  => true,
			'model'        => $config['model'],
			'temperature'  => '',
			'status'       => $config['status'],
		);
	}

	public static function sanitize( array $raw ): array {
		$data = array();
		foreach ( array(
			'topic'        => 300,
			'audience'     => 500,
			'language'     => 100,
			'tone'         => 300,
			'instructions' => 6000,
			'keywords'     => 2000,
			'outline'      => 6000,
			'model'        => 150,
		) as $key => $limit ) {
			$value        = isset( $raw[ $key ] ) && is_string( $raw[ $key ] ) ? $raw[ $key ] : '';
			$data[ $key ] = mb_substr( in_array( $key, array( 'instructions', 'outline' ), true ) ? sanitize_textarea_field( $value ) : sanitize_text_field( $value ), 0, $limit );
		}
		$data['length']      = isset( $raw['length'] ) && is_scalar( $raw['length'] ) ? (int) $raw['length'] : 0;
		$data['status']      = isset( $raw['status'] ) && is_string( $raw['status'] ) ? sanitize_key( $raw['status'] ) : '';
		$data['temperature'] = isset( $raw['temperature'] ) && is_string( $raw['temperature'] ) ? sanitize_text_field( $raw['temperature'] ) : '';
		foreach ( array( 'include_faq', 'include_meta', 'include_seo' ) as $key ) {
			$data[ $key ] = isset( $raw[ $key ] ) && '1' === $raw[ $key ];
		}
		return $data;
	}

	public static function validate( array $data ): bool|\WP_Error {
		if ( '' === $data['topic'] || '' === $data['language'] || ! preg_match( '/\A[A-Za-z0-9._:\-]{1,150}\z/', $data['model'] ) ) {
			return new \WP_Error( 'input', __( 'Enter a topic, language and valid model identifier.', 'direct-openai-article-generator' ) );
		}
		if ( $data['length'] < 200 || $data['length'] > 4000 ) {
			return new \WP_Error( 'length', __( 'Choose a target length between 200 and 4000 words.', 'direct-openai-article-generator' ) );
		}
		if ( ! isset( Settings::statuses()[ $data['status'] ] ) || ( 'private' === $data['status'] && ! current_user_can( 'publish_posts' ) ) ) {
			return new \WP_Error( 'status', __( 'You cannot use this article status.', 'direct-openai-article-generator' ) );
		}
		if ( '' !== $data['temperature'] && ( ! is_numeric( $data['temperature'] ) || ! is_finite( (float) $data['temperature'] ) || (float) $data['temperature'] < 0 || (float) $data['temperature'] > 2 ) ) {
			return new \WP_Error( 'temperature', __( 'Temperature must be empty or a number from 0 to 2.', 'direct-openai-article-generator' ) );
		}
		return true;
	}
}
