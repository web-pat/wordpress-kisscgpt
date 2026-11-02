<?php
/** Single-attempt generation in WP-Cron, never in an admin page request. @package DirectOpenAIArticleGenerator */

namespace DOAG;

defined( 'ABSPATH' ) || exit;

final class Background_Generator {

	public const HOOK = 'doag_generate_article';

	public function __construct( private Article_Service $service ) {}

	public function register(): void {
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( Job_Store::CLEANUP_HOOK, array( Job_Store::class, 'cleanup' ) );
	}

	public function enqueue( array $input, string $form_token ): string|\WP_Error {
		if ( ! Article_Service::can_generate() ) {
			return new \WP_Error( 'permission', __( 'You cannot generate articles.', 'direct-openai-article-generator' ) );
		}
		$valid = Article_Input::validate( $input );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$id = wp_generate_uuid4();
		if ( ! Generation_Lock::acquire( Generation_Lock::QUEUE, $id, 15 * MINUTE_IN_SECONDS ) ) {
			return new \WP_Error( 'busy', __( 'An article is already queued or being generated on this site. Wait for it to finish before trying again.', 'direct-openai-article-generator' ) );
		}
		$user_id   = get_current_user_id();
		$token_key = 'doag_form_' . $form_token;
		if ( (int) get_transient( $token_key ) !== $user_id ) {
			Generation_Lock::release( Generation_Lock::QUEUE, $id );
			return new \WP_Error( 'replay', __( 'This form was already submitted or has expired. Review existing posts before trying again.', 'direct-openai-article-generator' ) );
		}
		$job = array(
			'user_id' => $user_id,
			'input'   => $input,
			'status'  => 'queued',
			'created' => time(),
			'expires' => time() + 15 * MINUTE_IN_SECONDS,
		);
		if ( ! Job_Store::create( $id, $job ) ) {
			Generation_Lock::release( Generation_Lock::QUEUE, $id );
			return new \WP_Error( 'queue', __( 'The generation job could not be saved. No API request was made.', 'direct-openai-article-generator' ) );
		}
		$user_key = 'doag_user_job_' . $user_id;
		$previous = get_option( $user_key );
		if ( ! update_option( $user_key, $id, false ) ) {
			Job_Store::delete( $id );
			Generation_Lock::release( Generation_Lock::QUEUE, $id );
			return new \WP_Error( 'queue', __( 'The generation job could not be saved. No API request was made.', 'direct-openai-article-generator' ) );
		}
		$scheduled = wp_schedule_single_event( time(), self::HOOK, array( $id ), true );
		if ( is_wp_error( $scheduled ) || ! $scheduled ) {
			Job_Store::delete( $id );
			if ( is_string( $previous ) ) {
				update_option( $user_key, $previous, false );
			} else {
				delete_option( $user_key );
			}
			Generation_Lock::release( Generation_Lock::QUEUE, $id );
			return new \WP_Error( 'queue', __( 'The generation job could not be scheduled. Check WordPress cron configuration. No API request was made.', 'direct-openai-article-generator' ) );
		}
		delete_transient( $token_key );
		return $id;
	}

	public function current(): ?array {
		$user_key = 'doag_user_job_' . get_current_user_id();
		$id       = get_option( $user_key, get_transient( $user_key ) );
		$job      = is_string( $id ) ? Job_Store::get( $id ) : null;
		if ( ! is_array( $job ) || ( $job['user_id'] ?? 0 ) !== get_current_user_id() ) {
			return null;
		}
		// Preserve the status link when adopting an older transient-backed job.
		if ( get_option( $user_key ) !== $id ) {
			Job_Store::remember_user( get_current_user_id(), $id );
		}
		if ( in_array( $job['status'], array( 'queued', 'running' ), true ) && $job['expires'] <= time() ) {
			$job['status'] = 'error';
			$job['error']  = __( 'Generation did not finish in time. Check existing posts before submitting again; an API request may have incurred costs. Also check WordPress cron and server timeouts.', 'direct-openai-article-generator' );
		}
		return $job;
	}

	public function run( string $id ): void {
		$job = Job_Store::get( $id );
		if ( ! is_array( $job ) || 'queued' !== ( $job['status'] ?? '' ) ) {
			return;
		}
		$owner = wp_generate_uuid4();
		if ( ! Generation_Lock::acquire( Generation_Lock::WORKER, $owner, 5 * MINUTE_IN_SECONDS ) ) {
			return; // Never automatically retry a possibly in-flight API request.
		}
		$previous_user = get_current_user_id();
		try {
			// Re-read after claiming the worker; duplicate cron deliveries must not call OpenAI.
			$job = Job_Store::get( $id );
			if ( ! is_array( $job ) || 'queued' !== ( $job['status'] ?? '' ) ) {
				return;
			}
			if ( $job['created'] + 10 * MINUTE_IN_SECONDS <= time() ) {
				$result = new \WP_Error( 'expired', __( 'The queued job expired before it started. Check WordPress cron configuration. No API request was made.', 'direct-openai-article-generator' ) );
			} else {
				// Persist the claim before making a billable request. Fail closed on storage failure.
				if ( ! Job_Store::claim( $id, $job ) ) {
					return;
				}
				$job['status'] = 'running';
				wp_set_current_user( $job['user_id'] );
				$result = $this->service->generate( $job['input'] );
			}
		} catch ( \Throwable $error ) {
			// Never expose exception messages: extension code may include credentials or prompts.
			$result = new \WP_Error( 'generation', __( 'Generation stopped unexpectedly. Check existing posts before submitting again; the API request may have incurred costs.', 'direct-openai-article-generator' ) );
		} finally {
			wp_set_current_user( $previous_user );
			Generation_Lock::release( Generation_Lock::WORKER, $owner );
			Generation_Lock::release( Generation_Lock::QUEUE, $id );
		}
		if ( is_wp_error( $result ) ) {
			$job['status'] = 'error';
			$job['error']  = $result->get_error_message();
		} else {
			$job['status']  = 'complete';
			$job['post_id'] = $result;
		}
		Job_Store::save( $id, $job );
	}
}
