<?php
/**
 * Redis Connection interface Class file.
 *
 * @package WP_Queue
 */

namespace WP_Queue\Connections;

use Exception;
use WP_Queue\Job;


/**
 * Redis Connection class.
 */
class RedisConnection implements ConnectionInterface {

	/**
	 * Push a job onto the queue.
	 *
	 * @param Job    $job          Job to push to queue.
	 * @param int    $delay        Delay time for job.
	 * @param string $category  Category tag to label jobs in db.
	 *
	 * @return bool|int
	 */
	public function push( Job $job, $delay = 0, $category = '' ) {
		return false;
	}

	/**
	 * Retrieve a job from the queue.
	 *
	 * @return bool|Job
	 */
	public function pop() {
		return false;
	}

	/**
	 * Delete a job from the queue.
	 *
	 * @param Job $job Job to delete.
	 */
	public function delete( $job ) {
	}

	/**
	 * Release a job back onto the queue.
	 *
	 * @param Job $job Job to release.
	 */
	public function release( $job ) {
	}

	/**
	 * Push a job onto the failure queue.
	 *
	 * @param Job       $job       Job totpush on failure queue.
	 * @param Exception $exception Exception thrown.
	 */
	public function failure( $job, Exception $exception ) {
	}

	/**
	 * Get total jobs in the queue.
	 *
	 * @return int
	 */
	public function jobs() {
		return 0;
	}

	/**
	 * Get total jobs in the failures queue.
	 *
	 * @return int
	 */
	public function failed_jobs() {
		return 0;
	}

}
