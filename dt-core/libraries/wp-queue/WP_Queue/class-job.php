<?php
/**
 * Job class files.
 *
 * @package WP_Queue
 */

namespace WP_Queue;

use Exception;

if ( ! class_exists( 'Job' ) ) {
	/**
	 * Abstract Job class.
	 *
	 * @abstract
	 */
	abstract class Job {

		/**
		 * Job ID.
		 *
		 * @var int
		 */
		private $id;

		/**
		 * Number of times job has been attempted.
		 *
		 * @var int
		 */
		private $attempts;

		/**
		 * Time Job has been reserved at.
		 *
		 * @var Reserved At.
		 */
		private $reserved_at;

		/**
		 * Time Job is available at.
		 *
		 * @var Available At.
		 */
		private $available_at;

		/**
		 * Time Job was created at.
		 *
		 * @var Created At.
		 */
		private $created_at;

		/**
		 * Time Job was released at.
		 *
		 * @var bool
		 */
		private $released = false;

		/**
		 * Has job failed?
		 *
		 * @var bool
		 */
		private $failed = false;

		/**
		 * Handle job logic.
		 */
		abstract public function handle();

		/**
		 * Get job ID.
		 *
		 * @return int
		 */
		public function id() {
			return $this->id;
		}

		/**
		 * Set job ID.
		 *
		 * @param int $id Job ID.
		 */
		public function set_id( $id ) {
			$this->id = $id;
		}

		/**
		 * Get job attempts;
		 *
		 * @return int
		 */
		public function attempts() {
			return $this->attempts;
		}

		/**
		 * Set job attempts.
		 *
		 * @param int $attempts Attempt no.
		 */
		public function set_attempts( $attempts ) {
			$this->attempts = $attempts;
		}

		/**
		 * Get reserved at date.
		 *
		 * @return Reserved At.
		 */
		public function reserved_at() {
			return $this->reserved_at;
		}

		/**
		 * Set reserved at date.
		 *
		 * @param null|Carbon $reserved_at Date job was reserved.
		 */
		public function set_reserved_at( $reserved_at ) {
			$this->reserved_at = $reserved_at;
		}

		/**
		 * Get available at date.
		 *
		 * @return Carbon
		 */
		public function available_at() {
			return $this->available_at;
		}

		/**
		 * Set available at date.
		 *
		 * @param Carbon $available_at Available At.
		 */
		public function set_available_at( $available_at ) {
			$this->available_at = $available_at;
		}

		/**
		 * Get created at date.
		 *
		 * @return Carbon
		 */
		public function created_at() {
			return $this->created_at;
		}

		/**
		 * Set created at date.
		 *
		 * @param Carbon $created_at Created At.
		 */
		public function set_created_at( $created_at ) {
			$this->created_at = $created_at;
		}

		/**
		 * Flag job as released.
		 */
		public function release() {
			$this->released  = true;
			$this->attempts += 1;
		}

		/**
		 * Should the job be released back onto the queue?
		 *
		 * @return bool
		 */
		public function released() {
			return $this->released;
		}

		/**
		 * Flag job as failed.
		 */
		public function fail() {
			$this->failed = true;
		}

		/**
		 * Has the job failed?
		 *
		 * @return bool
		 */
		public function failed() {
			return $this->failed;
		}

		/**
		 * Determine which properties should be serialized.
		 *
		 * @return array
		 */
		public function __sleep() {
			$object_props   = get_object_vars( $this );
			$excluded_props = array(
				'id',
				'attempts',
				'reserved_at',
				'available_at',
				'created_at',
				'released',
				'failed',
			);

			foreach ( $excluded_props as $prop ) {
				unset( $object_props[ $prop ] );
			}

			return array_keys( $object_props );
		}

	}
}
