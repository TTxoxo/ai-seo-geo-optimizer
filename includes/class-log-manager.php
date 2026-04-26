<?php
/**
 * Log manager class.
 *
 * @package AI_SEO_GEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin logs.
 */
class AI_SEO_GEO_Log_Manager {

	/** @var string */
	private $table_name;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'ai_seo_logs';
	}

	/**
	 * Writes a log entry.
	 *
	 * @param array $args Log payload.
	 *
	 * @return int|false
	 */
	public function add_log( $args ) {
		global $wpdb;

		$defaults = array(
			'job_id'       => null,
			'post_id'      => null,
			'action'       => '',
			'message'      => '',
			'context_json' => '',
			'created_by'   => get_current_user_id(),
			'created_at'   => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $args, $defaults );

		$inserted = $wpdb->insert(
			$this->table_name,
			array(
				'job_id'       => $data['job_id'],
				'post_id'      => $data['post_id'],
				'action'       => sanitize_text_field( $data['action'] ),
				'message'      => sanitize_textarea_field( $data['message'] ),
				'context_json' => wp_json_encode( $data['context_json'] ),
				'created_by'   => absint( $data['created_by'] ),
				'created_at'   => $data['created_at'],
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Retrieves recent logs.
	 *
	 * @param int $limit Number of rows.
	 *
	 * @return array
	 */
	public function get_recent_logs( $limit = 20 ) {
		global $wpdb;

		$query = $wpdb->prepare( "SELECT * FROM {$this->table_name} ORDER BY id DESC LIMIT %d", absint( $limit ) );
		return $wpdb->get_results( $query, ARRAY_A );
	}

	/**
	 * Retrieves log rows joined with post title and job status.
	 *
	 * @param int $limit Limit.
	 *
	 * @return array
	 */
	public function get_logs_with_context( $limit = 50 ) {
		global $wpdb;
		$jobs_table = $wpdb->prefix . 'ai_seo_jobs';

		$query = $wpdb->prepare(
			"SELECT l.*, p.post_title, j.status AS job_status
			 FROM {$this->table_name} l
			 LEFT JOIN {$wpdb->posts} p ON l.post_id = p.ID
			 LEFT JOIN {$jobs_table} j ON l.job_id = j.id
			 ORDER BY l.id DESC LIMIT %d",
			absint( $limit )
		);

		return $wpdb->get_results( $query, ARRAY_A );
	}
}
