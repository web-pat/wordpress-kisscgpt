<?php
/** Settings API screen. @package DirectOpenAIArticleGenerator */

namespace DOAG\Admin;

use DOAG\Settings;

defined( 'ABSPATH' ) || exit;

final class Settings_Page {

	public function __construct( private Settings $settings ) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'settings' ) );
	}

	public function menu(): void {
		add_options_page( __( 'OpenAI Article Generator', 'direct-openai-article-generator' ), __( 'OpenAI Article Generator', 'direct-openai-article-generator' ), 'manage_options', 'doag-settings', array( $this, 'render' ) );
	}

	public function settings(): void {
		// Do not autoload the secret on unrelated frontend requests.
		add_option( Settings::OPTION, $this->settings->defaults(), '', false );
		register_setting(
			'doag_settings_group',
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this->settings, 'sanitize' ),
				'show_in_rest'      => false,
			)
		);
		add_settings_section( 'doag_connection', __( 'Connection and defaults', 'direct-openai-article-generator' ), '__return_false', 'doag-settings' );
		$fields = array(
			'api_key'       => __( 'OpenAI API key', 'direct-openai-article-generator' ),
			'model'         => __( 'Model', 'direct-openai-article-generator' ),
			'system_prompt' => __( 'Default system prompt', 'direct-openai-article-generator' ),
			'language'      => __( 'Default language', 'direct-openai-article-generator' ),
			'status'        => __( 'Default article status', 'direct-openai-article-generator' ),
		);
		foreach ( $fields as $key => $label ) {
			add_settings_field(
				$key,
				$label,
				array( $this, 'field' ),
				'doag-settings',
				'doag_connection',
				array(
					'label_for' => 'doag-' . $key,
					'key'       => $key,
				)
			);
		}
	}

	public function field( array $args ): void {
		$key    = $args['key'];
		$values = $this->settings->all();
		$name   = Settings::OPTION . '[' . $key . ']';
		$id     = 'doag-' . $key;
		if ( 'api_key' === $key ) {
			if ( $this->settings->has_constant() ) {
				echo '<p>' . esc_html__( 'API key configured through wp-config.php.', 'direct-openai-article-generator' ) . '</p>';
				return;
			}
			echo '<input type="password" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="" autocomplete="new-password" maxlength="512">';
			echo '<p class="description">' . esc_html__( 'Leave blank to keep the saved key. The saved key is never displayed.', 'direct-openai-article-generator' ) . '</p>';
			echo '<p>' . ( '' !== $this->settings->api_key() ? esc_html__( 'A key is saved.', 'direct-openai-article-generator' ) : esc_html__( 'No key is saved.', 'direct-openai-article-generator' ) ) . '</p>';
			echo '<label><input type="checkbox" name="doag_settings[delete_api_key]" value="1"> ' . esc_html__( 'Delete saved API key', 'direct-openai-article-generator' ) . '</label>';
		} elseif ( 'system_prompt' === $key ) {
			echo '<textarea class="large-text" rows="6" maxlength="12000" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( $values[ $key ] ) . '</textarea>';
		} elseif ( 'status' === $key ) {
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
			foreach ( Settings::statuses() as $status => $label ) {
				echo '<option value="' . esc_attr( $status ) . '" ' . selected( $values[ $key ], $status, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
		} else {
			echo '<input type="text" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $values[ $key ] ) . '" maxlength="150" required>';
		}
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot manage these settings.', 'direct-openai-article-generator' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'OpenAI Article Generator', 'direct-openai-article-generator' ) . '</h1>';
		echo '<p>' . esc_html__( 'When you generate an article, your inputs and system prompt are sent directly to OpenAI. An OpenAI API account with billing is required. This plugin has no telemetry, author-side data collection, external tracking scripts or tracking cookies.', 'direct-openai-article-generator' ) . '</p>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'doag_settings_group' );
		do_settings_sections( 'doag-settings' );
		submit_button();
		echo '</form></div>';
	}
}
