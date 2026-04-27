<?php
/**
 * Custom provider class.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom provider implementation.
 */
class AI_SEO_GEO_Custom_Provider implements AI_SEO_GEO_AI_Provider_Interface {

	/**
	 * Provider runtime configuration.
	 *
	 * @var array
	 */
	private $config = array();

	/**
	 * Constructor.
	 *
	 * @param array $config Provider config.
	 */
	public function __construct( $config = array() ) {
		$this->config = $config;
	}

	/**
	 * Gets provider key.
	 *
	 * @return string
	 */
	public function get_provider_key() {
		return 'custom';
	}

	/**
	 * Gets provider name.
	 *
	 * @return string
	 */
	public function get_provider_name() {
		return 'Custom';
	}

	/**
	 * Gets default base URL.
	 *
	 * @return string
	 */
	public function get_default_base_url() {
		return '';
	}

	/**
	 * Gets default model.
	 *
	 * @return string
	 */
	public function get_default_model() {
		return '';
	}

	/**
	 * Generates AI response.
	 *
	 * @param array $messages Chat messages.
	 * @param array $options  Request options.
	 *
	 * @return array
	 */
	public function generate( $messages, $options = array() ) {
		$config = $this->prepare_runtime_config( $options );

		if ( empty( $config['api_key'] ) || empty( $config['base_url'] ) || empty( $config['model'] ) ) {
			return $this->build_error_result( __( 'Provider configuration is incomplete.', 'ai-seo-geo-optimizer' ), '', $config['model'] );
		}

		$endpoint_format = isset( $options['endpoint_format'] ) ? sanitize_text_field( $options['endpoint_format'] ) : 'chat_completions';
		$endpoint_path   = 'responses' === $endpoint_format ? '/responses' : '/chat/completions';
		$endpoint        = untrailingslashit( $config['base_url'] ) . $endpoint_path;

		$request_body = 'responses' === $endpoint_format
			? $this->build_responses_body( $messages, $config )
			: $this->build_chat_completions_body( $messages, $config );

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => $config['timeout'],
				'headers' => array(
					'Authorization' => 'Bearer ' . $config['api_key'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $request_body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->build_error_result(
				sprintf(
					/* translators: %s error detail. */
					__( 'Network error: %s', 'ai-seo-geo-optimizer' ),
					$response->get_error_message()
				),
				'',
				$config['model']
			);
		}

		$http_code    = (int) wp_remote_retrieve_response_code( $response );
		$raw_response = (string) wp_remote_retrieve_body( $response );
		$decoded      = json_decode( $raw_response, true );

		if ( $http_code < 200 || $http_code >= 300 ) {
			return $this->build_error_result( $this->map_http_error( $http_code, $decoded ), $raw_response, $config['model'] );
		}

		$content = $this->extract_content_from_response( $decoded, $endpoint_format );
		if ( '' === $content ) {
			return $this->build_error_result( __( 'Empty response from provider.', 'ai-seo-geo-optimizer' ), $raw_response, $config['model'] );
		}

		$structured_output_manager = new AI_SEO_GEO_Structured_Output_Manager();
		$parsed_json               = $structured_output_manager->parse_json_response( $content );
		if ( ! $parsed_json['success'] ) {
			return $this->build_error_result(
				$parsed_json['error'],
				$raw_response,
				$config['model'],
				array(
					'is_json_parse_failed'  => true,
					'http_status'           => $http_code,
					'json_error'            => $parsed_json['json_error'] ?? '',
					'raw_response_preview'  => (string) ( $parsed_json['raw_preview'] ?? mb_substr( $raw_response, 0, 500 ) ),
					'looks_like_markdown'   => ! empty( $parsed_json['markdown_wrapped'] ),
					'looks_truncated'       => ! empty( $parsed_json['likely_truncated'] ),
				)
			);
		}

		return array(
			'success'      => true,
			'data'         => $parsed_json['data'],
			'error'        => '',
			'raw_response' => $raw_response,
			'provider_key' => $this->get_provider_key(),
			'model'        => $config['model'],
		);
	}

	/**
	 * Runs provider connection test.
	 *
	 * @return array
	 */
	public function test_connection() {
		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'Return valid JSON only.',
			),
			array(
				'role'    => 'user',
				'content' => '{"status":"ok"}',
			),
		);

		$result = $this->generate(
			$messages,
			array(
				'temperature'     => 0,
				'max_tokens'      => 20,
				'require_json'    => true,
				'endpoint_format' => 'chat_completions',
			)
		);

		if ( $result['success'] ) {
			$result['message'] = __( 'Provider is working normally.', 'ai-seo-geo-optimizer' );
		} else {
			$result['message'] = $result['error'];
		}

