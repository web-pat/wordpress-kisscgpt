<?php
/** Extension point for SEO persistence. @package DirectOpenAIArticleGenerator */

namespace DOAG;

defined( 'ABSPATH' ) || exit;

interface SEO_Adapter {

	public function save( int $post_id, array $article ): void;
}
