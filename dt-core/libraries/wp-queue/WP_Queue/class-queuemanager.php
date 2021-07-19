<?php
/**
 * QueueManager class files.
 *
 * @package WP_Queue
 */

namespace WP_Queue;

use WP_Queue\Connections\DatabaseConnection;
use WP_Queue\Connections\RedisConnection;
use WP_Queue\Connections\SyncConnection;
use WP_Queue\Exceptions\ConnectionNotFoundException;

if ( ! class_exists( 'QueueManager' ) ) {
	/**
	 * QueueManager class.
	 */
	class QueueManager {

		/**
		 * Array of connection instances.
		 *
		 * @var array
		 */
		protected static $instances = array();

		/**
		 * Resolve a Queue instance for required connection.
		 *
		 * @param string $connection The type of connection to use for your queue.
		 *
		 * @return Queue
		 */
		public static function resolve( $connection ) {
			$connection = apply_filters( 'wp_queue_default_connection', $connection );

			if ( isset( static::$instances[ $connection ] ) ) {
				return static::$instances[ $connection ];
			}

			return static::build( $connection );
		}

		/**
		 * Build a queue instance.
		 *
		 * @param string $connection Connection type.
		 *
		 * @return Queue
		 * @throws ConnectionNotFoundException Throws Cnnection not found except.
		 */
		protected static function build( $connection ) {
			$connections = static::connections();

			if ( empty( $connections[ $connection ] ) ) {
				throw new ConnectionNotFoundException();
			}

			static::$instances[ $connection ] = new Queue( $connections[ $connection ] );

			return static::$instances[ $connection ];
		}

		/**
		 * Get available connections.
		 *
		 * @return array
		 */
		protected static function connections() {
			$connections = array(
				'database' => new DatabaseConnection( $GLOBALS['wpdb'] ),
				'redis'    => new RedisConnection(),
				'sync'     => new SyncConnection(),
			);

			return apply_filters( 'wp_queue_connections', $connections );
		}
	}
}
