<?php
/**
 * Queue class files.
 *
 * @package WP_Queue
 */

 // Exit if accessed directly.
// namespace WP_Queue;

if ( ! class_exists( 'RestApi' ) ) {

	/**
	 * Rest_API class.
	 */
	class RestApi {

		/**
		 * namespace
		 *
		 * (default value: 'wp-queue/v1')
		 *
		 * @var string
		 * @access private
		 */
		private $namespace = 'wp-queue/v1';

		/**
		 * Create the rest API routes.
		 *
		 * @access public
		 */
		public function __construct() {
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		}


		public function register_routes() {

			register_rest_route(
				$this->namespace,
				'jobs',
				array(
					'methods'             => array( 'GET' ),
					'callback'            => array( $this, 'jobs' ),
					'permission_callback' => array( $this, 'default_permission_check' ),
				)
			);

			register_rest_route(
				$this->namespace,
				'jobs/count',
				array(
					'methods'             => array( 'GET' ),
					'callback'            => array( $this, 'count' ),
					'permission_callback' => array( $this, 'default_permission_check' ),
				)
			);

		}

		/*
		 * Get Jobs.
		 */
		public function jobs( WP_REST_Request $request ) {

			$results = wp_queue_get_jobs();

			return rest_ensure_response( $results );
		}

		/*
		 * Get Count.
		 */
		public function count( WP_REST_Request $request ) {

			$results = wp_queue_count_jobs();
			return rest_ensure_response( array( 'total' => $results ) );
		}

		/*
		 * Default Permissions.
		 */
		public function default_permission_check() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return new WP_Error( 'forbidden', 'You are not allowed to do that.', array( 'status' => 403 ) );
			}
			return true;
		}

	}

	$rest_api = new RestAPI();

}
