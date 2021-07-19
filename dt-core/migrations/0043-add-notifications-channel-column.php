<?php
declare(strict_types=1);

if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly

/**
 * Class Disciple_Tools_Migration_0043
 *
 * Adds channels to dt_notifications table
 */
class Disciple_Tools_Migration_0043 extends Disciple_Tools_Migration {
    public function up() {
        global $wpdb;

        if ( ! isset( $wpdb->dt_notifications ) ) {
            $wpdb->dt_notifications = $wpdb->prefix . 'dt_notifications';
        }

        // add parent_id
        $wpdb->query( "ALTER TABLE $wpdb->dt_notifications ADD `channels` VARCHAR(70) NULL DEFAULT NULL AFTER `is_new`" );

    }

    public function down() {
        global $wpdb;

        $wpdb->query( "ALTER TABLE $wpdb->dt_notifications DROP COLUMN `channels`" );
    }

    public function test() {
    }

    public function get_expected_tables(): array {
        return [];
    }
}
