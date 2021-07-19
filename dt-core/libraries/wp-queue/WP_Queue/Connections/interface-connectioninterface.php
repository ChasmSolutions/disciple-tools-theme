<?php
/**
 * Connection interface file.
 *
 * @package WP_Queue
 */

namespace WP_Queue\Connections;

use Exception;
use WP_Queue\Job;

interface ConnectionInterface {

	/**
	 * Push a job onto the queue.
	 *
	 * @param Job    $job      Job to send to queue.
	 * @param int    $delay    Time to delay job.
	 * @param string $category Category tag.
	 *
	 * @return bool|int
	 */
	public function push( Job $job, $delay = 0, $category = '' );

	/**
	 * Retrieve a job from the queue.
	 *
	 * @return bool|Job
	 */
	public function pop();

	/**
	 * Delete a job from the queue.
	 *
	 * @param Job $job Job to delete.
	 */
	public function delete( $job );

	/**
	 * Release a job back onto the queue.
	 *
	 * @param Job $job Job to release.
	 */
	public function release( $job );

	/**
	 * Push a job onto the failure queue.
	 *
	 * @param Job       $job        Job that faied.
	 * @param Exception $exception  Exception thrown.
	 *
	 * @return bool
	 */
	public function failure( $job, Exception $exception );

	/**
	 * Get total jobs in the queue.
	 *
	 * @return int
	 */
	public function jobs();

	/**
	 * Get total jobs in the failures queue.
	 *
	 * @return int
	 */
	public function failed_jobs();

}