		return $result;
	}

	/**
	 * Prepares runtime config.
	 *
	 * @param array $options Runtime overrides.
	 *
	 * @return array
	 */
	private function prepare_runtime_config( $options ) {
		$model = ! empty( $options['model'] ) ? sanitize_text_field( $options['model'] ) : ( $this->config['default_model'] ?? $this->get_default_model() );

		return array(
			'base_url'    => ! empty( $options['base_url'] ) ? esc_url_raw( $options['base_url'] ) : ( $this->config['base_url'] ?? $this->get_default_base_url() ),
			'api_key'     => ! empty( $options['api_key'] ) ? sanitize_text_field( $options['api_key'] ) : ( $this->config['api_key'] ?? '' ),
			'model'       => $model,
			'timeout'     => max( 5, absint( $options['timeout'] ?? ( $this->config['timeout'] ?? 60 ) ) ),
			'temperature' => isset( $options['temperature'] ) ? (float) $options['temperature'] : 0.2,
			'max_tokens'  => max( 1, absint( $options['max_tokens'] ?? 800 ) ),
			'require_json'=> ! empty( $options['require_json'] ),
		);
	}

	/**
	 * Builds chat/completions body.
	 *
	 * @param array $messages Messages.
	 * @param array $config   Runtime config.
	 *
	 * @return array
	 */
	private function build_chat_completions_body( $messages, $config ) {
		$body = array(
			'model'       => $config['model'],
			'messages'    => $messages,
			'temperature' => $config['temperature'],
			'max_tokens'  => $config['max_tokens'],
		);

		if ( $config['require_json'] ) {
			$body['response_format'] = array( 'type' => 'json_object' );
		}

		return $body;
	}

	/**
	 * Builds responses API body.
	 *
	 * @param array $messages Messages.
	 * @param array $config   Runtime config.
	 *
	 * @return array
	 */
	private function build_responses_body( $messages, $config ) {
		return array(
			'model'       => $config['model'],
			'input'       => $messages,
			'temperature' => $config['temperature'],
			'max_output_tokens' => $config['max_tokens'],
		);
	}

	/**
	 * Extracts content text from response payload.
	 *
	 * @param array  $decoded         Decoded response.
	 * @param string $endpoint_format responses|chat_completions.
	 *
	 * @return string
	 */
	private function extract_content_from_response( $decoded, $endpoint_format ) {
		if ( ! is_array( $decoded ) ) {
			return '';
		}

		if ( 'responses' === $endpoint_format && isset( $decoded['output'][0]['content'][0]['text'] ) ) {
			return (string) $decoded['output'][0]['content'][0]['text'];
		}

		if ( isset( $decoded['choices'][0]['message']['content'] ) ) {
			$content = $decoded['choices'][0]['message']['content'];
			if ( is_array( $content ) ) {
				return wp_json_encode( $content );
			}
			return (string) $content;
		}

		if ( isset( $decoded['output_text'] ) ) {
			return (string) $decoded['output_text'];
		}

		return '';
	}

	/**
	 * Decodes JSON with markdown cleanup fallback.
	 *
	 * @param string $content Content string.
	 *
	 * @return array
	 */
	private function decode_json_with_cleanup( $content ) {
		$structured_output_manager = new AI_SEO_GEO_Structured_Output_Manager();
		return $structured_output_manager->parse_json_response( $content );
	}

	/**
	 * Maps HTTP errors to safe messages.
	 *
	 * @param int   $http_code HTTP status code.
	 * @param array $decoded   Decoded response.
	 *
	 * @return string
	 */
	private function map_http_error( $http_code, $decoded ) {
		$message = '';
		if ( is_array( $decoded ) && isset( $decoded['error']['message'] ) ) {
			$message = sanitize_text_field( $decoded['error']['message'] );
		}

		if ( 401 === $http_code || 403 === $http_code ) {
			return __( 'Authentication failed. Please check API key.', 'ai-seo-geo-optimizer' ) . ' ' . $message;
		}

		if ( 404 === $http_code ) {
			return __( 'Base URL or endpoint is invalid.', 'ai-seo-geo-optimizer' ) . ' ' . $message;
		}

		if ( 400 === $http_code ) {
			return __( 'Request was rejected. Please verify model and parameters.', 'ai-seo-geo-optimizer' ) . ' ' . $message;
		}

		if ( 408 === $http_code || 504 === $http_code ) {
			return __( 'Request timed out. Please increase timeout and retry.', 'ai-seo-geo-optimizer' ) . ' ' . $message;
		}

		if ( $http_code >= 500 ) {
			return __( 'Provider server error occurred.', 'ai-seo-geo-optimizer' ) . ' ' . $message;
		}

		return __( 'Provider request failed.', 'ai-seo-geo-optimizer' ) . ' ' . $message;
	}

	/**
	 * Builds error result structure.
	 *
	 * @param string $error Error message.
	 * @param string $raw   Raw response.
	 * @param string $model Model name.
	 *
	 * @return array
	 */
	private function build_error_result( $error, $raw, $model, $debug = array() ) {
		return array(
			'success'      => false,
			'data'         => array(),
			'error'        => sanitize_text_field( (string) $error ),
			'raw_response' => (string) $raw,
			'provider_key' => $this->get_provider_key(),
			'model'        => (string) $model,
			'debug'        => is_array( $debug ) ? $debug : array(),
		);
	}
}
