<?php
/**
 * Syncrhronous Connection interface Class file.
 *
 * @package WP_Queue
 */

namespace WP_Queue\Connections;

use Exception;
use WP_Queue\Job;

/**
 * SyncConnection class.
 *
 * @implements ConnectionInterface
 */
class SyncConnection implements ConnectionInterface {
	/**
	 * Execute the job immediately without pushing to the queue.
	 *
	 * @param Job    $job       Job to push to queue.
	 * @param int    $delay     Delay time for job.
	 * @param string $category  Category tag to label jobs in db.
	 *
	 * @return bool|int
	 */
	public function push( Job $job, $delay = 0, $category = '' ) {
		$job->handle();

		return true;
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
	 *
	 * @return bool
	 */
	public function delete( $job ) {
		return false;
	}

	/**
	 * Release a job back onto the queue.
	 *
	 * @param Job $job Job to release.
	 *
	 * @return bool
	 */
	public function release( $job ) {
		return false;
	}

	/**
	 * Push a job onto the failure queue.
	 *
	 * @param Job       $job        Job that failed.
	 * @param Exception $exception  Exception thrown.
	 *
	 * @return bool
	 */
	public function failure( $job, Exception $exception ) {
		return false;
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
