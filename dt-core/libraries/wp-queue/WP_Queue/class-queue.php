<?php
/**
 * Queue class files.
 *
 * @package WP_Queue
 */

namespace WP_Queue;

use WP_Queue\Connections\ConnectionInterface;

if ( ! class_exists( 'Queue' ) ) {

	/**
	 * Queue class.
	 */
	class Queue {

		/**
		 * The connection type you want to use to store your Queue.
		 *
		 * @var ConnectionInterface
		 */
		protected $connection;

		/**
		 * The Queue's cron worker.
		 *
		 * @var Cron
		 */
		protected $cron;

		/**
		 * Queue constructor.
		 *
		 * @param ConnectionInterface $connection Queue Data Connection.
		 */
		public function __construct( ConnectionInterface $connection ) {
			$this->connection = $connection;
		}

		/**
		 * Push a job onto the queue;
		 *
		 * @param Job    $job      Job to push to queue.
		 * @param int    $delay    Seconds to delay job.
		 * @param string $category Optional category to tag jobs.
		 * @return bool|int
		 */
		public function push( Job $job, $delay = 0, $category = '' ) {
			return $this->connection->push( $job, $delay, $category );
		}

		/**
		 * Create a cron worker.
		 *
		 * @param int $attempts Time to attempt successful job execution.
		 * @param int $interval Interval to run cron.
		 *
		 * @return Cron
		 */
		public function cron( $attempts = 3, $interval = 5 ) {
			if ( is_null( $this->cron ) ) {
				$this->cron = new Cron( get_class( $this->connection ), $this->worker( $attempts ), $interval );
				$this->cron->init();
			}

			return $this->cron;
		}

		/**
		 * Create a new worker.
		 *
		 * @param int $attempts Attempts.
		 *
		 * @return Worker
		 */
		public function worker( $attempts ) {
			return new Worker( $this->connection, $attempts );
		}
	}
}
