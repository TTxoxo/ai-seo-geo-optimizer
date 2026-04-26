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
 * Handles AI provider CRUD and test connection operations.
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
			$templates[ $provider->get_provider_key() ] = array(
				'provider_key'  => $provider->get_provider_key(),
				'provider_name' => $provider->get_provider_name(),
				'base_url'      => $provider->get_default_base_url(),
				'default_model' => $provider->get_default_model(),
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

		$rows = $wpdb->get_results( $query, ARRAY_A );

		foreach ( $rows as &$row ) {
			$row['api_key_masked'] = $this->mask_api_key( $this->decrypt_api_key( $row['api_key_encrypted'] ) );
		}
		unset( $row );

		return $rows;
	}

	/**
	 * Gets one provider by ID.
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

		$row = $wpdb->get_row( $query, ARRAY_A );

		if ( ! $row ) {
			return null;
		}

		$row['api_key_masked'] = $this->mask_api_key( $this->decrypt_api_key( $row['api_key_encrypted'] ) );

		return $row;
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
		$timeout       = absint( $raw_input['timeout'] ?? 60 );
		$status        = AI_SEO_GEO_Security::sanitize_text( $raw_input['status'] ?? 'inactive' );
		$api_key_raw   = AI_SEO_GEO_Security::sanitize_text( $raw_input['api_key'] ?? '' );

		if ( $timeout < 10 ) {
			$timeout = 10;
		}
		if ( $timeout > 300 ) {
			$timeout = 300;
		}

		if ( ! in_array( $status, array( 'active', 'inactive' ), true ) ) {
			$status = 'inactive';
		}

		if ( empty( $provider_key ) || empty( $provider_name ) || empty( $base_url ) ) {
			return array(
				'success' => false,
				'message' => __( 'Provider key, provider name, and base URL are required.', 'ai-seo-geo-optimizer' ),
			);
		}

		if ( 0 === $provider_id ) {
			if ( empty( $api_key_raw ) ) {
				return array(
					'success' => false,
					'message' => __( 'API key is required when creating a provider.', 'ai-seo-geo-optimizer' ),
				);
			}

			if ( $this->provider_key_exists( $provider_key ) ) {
				return array(
					'success' => false,
					'message' => __( 'Provider key already exists. Please use another key.', 'ai-seo-geo-optimizer' ),
				);
			}
		}

		$encrypted_api_key = '';
		if ( ! empty( $api_key_raw ) ) {
			$encrypted_api_key = $this->encrypt_api_key( $api_key_raw );
		} elseif ( ! empty( $existing['api_key_encrypted'] ) ) {
			$encrypted_api_key = $existing['api_key_encrypted'];
		}

		$data = array(
			'provider_key'      => $provider_key,
			'provider_name'     => $provider_name,
			'base_url'          => $base_url,
			'api_key_encrypted' => $encrypted_api_key,
			'default_model'     => $default_model,
			'timeout'           => $timeout,
			'status'            => $status,
			'updated_at'        => current_time( 'mysql' ),
		);

		if ( 0 === $provider_id ) {
			$data['created_at'] = current_time( 'mysql' );
			$inserted           = $wpdb->insert(
				$this->table_name,
				$data,
				array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
			);

			if ( false === $inserted ) {
				return array(
					'success' => false,
					'message' => __( 'Failed to create provider. Please try again.', 'ai-seo-geo-optimizer' ),
				);
			}

			return array(
				'success' => true,
				'message' => __( 'Provider created successfully.', 'ai-seo-geo-optimizer' ),
			);
		}

		$updated = $wpdb->update(
			$this->table_name,
			$data,
			array( 'id' => $provider_id ),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to update provider. Please try again.', 'ai-seo-geo-optimizer' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Provider updated successfully.', 'ai-seo-geo-optimizer' ),
		);
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
			return array(
				'success' => false,
				'message' => __( 'Provider not found.', 'ai-seo-geo-optimizer' ),
			);
		}

		$deleted = $wpdb->delete(
			$this->table_name,
			array( 'id' => $provider_id ),
			array( '%d' )
		);

		if ( false === $deleted ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to delete provider.', 'ai-seo-geo-optimizer' ),
			);
		}

		$this->log_manager->add_log(
			array(
				'action'       => 'provider_deleted',
				'message'      => sprintf(
					/* translators: %s provider key. */
					__( 'Provider deleted: %s', 'ai-seo-geo-optimizer' ),
					$provider['provider_key']
				),
				'context_json' => array(
					'provider_id'  => $provider_id,
					'provider_key' => $provider['provider_key'],
				),
			)
		);

		return array(
			'success' => true,
			'message' => __( 'Provider deleted successfully.', 'ai-seo-geo-optimizer' ),
		);
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
			return array(
				'success' => false,
				'message' => __( 'Invalid provider status.', 'ai-seo-geo-optimizer' ),
			);
		}

		$updated = $wpdb->update(
			$this->table_name,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $provider_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to update provider status.', 'ai-seo-geo-optimizer' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Provider status updated.', 'ai-seo-geo-optimizer' ),
		);
	}

	/**
	 * Tests provider connection.
	 *
	 * @param int $provider_id Provider ID.
	 *
	 * @return array
	 */
	public function test_connection( $provider_id ) {
		$provider = $this->get_provider( $provider_id );

		if ( ! $provider ) {
			return array(
				'success' => false,
				'message' => __( 'Provider not found.', 'ai-seo-geo-optimizer' ),
			);
		}

		$provider_handler = $this->get_provider_handler( $provider['provider_key'] );
		$api_key          = $this->decrypt_api_key( $provider['api_key_encrypted'] );

		if ( empty( $api_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'API key is missing. Please update and try again.', 'ai-seo-geo-optimizer' ),
			);
		}

		$endpoint = untrailingslashit( $provider['base_url'] ) . $provider_handler->get_test_endpoint_path();
		$model    = ! empty( $provider['default_model'] ) ? $provider['default_model'] : $provider_handler->get_default_model();

		if ( empty( $model ) ) {
			return array(
				'success' => false,
				'message' => __( 'Default model is empty. Please set a model and retry.', 'ai-seo-geo-optimizer' ),
			);
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => absint( $provider['timeout'] ),
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $provider_handler->build_test_request_body( $model ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s error message. */
					__( 'Connection failed: %s', 'ai-seo-geo-optimizer' ),
					esc_html( $response->get_error_message() )
				),
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = (string) wp_remote_retrieve_body( $response );
		$json        = json_decode( $body, true );

		if ( $status_code >= 200 && $status_code < 300 ) {
			return array(
				'success' => true,
				'message' => __( 'Connection test passed.', 'ai-seo-geo-optimizer' ),
			);
		}

		$error_message = __( 'Provider returned an unexpected error.', 'ai-seo-geo-optimizer' );

		if ( is_array( $json ) && isset( $json['error']['message'] ) ) {
			$error_message = sanitize_text_field( $json['error']['message'] );
		}

		return array(
			'success' => false,
			'message' => sprintf(
				/* translators: 1: status code, 2: error message. */
				__( 'Connection failed (HTTP %1$d): %2$s', 'ai-seo-geo-optimizer' ),
				$status_code,
				esc_html( $error_message )
			),
		);
	}

	/**
	 * Checks if provider_key exists.
	 *
	 * @param string $provider_key Provider key.
	 *
	 * @return bool
	 */
	private function provider_key_exists( $provider_key ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT COUNT(1) FROM {$this->table_name} WHERE provider_key = %s",
			$provider_key
		);

		$count = (int) $wpdb->get_var( $query );

		return $count > 0;
	}

	/**
	 * Gets provider handler by key.
	 *
	 * @param string $provider_key Provider key.
	 *
	 * @return AI_SEO_GEO_AI_Provider_Interface
	 */
	private function get_provider_handler( $provider_key ) {
		switch ( $provider_key ) {
			case 'openai':
				return new AI_SEO_GEO_OpenAI_Provider();
			case 'deepseek':
				return new AI_SEO_GEO_DeepSeek_Provider();
			case 'qwen':
				return new AI_SEO_GEO_Qwen_Provider();
			case 'custom':
			default:
				return new AI_SEO_GEO_Custom_Provider();
		}
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
		if ( false !== $decoded ) {
			return $decoded;
		}

		return '';
	}

	/**
	 * Masks API key for output.
	 *
	 * @param string $api_key API key value.
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
