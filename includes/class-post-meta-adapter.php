<?php
/** Plugin-owned metadata, independent of SEO vendors. @package DirectOpenAIArticleGenerator */

namespace DOAG;

defined( 'ABSPATH' ) || exit;

final class Post_Meta_Adapter implements SEO_Adapter {

	public function save( int $post_id, array $article ): void {
		update_post_meta( $post_id, '_doag_seo_title', wp_slash( $article['seo_title'] ) );
		update_post_meta( $post_id, '_doag_meta_description', wp_slash( $article['meta_description'] ) );
		update_post_meta( $post_id, '_doag_keywords', wp_slash( $article['keywords'] ) );
	}
}
