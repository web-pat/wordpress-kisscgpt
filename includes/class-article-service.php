<?php
/** Generation and post persistence orchestration. @package DirectOpenAIArticleGenerator */

namespace DOAG;

defined( 'ABSPATH' ) || exit;

final class Article_Service {

	public function __construct( private OpenAI_Client $client, private SEO_Adapter $seo ) {}

	public static function can_generate(): bool {
		return current_user_can( 'manage_options' ) && current_user_can( 'edit_posts' ) && current_user_can( get_post_type_object( 'post' )->cap->create_posts );
	}

	public function generate( array $input ): int|\WP_Error {
		if ( ! self::can_generate() ) {
			return new \WP_Error( 'permission', __( 'You cannot generate articles.', 'direct-openai-article-generator' ) );
		}
		$valid = Article_Input::validate( $input );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$article = $this->client->generate( $input );
		if ( is_wp_error( $article ) ) {
			return $article;
		}
		$article = Article_Validator::clean( $article, $input );
		if ( is_wp_error( $article ) ) {
			return $article;
		}
		$article = Article_Validator::clean( apply_filters( 'doag_generated_article_data', $article, $input ), $input );
		if ( is_wp_error( $article ) ) {
			return $article;
		}
		$content = $article['content_html'];
		// FAQ questions and answers follow the requested article language.
		foreach ( $article['faq'] as $faq ) {
			$content .= "\n<h2>" . esc_html( $faq['question'] ) . '</h2><p>' . esc_html( $faq['answer'] ) . '</p>';
		}
		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => 'post',
					'post_author'  => get_current_user_id(),
					'post_title'   => $article['title'],
					'post_content' => wp_kses_post( $content ),
					'post_excerpt' => $article['excerpt'],
					'post_status'  => $input['status'],
				)
			),
			true
		);
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return new \WP_Error( 'save', __( 'The article could not be saved in WordPress. Check database availability before retrying. The API request may have incurred costs.', 'direct-openai-article-generator' ) );
		}
		$this->seo->save( $post_id, $article );
		do_action( 'doag_article_created', $post_id, $article, $input );
		return $post_id;
	}
}
