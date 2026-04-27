<?php
/**
 * AI Provider manager class.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles AI provider CRUD and runtime invocation.
 */
class AI_SEO_GEO_AI_Provider_Manager {

	/**
	 * Providers table name.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Log manager.
	 *
	 * @var AI_SEO_GEO_Log_Manager
	 */
	private $log_manager;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name  = $wpdb->prefix . 'ai_seo_providers';
		$this->log_manager = new AI_SEO_GEO_Log_Manager();
	}

	/**
	 * Gets provider defaults.
	 *
	 * @return array
	 */
	public function get_provider_templates() {
		$providers = array(
			new AI_SEO_GEO_OpenAI_Provider(),
			new AI_SEO_GEO_DeepSeek_Provider(),
			new AI_SEO_GEO_Qwen_Provider(),
			new AI_SEO_GEO_Custom_Provider(),
		);

		$templates = array();
		foreach ( $providers as $provider ) {
			$default_mode = 'auto';
			if ( in_array( $provider->get_provider_key(), array( 'qwen', 'deepseek' ), true ) ) {
				$default_mode = 'json_object';
			}

			$templates[ $provider->get_provider_key() ] = array(
				'provider_key'  => $provider->get_provider_key(),
				'provider_name' => $provider->get_provider_name(),
				'base_url'      => $provider->get_default_base_url(),
				'default_model' => $provider->get_default_model(),
				'structured_output_mode' => $default_mode,
			);
		}

		return $templates;
	}

	/**
	 * Gets all providers.
	 *
	 * @return array
	 */
	public function get_providers() {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT * FROM {$this->table_name} ORDER BY id DESC LIMIT %d",
			200
		);
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		foreach ( $rows as &$row ) {
			$row = $this->with_safe_view_fields( $row );
		}
		unset( $row );

		return $rows;
	}

	/**
	 * Gets provider by ID.
	 *
	 * @param int $provider_id Provider ID.
	 *
	 * @return array|null
	 */
	public function get_provider( $provider_id ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d LIMIT 1",
			absint( $provider_id )
		);
		$row   = $wpdb->get_row( $query, ARRAY_A );

		if ( ! $row ) {
			return null;
		}

		return $this->with_safe_view_fields( $row );
	}

	/**
	 * Gets provider by provider_key.
	 *
	 * @param string $provider_key Provider key.
	 *
	 * @return array|null
	 */
	public function get_provider_by_key( $provider_key ) {
		global $wpdb;

		$provider_key = sanitize_text_field( $provider_key );
		$query        = $wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE provider_key = %s LIMIT 1",
			$provider_key
		);
		$row          = $wpdb->get_row( $query, ARRAY_A );

		if ( ! $row ) {
			return null;
		}

		return $this->with_safe_view_fields( $row );
	}

	/**
	 * Gets active providers.
	 *
	 * @return array
	 */
	public function get_active_providers() {
		$providers = $this->get_providers();
		return array_values(
			array_filter(
				$providers,
				static function ( $provider ) {
					return isset( $provider['status'] ) && 'active' === $provider['status'];
				}
			)
		);
	}

	/**
	 * Gets default provider from settings fallback to first active provider.
	 *
	 * @return array|null
	 */
	public function get_default_provider() {
		$settings             = get_option( 'ai_seo_geo_settings', array() );
		$default_provider_key = isset( $settings['default_provider_key'] ) ? sanitize_text_field( $settings['default_provider_key'] ) : '';

		if ( '' !== $default_provider_key ) {
			$provider = $this->get_provider_by_key( $default_provider_key );
			if ( $provider && 'active' === $provider['status'] ) {
				return $provider;
			}
		}

		$active = $this->get_active_providers();
		return ! empty( $active ) ? $active[0] : null;
	}

	/**
	 * Calls provider by provider ID.
	 *
	 * @param int   $provider_id Provider ID.
	 * @param array $messages    Chat messages.
	 * @param array $options     Runtime options.
	 *
	 * @return array
	 */
	public function call_provider_by_id( $provider_id, $messages, $options = array() ) {
		$provider = $this->get_provider( $provider_id );
		if ( ! $provider ) {
			return $this->build_error_result( __( 'Provider not found.', 'ai-seo-geo-optimizer' ) );
		}

		return $this->call_provider( $provider, $messages, $options );
	}

	/**
	 * Calls provider by key.
	 *
	 * @param string $provider_key Provider key.
	 * @param array  $messages     Chat messages.
	 * @param array  $options      Runtime options.
	 *
	 * @return array
	 */
	public function call_provider_by_key( $provider_key, $messages, $options = array() ) {
		$provider = $this->get_provider_by_key( $provider_key );
		if ( ! $provider ) {
			return $this->build_error_result( __( 'Provider not found.', 'ai-seo-geo-optimizer' ) );
		}

		return $this->call_provider( $provider, $messages, $options );
	}

	/**
	 * Calls default provider.
	 *
	 * @param array $messages Chat messages.
	 * @param array $options  Runtime options.
	 *
	 * @return array
	 */
	public function call_default_provider( $messages, $options = array() ) {
		$provider = $this->get_default_provider();
		if ( ! $provider ) {
			return $this->build_error_result( __( 'No active provider available.', 'ai-seo-geo-optimizer' ) );
		}

		return $this->call_provider( $provider, $messages, $options );
	}

	/**
	 * Saves provider.
	 *
	 * @param array $raw_input Raw form input.
	 * @param int   $provider_id Optional provider ID.
	 *
	 * @return array
	 */
	public function save_provider( $raw_input, $provider_id = 0 ) {
		global $wpdb;

		$provider_id = absint( $provider_id );
		$existing    = $provider_id > 0 ? $this->get_provider( $provider_id ) : null;

		$provider_key = AI_SEO_GEO_Security::sanitize_text( $raw_input['provider_key'] ?? '' );
		$provider_key = strtolower( $provider_key );
		$provider_key = preg_replace( '/[^a-z0-9_-]/', '', $provider_key );

		$provider_name = AI_SEO_GEO_Security::sanitize_text( $raw_input['provider_name'] ?? '' );
		$base_url      = untrailingslashit( AI_SEO_GEO_Security::sanitize_url( $raw_input['base_url'] ?? '' ) );
		$default_model = AI_SEO_GEO_Security::sanitize_text( $raw_input['default_model'] ?? '' );
		$structured_output_mode = AI_SEO_GEO_Security::sanitize_text( $raw_input['structured_output_mode'] ?? '' );
		$timeout       = max( 10, min( 300, absint( $raw_input['timeout'] ?? 60 ) ) );
		$status        = AI_SEO_GEO_Security::sanitize_text( $raw_input['status'] ?? 'inactive' );
		$api_key_raw   = AI_SEO_GEO_Security::sanitize_text( $raw_input['api_key'] ?? '' );

		if ( ! in_array( $status, array( 'active', 'inactive' ), true ) ) {
			$status = 'inactive';
		}

		if ( empty( $provider_key ) || empty( $provider_name ) || empty( $base_url ) ) {
			return array( 'success' => false, 'message' => __( 'Provider key, provider name, and base URL are required.', 'ai-seo-geo-optimizer' ) );
		}

		if ( 0 === $provider_id && empty( $api_key_raw ) ) {
			return array( 'success' => false, 'message' => __( 'API key is required when creating a provider.', 'ai-seo-geo-optimizer' ) );
		}

		if ( 0 === $provider_id && $this->provider_key_exists( $provider_key ) ) {
			return array( 'success' => false, 'message' => __( 'Provider key already exists. Please use another key.', 'ai-seo-geo-optimizer' ) );
		}

		$encrypted_api_key = '';
		if ( ! empty( $api_key_raw ) ) {
			$encrypted_api_key = $this->encrypt_api_key( $api_key_raw );
		} elseif ( ! empty( $existing['api_key_encrypted'] ) ) {
			$encrypted_api_key = $existing['api_key_encrypted'];
		}

		$allowed_modes = array( 'auto', 'json_object', 'prompt_only' );
		if ( ! in_array( $structured_output_mode, $allowed_modes, true ) ) {
			if ( in_array( $provider_key, array( 'qwen', 'deepseek' ), true ) ) {
				$structured_output_mode = 'json_object';
			} else {
				$structured_output_mode = 'auto';
			}
		}

		$data = array(
			'provider_key'      => $provider_key,
			'provider_name'     => $provider_name,
			'base_url'          => $base_url,
			'api_key_encrypted' => $encrypted_api_key,
			'default_model'     => $default_model,
			'structured_output_mode' => $structured_output_mode,
			'timeout'           => $timeout,
			'status'            => $status,
			'updated_at'        => current_time( 'mysql' ),
		);

		if ( 0 === $provider_id ) {
			$data['created_at'] = current_time( 'mysql' );
			$inserted           = $wpdb->insert( $this->table_name, $data, array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ) );
			if ( false === $inserted ) {
				return array( 'success' => false, 'message' => __( 'Failed to create provider. Please try again.', 'ai-seo-geo-optimizer' ) );
			}

			return array( 'success' => true, 'message' => __( 'Provider created successfully.', 'ai-seo-geo-optimizer' ) );
		}

		$updated = $wpdb->update(
			$this->table_name,
			$data,
			array( 'id' => $provider_id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return array( 'success' => false, 'message' => __( 'Failed to update provider. Please try again.', 'ai-seo-geo-optimizer' ) );
		}

		return array( 'success' => true, 'message' => __( 'Provider updated successfully.', 'ai-seo-geo-optimizer' ) );
	}

	/**
	 * Deletes provider.
	 *
	 * @param int $provider_id Provider ID.
	 *
	 * @return array
	 */
	public function delete_provider( $provider_id ) {
		global $wpdb;

		$provider_id = absint( $provider_id );
		$provider    = $this->get_provider( $provider_id );
		if ( ! $provider ) {
			return array( 'success' => false, 'message' => __( 'Provider not found.', 'ai-seo-geo-optimizer' ) );
		}

		$deleted = $wpdb->delete( $this->table_name, array( 'id' => $provider_id ), array( '%d' ) );
		if ( false === $deleted ) {
			return array( 'success' => false, 'message' => __( 'Failed to delete provider.', 'ai-seo-geo-optimizer' ) );
		}

		$this->log_manager->add_log(
			array(
				'action'       => 'provider_deleted',
				'message'      => sprintf( __( 'Provider deleted: %s', 'ai-seo-geo-optimizer' ), $provider['provider_key'] ),
				'context_json' => array(
					'provider_id'  => $provider_id,
					'provider_key' => $provider['provider_key'],
				),
			)
		);

		return array( 'success' => true, 'message' => __( 'Provider deleted successfully.', 'ai-seo-geo-optimizer' ) );
	}

	/**
	 * Updates provider status.
	 *
	 * @param int    $provider_id Provider ID.
	 * @param string $status      active|inactive.
	 *
	 * @return array
	 */
	public function update_status( $provider_id, $status ) {
		global $wpdb;

		$status = AI_SEO_GEO_Security::sanitize_text( $status );
		if ( ! in_array( $status, array( 'active', 'inactive' ), true ) ) {
			return array( 'success' => false, 'message' => __( 'Invalid provider status.', 'ai-seo-geo-optimizer' ) );
		}

		$updated = $wpdb->update(
			$this->table_name,
			array( 'status' => $status, 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => absint( $provider_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return array( 'success' => false, 'message' => __( 'Failed to update provider status.', 'ai-seo-geo-optimizer' ) );
		}

		return array( 'success' => true, 'message' => __( 'Provider status updated.', 'ai-seo-geo-optimizer' ) );
	}

	/**
	 * Tests provider connection by provider ID.
	 *
	 * @param int $provider_id Provider ID.
	 *
	 * @return array
	 */
	public function test_connection( $provider_id ) {
		$provider = $this->get_provider( $provider_id );
		if ( ! $provider ) {
			return array( 'success' => false, 'message' => __( 'Provider not found.', 'ai-seo-geo-optimizer' ) );
		}

		$instance = $this->get_provider_instance( $provider );
		$result   = $instance->test_connection();

		$this->log_provider_result( $provider, $result, 'test_connection' );

		return array(
			'success' => ! empty( $result['success'] ),
			'message' => ! empty( $result['success'] )
				? __( 'Provider is working normally.', 'ai-seo-geo-optimizer' )
				: ( ! empty( $result['error'] ) ? $result['error'] : __( 'Connection test failed.', 'ai-seo-geo-optimizer' ) ),
		);
	}

	/**
	 * Calls provider instance with unified return structure.
	 *
	 * @param array $provider Provider row.
	 * @param array $messages Messages.
	 * @param array $options  Runtime options.
	 *
	 * @return array
	 */
	private function call_provider( $provider, $messages, $options ) {
		if ( empty( $provider['status'] ) || 'active' !== $provider['status'] ) {
			return $this->build_error_result( __( 'Selected provider is inactive.', 'ai-seo-geo-optimizer' ) );
		}

		$instance = $this->get_provider_instance( $provider );
		$result   = $instance->generate( $messages, $options );

		$this->log_provider_result( $provider, $result, 'generate' );

		return $this->normalize_result( $result, $provider );
	}

	/**
	 * Gets provider implementation instance.
	 *
	 * @param array $provider Provider row.
	 *
	 * @return AI_SEO_GEO_AI_Provider_Interface
	 */
	private function get_provider_instance( $provider ) {
		$config = array(
			'provider_key'  => $provider['provider_key'],
			'base_url'      => $provider['base_url'],
			'default_model' => $provider['default_model'],
			'structured_output_mode' => isset( $provider['structured_output_mode'] ) ? sanitize_text_field( $provider['structured_output_mode'] ) : 'auto',
			'timeout'       => absint( $provider['timeout'] ),
			'api_key'       => $this->decrypt_api_key( $provider['api_key_encrypted'] ),
		);

		switch ( $provider['provider_key'] ) {
			case 'openai':
				return new AI_SEO_GEO_OpenAI_Provider( $config );
			case 'deepseek':
				return new AI_SEO_GEO_DeepSeek_Provider( $config );
			case 'qwen':
				return new AI_SEO_GEO_Qwen_Provider( $config );
			case 'custom':
			default:
				return new AI_SEO_GEO_Custom_Provider( $config );
		}
	}

	/**
	 * Normalizes return structure.
	 *
	 * @param array $result   Provider result.
	 * @param array $provider Provider row.
	 *
	 * @return array
	 */
	private function normalize_result( $result, $provider ) {
		return array(
			'success'      => ! empty( $result['success'] ),
			'data'         => $result['data'] ?? array(),
			'error'        => sanitize_text_field( (string) ( $result['error'] ?? '' ) ),
			'raw_response' => (string) ( $result['raw_response'] ?? '' ),
			'provider_key' => sanitize_text_field( (string) ( $result['provider_key'] ?? $provider['provider_key'] ) ),
			'model'        => sanitize_text_field( (string) ( $result['model'] ?? $provider['default_model'] ) ),
			'debug'        => isset( $result['debug'] ) && is_array( $result['debug'] ) ? $result['debug'] : array(),
		);
	}

	/**
	 * Adds safe display fields on provider row.
	 *
	 * @param array $provider Provider row.
	 *
	 * @return array
	 */
	private function with_safe_view_fields( $provider ) {
		$provider['api_key_masked'] = $this->mask_api_key( $this->decrypt_api_key( $provider['api_key_encrypted'] ) );
		return $provider;
	}

	/**
	 * Logs provider request result.
	 *
	 * @param array  $provider Provider row.
	 * @param array  $result   Result structure.
	 * @param string $action   Action name.
	 *
	 * @return void
	 */
	private function log_provider_result( $provider, $result, $action ) {
		$debug                = isset( $result['debug'] ) && is_array( $result['debug'] ) ? $result['debug'] : array();
		$raw_response         = isset( $result['raw_response'] ) ? (string) $result['raw_response'] : '';
		$raw_response_preview = isset( $debug['raw_response_preview'] ) ? (string) $debug['raw_response_preview'] : mb_substr( $raw_response, 0, 1000 );
		$raw_response_preview = mb_substr( $raw_response_preview, 0, 1000 );

		$this->log_manager->add_log(
			array(
				'action'  => 'provider_' . sanitize_key( $action ),
				'message' => ! empty( $result['success'] )
					? sprintf( __( 'Provider %s request succeeded.', 'ai-seo-geo-optimizer' ), $provider['provider_key'] )
					: sprintf( __( 'Provider %s request failed.', 'ai-seo-geo-optimizer' ), $provider['provider_key'] ),
				'context_json' => array(
					'provider_id'  => (int) $provider['id'],
					'provider_key' => $provider['provider_key'],
					'success'      => ! empty( $result['success'] ),
					'error'        => sanitize_text_field( (string) ( $result['error'] ?? '' ) ),
					'model'        => sanitize_text_field( (string) ( $result['model'] ?? $provider['default_model'] ) ),
					'http_status'  => isset( $debug['http_status'] ) ? absint( $debug['http_status'] ) : 0,
					'json_error'   => sanitize_text_field( (string) ( $debug['json_error'] ?? '' ) ),
					'raw_response_preview' => sanitize_textarea_field( $raw_response_preview ),
					'response_format_used' => sanitize_text_field( (string) ( $debug['response_format_used'] ?? '' ) ),
					'fallback_mode' => sanitize_text_field( (string) ( $debug['fallback_mode'] ?? '' ) ),
				),
			)
		);
	}

	/**
	 * Builds unified error result.
	 *
	 * @param string $error Error message.
	 *
	 * @return array
	 */
	private function build_error_result( $error ) {
		return array(
			'success'      => false,
			'data'         => array(),
			'error'        => sanitize_text_field( $error ),
			'raw_response' => '',
			'provider_key' => '',
			'model'        => '',
		);
	}

	/**
	 * Checks if provider key exists.
	 *
	 * @param string $provider_key Provider key.
	 *
	 * @return bool
	 */
	private function provider_key_exists( $provider_key ) {
		global $wpdb;

		$query = $wpdb->prepare( "SELECT COUNT(1) FROM {$this->table_name} WHERE provider_key = %s", $provider_key );
		return (int) $wpdb->get_var( $query ) > 0;
	}

	/**
	 * Encrypts API key.
	 *
	 * @param string $api_key API key value.
	 *
	 * @return string
	 */
	private function encrypt_api_key( $api_key ) {
		$secret = wp_salt( 'auth' );
		$method = 'aes-256-cbc';
		$iv     = substr( hash( 'sha256', $secret ), 0, 16 );

		if ( function_exists( 'openssl_encrypt' ) ) {
			$encrypted = openssl_encrypt( $api_key, $method, $secret, 0, $iv );
			if ( false !== $encrypted ) {
				return $encrypted;
			}
		}

		return base64_encode( $api_key );
	}

	/**
	 * Decrypts API key.
	 *
	 * @param string $encrypted Encrypted key.
	 *
	 * @return string
	 */
	private function decrypt_api_key( $encrypted ) {
		$secret = wp_salt( 'auth' );
		$method = 'aes-256-cbc';
		$iv     = substr( hash( 'sha256', $secret ), 0, 16 );

		if ( function_exists( 'openssl_decrypt' ) ) {
			$decrypted = openssl_decrypt( $encrypted, $method, $secret, 0, $iv );
			if ( false !== $decrypted ) {
				return $decrypted;
			}
		}

		$decoded = base64_decode( $encrypted, true );
		return false !== $decoded ? $decoded : '';
	}

	/**
	 * Masks API key for output.
	 *
	 * @param string $api_key API key.
	 *
	 * @return string
	 */
	private function mask_api_key( $api_key ) {
		if ( empty( $api_key ) ) {
			return '—';
		}

		$len = strlen( $api_key );
		if ( $len <= 8 ) {
			return str_repeat( '*', $len );
		}

		return substr( $api_key, 0, 3 ) . '-****' . substr( $api_key, -4 );
	}
}
