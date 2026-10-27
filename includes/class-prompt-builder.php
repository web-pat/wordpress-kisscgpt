<?php
/** Structured editorial prompts. @package DirectOpenAIArticleGenerator */

namespace DOAG;

defined( 'ABSPATH' ) || exit;

final class Prompt_Builder {

	public static function messages( array $input, string $system ): array {
		$requirements = 'Return only the JSON object required by the schema, without Markdown fences. '
			. 'content_html must be an HTML fragment suitable for WordPress: p, h2, h3, ul, ol, li, strong, em, blockquote and a only. '
			. 'Never include html, head, body, scripts, styles, embeds, images, forms, event handlers or shortcodes. '
			. 'Use plain text for every field except content_html. Include a useful excerpt. '
			. 'Do not repeat the title as an h1. Return FAQs separately; do not duplicate them in content_html. '
			. 'If an optional field is disabled, return an empty string or empty array for that field. '
			. 'The word count is an approximate target, not permission to truncate the response.';
		$editorial    = array_intersect_key( $input, array_flip( array( 'topic', 'audience', 'language', 'length', 'tone', 'instructions', 'keywords', 'outline', 'include_faq', 'include_meta', 'include_seo' ) ) );
		$user         = "Write an article using the following editorial brief (JSON):\n" . wp_json_encode( $editorial, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return array(
			array(
				'role'    => 'system',
				'content' => (string) apply_filters( 'doag_system_prompt', $system . "\n\n" . $requirements, $input ),
			),
			array(
				'role'    => 'user',
				'content' => (string) apply_filters( 'doag_user_prompt', $user, $input ),
			),
		);
	}

	public static function schema(): array {
		$string                 = array( 'type' => 'string' );
		$properties             = array_fill_keys( array( 'title', 'content_html', 'excerpt', 'seo_title', 'meta_description' ), $string );
		$properties['keywords'] = array(
			'type'  => 'array',
			'items' => $string,
		);
		$properties['faq']      = array(
			'type'  => 'array',
			'items' => array(
				'type'                 => 'object',
				'properties'           => array(
					'question' => $string,
					'answer'   => $string,
				),
				'required'             => array( 'question', 'answer' ),
				'additionalProperties' => false,
			),
		);
		return array(
			'type'                 => 'object',
			'properties'           => $properties,
			'required'             => array_keys( $properties ),
			'additionalProperties' => false,
		);
	}
}
