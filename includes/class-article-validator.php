<?php
/** Treat model output and extension output as untrusted content. @package DirectOpenAIArticleGenerator */

namespace DOAG;

defined( 'ABSPATH' ) || exit;

final class Article_Validator {

	public static function clean( $article, array $input ): array|\WP_Error {
		$error = new \WP_Error( 'article', __( 'The generated article has an invalid structure or empty content. Please retry.', 'direct-openai-article-generator' ) );
		if ( ! is_array( $article ) ) {
			return $error;
		}
		$clean = array();
		foreach ( array(
			'title'            => 500,
			'content_html'     => 200000,
			'excerpt'          => 4000,
			'seo_title'        => 500,
			'meta_description' => 2000,
		) as $field => $limit ) {
			if ( ! isset( $article[ $field ] ) || ! is_string( $article[ $field ] ) || strlen( $article[ $field ] ) > $limit ) {
				return $error;
			}
			$clean[ $field ] = 'content_html' === $field ? self::html( $article[ $field ] ) : self::text( $article[ $field ] );
		}
		if ( '' === trim( $clean['title'] ) || '' === trim( wp_strip_all_tags( $clean['content_html'] ) ) ) {
			return $error;
		}
		if ( ! isset( $article['keywords'], $article['faq'] ) || ! is_array( $article['keywords'] ) || ! array_is_list( $article['keywords'] ) || ! is_array( $article['faq'] ) || ! array_is_list( $article['faq'] ) || count( $article['keywords'] ) > 50 || count( $article['faq'] ) > 20 ) {
			return $error;
		}
		$clean['keywords'] = array();
		foreach ( $article['keywords'] as $keyword ) {
			if ( ! is_string( $keyword ) || strlen( $keyword ) > 300 ) {
				return $error;
			}
			$keyword = self::text( $keyword );
			if ( '' !== $keyword ) {
				$clean['keywords'][] = $keyword;
			}
		}
		$clean['faq'] = array();
		foreach ( $article['faq'] as $faq ) {
			if ( ! is_array( $faq ) || ! isset( $faq['question'], $faq['answer'] ) || ! is_string( $faq['question'] ) || ! is_string( $faq['answer'] ) || strlen( $faq['question'] ) > 1000 || strlen( $faq['answer'] ) > 6000 ) {
				return $error;
			}
			if ( $input['include_faq'] ) {
				$clean['faq'][] = array(
					'question' => self::text( $faq['question'] ),
					'answer'   => self::text( $faq['answer'] ),
				);
			}
		}
		if ( ! $input['include_seo'] ) {
			$clean['seo_title'] = '';
		}
		if ( ! $input['include_meta'] ) {
			$clean['meta_description'] = '';
		}
		return $clean;
	}

	private static function text( string $text ): string {
		return str_replace( array( '[', ']' ), array( '&#91;', '&#93;' ), sanitize_text_field( $text ) );
	}

	private static function html( string $html ): string {
		$allowed      = array_fill_keys( array( 'p', 'h2', 'h3', 'ul', 'ol', 'li', 'strong', 'em', 'blockquote', 'br' ), array() );
		$allowed['a'] = array(
			'href'  => true,
			'title' => true,
		);
		// Strip block comments and shortcode syntax to prevent dynamic rendering.
		$html = preg_replace( '/<!--[\s\S]*?(?:-->|$)/', '', $html );
		$html = force_balance_tags( wp_kses( wp_kses_post( $html ), $allowed, array( 'http', 'https', 'mailto' ) ) );
		return str_replace( array( '[', ']' ), array( '&#91;', '&#93;' ), $html );
	}
}
