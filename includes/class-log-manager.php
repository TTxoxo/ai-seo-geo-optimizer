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

		return false === $inserted ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Retrieves filtered logs with pagination.
	 *
	 * @param array $filters Filters.
	 *
	 * @return array
	 */
	public function get_logs_filtered( $filters = array() ) {
		global $wpdb;
		$jobs_table = $wpdb->prefix . 'ai_seo_jobs';

		$defaults = array(
			'post_id'   => 0,
			'action'    => '',
			'date_from' => '',
			'date_to'   => '',
			'paged'     => 1,
			'per_page'  => 20,
		);
		$filters = wp_parse_args( $filters, $defaults );
		$paged   = max( 1, absint( $filters['paged'] ) );
		$per_page = max( 10, min( 100, absint( $filters['per_page'] ) ) );
		$offset   = ( $paged - 1 ) * $per_page;

		$where  = ' WHERE 1=1 ';
		$params = array();

		if ( ! empty( $filters['post_id'] ) ) {
			$where    .= ' AND l.post_id = %d ';
			$params[] = absint( $filters['post_id'] );
		}
		if ( ! empty( $filters['action'] ) ) {
			$where    .= ' AND l.action = %s ';
			$params[] = sanitize_text_field( $filters['action'] );
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$where    .= ' AND DATE(l.created_at) >= %s ';
			$params[] = sanitize_text_field( $filters['date_from'] );
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where    .= ' AND DATE(l.created_at) <= %s ';
			$params[] = sanitize_text_field( $filters['date_to'] );
		}

		$count_sql  = "SELECT COUNT(1) FROM {$this->table_name} l {$where}";
		$count_query = empty( $params ) ? $count_sql : $wpdb->prepare( $count_sql, $params );
		$total_items = (int) $wpdb->get_var( $count_query );

		$sql = "SELECT l.*, p.post_title, j.status AS job_status
			FROM {$this->table_name} l
			LEFT JOIN {$wpdb->posts} p ON l.post_id = p.ID
			LEFT JOIN {$jobs_table} j ON l.job_id = j.id
			{$where}
			ORDER BY l.id DESC
			LIMIT %d OFFSET %d";

		$query_params   = array_merge( $params, array( $per_page, $offset ) );
		$data_query     = $wpdb->prepare( $sql, $query_params );
		$items          = $wpdb->get_results( $data_query, ARRAY_A );
		$total_pages    = (int) ceil( $total_items / $per_page );

		return array(
			'items'       => $items,
			'total_items' => $total_items,
			'total_pages' => max( 1, $total_pages ),
			'paged'       => $paged,
			'per_page'    => $per_page,
		);
	}

	/**
	 * Returns distinct actions for filter dropdown.
	 *
	 * @return array
	 */
	public function get_distinct_actions() {
		global $wpdb;
		$sql = "SELECT DISTINCT action FROM {$this->table_name} ORDER BY action ASC";
		return $wpdb->get_col( $sql );
	}
}
