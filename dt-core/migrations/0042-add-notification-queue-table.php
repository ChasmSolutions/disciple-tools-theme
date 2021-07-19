<?php
declare(strict_types=1);

if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly

/**
 * Class Disciple_Tools_Migration_0042
 *
 * Adds Notifications Queue Table
 */
class Disciple_Tools_Migration_0042 extends Disciple_Tools_Migration {
    public function up() {
        global $wpdb;

        if ( ! isset( $wpdb->dt_notifications_queue ) ) {
            $wpdb->dt_notifications_queue = $wpdb->prefix . 'dt_notifications_queue';
        }

        $charset_collate = $wpdb->get_charset_collate();
        $rv = $wpdb->query(
            "CREATE TABLE $wpdb->dt_notifications_queue (
                `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `notification_id` BIGINT(20) UNSIGNED NOT NULL,
                `type` varchar(20) NOT NULL DEFAULT '',
                `date_queued` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `date_sent` DATETIME NULL,
                PRIMARY KEY (`id`)
            ) $charset_collate;"
        ); // WPCS: unprepared SQL OK
        if ( $rv == false ) {
            throw new Exception( "Got error when creating table $wpdb->dt_notifications_queue: $wpdb->last_error" );
        }
    }

    public function down() {
        global $wpdb;
        $rv = $wpdb->query( "DROP TABLE $wpdb->dt_notifications_queue" );
        if ( $rv == false ) {
            throw new Exception( "Got error when dropping table $wpdb->dt_notifications_queue: $wpdb->last_error" );
        }
    }

    public function test() {
    }

    public function get_expected_tables(): array {
        return [];
    }
}
