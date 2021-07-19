<?php

/**
 * Disciple_Tools_Notifications_Queue
 *
 * @class   Disciple_Tools_Notifications_Queue
 * @version ?
 * @since   ?
 * @package Disciple_Tools
 *
 */

if ( !defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Disciple_Tools_Notifications_Queue {

    const DEFAULT_ALLOWED_SCHEDULES = [
        'hourly',
        'daily',
    ];

    private $allowed_schedules;

    public function __construct() {
        $this->allowed_schedules = apply_filters( 'dt_allowed_schedules', self::DEFAULT_ALLOWED_SCHEDULES );
    }

    private function add_to_queue( stdClass $notification, string $type ) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->dt_notifications_queue,
            [
                'notification_id' => $notification->id,
                'type' => $type,
            ],
            [ '%d', '%s' ]
        );
    }

    /**
     * Add a notification to the queue to be emailed with the $schedule_time batch email
     *
     * $schedule_time can be one of 'hourly' | 'daily
     *
     * @param stdClass $notification
     * @param string $schedule_time
     */
    public function schedule_email( stdClass $notification, $schedule_time ) {
        if ( ! $notification ) {
            throw new Exception( "Notification must be supplied" );
        }
        if ( $this->verify_schedule_time( $schedule_time ) ) {
            throw new Exception( "Notification Queue schedule $schedule_time not allowed" );
        }
        $this->add_to_queue( $notification, "email_$schedule_time" );
    }

    /**
     * Get the unsent notifications from the queue for the email_$schedule_time type
     *
     * @param string $schedule_time One of default 'hourly' or 'daily'
     *
     * @return array
     */
    public function get_unsent_email_notifications( string $schedule_time ) {
        global $wpdb;

        if ( $this->verify_schedule_time( $schedule_time ) ) {
            throw new Exception( "Notification Queue schedule $schedule_time not allowed" );
        }

        $type = "email_$schedule_time";
        $results = $wpdb->get_results(
            $wpdb->prepare("
                SELECT n.*, q.id AS queue_id
                FROM $wpdb->dt_notifications AS n
                JOIN $wpdb->dt_notifications_queue AS q
                ON n.id = q.notification_id
                WHERE q.type = %s
                AND date_sent IS NULL
            ", $type),
            ARRAY_A
        );

        return $results;
    }

    /**
     * Remove sent notifications from the queue
     *
     * @param array $notifications
     *
     * @return void
     */
    public function remove_sent_notifications( array $notifications ) {
        global $wpdb;

        if ( count( $notifications ) === 0 ) {
            return;
        }

        $queue_ids = array_map( function ( $notification ) {
            return $notification['queue_id'];
        }, $notifications );

        $ids = dt_array_to_sql( $queue_ids );

        // phpcs:disable
        // WordPress.WP.PreparedSQL.NotPrepared
        $wpdb->query(
        "
            DELETE FROM $wpdb->dt_notifications_queue
            WHERE id IN ( $ids )
        ");
        // phpcs:enable
    }

    private function verify_schedule_time( $schedule_time ) {
        return ( ! in_array( $schedule_time, $this->allowed_schedules, true ) );
    }
}
