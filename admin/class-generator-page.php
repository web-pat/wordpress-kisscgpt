<?php
/** Native admin form with single-use submissions. @package DirectOpenAIArticleGenerator */

namespace DOAG\Admin;

use DOAG\Article_Input;
use DOAG\Article_Service;
use DOAG\Background_Generator;
use DOAG\Settings;

defined( 'ABSPATH' ) || exit;

final class Generator_Page {

	private array $input      = array();
	private ?\WP_Error $error = null;
	private ?array $job       = null;

	public function __construct( private Settings $settings, private Background_Generator $jobs ) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	public function menu(): void {
		$hook = add_menu_page( __( 'Article Generator', 'direct-openai-article-generator' ), __( 'Article Generator', 'direct-openai-article-generator' ), 'manage_options', 'doag-generator', array( $this, 'render' ), 'dashicons-edit-page', 80 );
		add_action( 'load-' . $hook, array( $this, 'load' ) );
	}

	public function load(): void {
		if ( ! Article_Service::can_generate() ) {
			wp_die( esc_html__( 'You cannot generate articles.', 'direct-openai-article-generator' ), '', array( 'response' => 403 ) );
		}
		wp_enqueue_script( 'doag-admin', plugins_url( 'assets/admin.js', DOAG_FILE ), array(), DOAG_VERSION, true );
		$this->input = Article_Input::defaults( $this->settings );
		$this->job   = $this->jobs->current();
		if ( $this->job && 'error' === $this->job['status'] ) {
			$this->input = $this->job['input'];
			$this->error = new \WP_Error( 'generation', $this->job['error'] );
		}
		if ( 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			return;
		}
		check_admin_referer( 'doag_generate', 'doag_nonce' );
		$this->input = Article_Input::sanitize( wp_unslash( $_POST ) );
		$valid       = Article_Input::validate( $this->input );
		if ( is_wp_error( $valid ) ) {
			$this->error = $valid;
			return;
		}
		$token = isset( $_POST['doag_token'] ) && is_string( $_POST['doag_token'] ) ? sanitize_text_field( wp_unslash( $_POST['doag_token'] ) ) : '';
		if ( ! preg_match( '/\A[a-f0-9-]{36}\z/', $token ) ) {
			$this->error = new \WP_Error( 'token', __( 'This form has expired. Please submit the refreshed form.', 'direct-openai-article-generator' ) );
			return;
		}
		$result = $this->jobs->enqueue( $this->input, $token );
		if ( is_wp_error( $result ) ) {
			$this->error = $result;
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=doag-generator' ) );
		exit;
	}

	public function render(): void {
		if ( ! Article_Service::can_generate() ) {
			return;
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Article Generator', 'direct-openai-article-generator' ) . '</h1>';
		if ( $this->error ) {
			echo '<div class="notice notice-error" role="alert"><p>' . esc_html( $this->error->get_error_message() ) . '</p></div>';
		}
		if ( $this->job && in_array( $this->job['status'], array( 'queued', 'running' ), true ) ) {
			echo '<div class="notice notice-info"><p id="doag-job-pending" role="status">' . esc_html__( 'Your article is queued or generating in the background. You may leave this page. This page refreshes automatically; WordPress cron must be running.', 'direct-openai-article-generator' ) . '</p><p><a href="' . esc_url( admin_url( 'admin.php?page=doag-generator' ) ) . '">' . esc_html__( 'Refresh status', 'direct-openai-article-generator' ) . '</a></p></div></div>';
			return;
		}
		$post_id = $this->job && 'complete' === $this->job['status'] ? (int) $this->job['post_id'] : 0;
		if ( $post_id && current_user_can( 'edit_post', $post_id ) ) {
			$label = 'draft' === get_post_status( $post_id ) ? __( 'Edit draft', 'direct-openai-article-generator' ) : __( 'Edit article', 'direct-openai-article-generator' );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Article created. Review facts, links and wording before publication.', 'direct-openai-article-generator' ) . ' <a href="' . esc_url( get_edit_post_link( $post_id, 'raw' ) ) . '">' . esc_html( $label ) . '</a></p></div>';
		}
		echo '<p>' . esc_html__( 'Your brief and configured system prompt will be sent to OpenAI. Each generation can incur API costs. Only administrators with post creation permissions can use this form.', 'direct-openai-article-generator' ) . '</p>';
		echo '<form id="doag-generator" method="post" action="' . esc_url( admin_url( 'admin.php?page=doag-generator' ) ) . '">';
		wp_nonce_field( 'doag_generate', 'doag_nonce' );
		$token = wp_generate_uuid4();
		set_transient( 'doag_form_' . $token, get_current_user_id(), 30 * MINUTE_IN_SECONDS );
		echo '<input type="hidden" name="doag_token" value="' . esc_attr( $token ) . '"><table class="form-table" role="presentation">';
		$fields = array(
			'topic'        => array( __( 'Topic / working title', 'direct-openai-article-generator' ), 300 ),
			'audience'     => array( __( 'Target audience', 'direct-openai-article-generator' ), 500 ),
			'language'     => array( __( 'Language', 'direct-openai-article-generator' ), 100 ),
			'length'       => array( __( 'Target word count', 'direct-openai-article-generator' ), 4 ),
			'tone'         => array( __( 'Tone', 'direct-openai-article-generator' ), 300 ),
			'instructions' => array( __( 'Additional instructions', 'direct-openai-article-generator' ), 6000 ),
			'keywords'     => array( __( 'Keywords', 'direct-openai-article-generator' ), 2000 ),
			'outline'      => array( __( 'Desired outline', 'direct-openai-article-generator' ), 6000 ),
			'model'        => array( __( 'Model', 'direct-openai-article-generator' ), 150 ),
			'temperature'  => array( __( 'Temperature (optional)', 'direct-openai-article-generator' ), 10 ),
		);
		foreach ( $fields as $key => $field ) {
			echo '<tr><th scope="row"><label for="doag-' . esc_attr( $key ) . '">' . esc_html( $field[0] ) . '</label></th><td>';
			if ( in_array( $key, array( 'instructions', 'outline' ), true ) ) {
				echo '<textarea rows="4" class="large-text" id="doag-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" maxlength="' . esc_attr( (string) $field[1] ) . '">' . esc_textarea( $this->input[ $key ] ) . '</textarea>';
			} else {
				$number = in_array( $key, array( 'length', 'temperature' ), true );
				echo '<input class="regular-text" type="' . ( $number ? 'number' : 'text' ) . '" id="doag-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $this->input[ $key ] ) . '" maxlength="' . esc_attr( (string) $field[1] ) . '"';
				if ( 'length' === $key ) {
					echo ' min="200" max="4000" step="1"';
				} elseif ( 'temperature' === $key ) {
					echo ' min="0" max="2" step="0.1" aria-describedby="doag-temperature-help"';
				}
				echo in_array( $key, array( 'topic', 'language', 'length', 'model' ), true ) ? ' required>' : '>';
			}
			if ( 'temperature' === $key ) {
				echo '<p class="description" id="doag-temperature-help">' . esc_html__( 'Leave empty for the model default. Only set this if your model supports temperature. Models must support Chat Completions and JSON Schema structured output.', 'direct-openai-article-generator' ) . '</p>';
			}
			echo '</td></tr>';
		}
		echo '<tr><th scope="row">' . esc_html__( 'Additional content', 'direct-openai-article-generator' ) . '</th><td>';
		foreach ( array(
			'include_faq'  => __( 'Generate FAQ', 'direct-openai-article-generator' ),
			'include_meta' => __( 'Generate meta description', 'direct-openai-article-generator' ),
			'include_seo'  => __( 'Generate SEO title', 'direct-openai-article-generator' ),
		) as $key => $label ) {
			echo '<p><label><input type="checkbox" name="' . esc_attr( $key ) . '" value="1" ' . checked( $this->input[ $key ], true, false ) . '> ' . esc_html( $label ) . '</label></p>';
		}
		echo '</td></tr><tr><th scope="row"><label for="doag-status">' . esc_html__( 'Article status', 'direct-openai-article-generator' ) . '</label></th><td><select name="status" id="doag-status">';
		foreach ( Settings::statuses() as $status => $label ) {
			if ( 'private' !== $status || current_user_can( 'publish_posts' ) ) {
				echo '<option value="' . esc_attr( $status ) . '" ' . selected( $this->input['status'], $status, false ) . '>' . esc_html( $label ) . '</option>';
			}
		}
		echo '</select></td></tr></table>';
		submit_button( __( 'Generate article', 'direct-openai-article-generator' ) );
		echo '<p id="doag-progress" role="status" hidden>' . esc_html__( 'Queuing your article for background generation…', 'direct-openai-article-generator' ) . '</p></form></div>';
	}
}
