<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

class Disciple_Tools_Notifications_Scheduler {

    /**
     * The hour of the daily schedule
     */
    const DAILY_RUN_TIME = 0;
    const DEBUG          = true;

    private $queue_manager;
    private $notifications_manager;

    public function __construct( $notifications_manager ) {

        $this->queue_manager = new Disciple_Tools_Notifications_Queue();
        $this->notifications_manager = $notifications_manager;

        if ( self::DEBUG ) {
            add_filter( 'cron_schedules', array( $this, 'debug_cron_schedules' ) );
        }

        if ( ! wp_next_scheduled( 'dt_hourly_notification_schedule' ) ) {
            $this->setup_hourly_schedule();
        }
        if ( ! wp_next_scheduled( 'dt_daily_notification_schedule' ) ) {
            $this->setup_daily_schedule();
        }

        add_action( 'dt_hourly_notification_schedule', array( $this, 'hourly_schedule' ) );
        add_action( 'dt_daily_notification_schedule', array( $this, 'daily_schedule' ) );
        add_action( 'switch_theme', array( $this, 'deactivate_schedules' ) );
    }

    /**
     * remove schedule hooks
     */
    public function deactivate_schedules() {
        if ( wp_next_scheduled( 'dt_hourly_notification_schedule' ) ) {
            wp_clear_scheduled_hook( 'dt_hourly_notification_schedule' );
        }
        if ( wp_next_scheduled( 'dt_daily_notification_schedule' ) ) {
            wp_clear_scheduled_hook( 'dt_daily_notification_schedule' );
        }
    }

    /**
     * Setup hourly batch notification schedule
     */
    private function setup_hourly_schedule() {
        if ( ! wp_next_scheduled( 'dt_hourly_notification_schedule' ) ) {
            $next_hour = self::DEBUG ? time() : $this->get_next_hourly_time()->getTimestamp();
            $time_schedule = self::DEBUG ? 'minutely' : 'hourly';
            wp_schedule_event( $next_hour, $time_schedule, 'dt_hourly_notification_schedule' );
        }
    }

    /**
     * Setup daily batch notification schedule
     */
    private function setup_daily_schedule() {
        $next_day = self::DEBUG ? time() : $this->get_next_daily_time( self::DAILY_RUN_TIME );
        if ( ! wp_next_scheduled( 'dt_daily_notification_schedule' ) ) {
            wp_schedule_event( $next_day, 'daily', 'dt_daily_notification_schedule' );
        }
    }

    public function hourly_schedule() {
        if ( self::DEBUG ) {
            error_log( 'This is my "hourly" cron going off' );
        }

        $unsent_notifications = $this->queue_manager->get_unsent_email_notifications( 'hourly' );
        $this->send_batch_emails( $unsent_notifications, 'hourly' );
    }

    public function daily_schedule() {
        if ( self::DEBUG ) {
            error_log( 'This is my daily cron going off' );
        }

        $unsent_notifications = $this->queue_manager->get_unsent_email_notifications( 'daily' );
        $this->send_batch_emails( $unsent_notifications, 'daily' );
    }

    /**
     * Send all unsent notifications as batch emails to users
     *
     * @param array $unsent_notifications
     * @param string $time_schedule
     *
     * @return void
     */
    private function send_batch_emails( array $unsent_notifications, string $time_schedule ) {

        $notifications_by_user_by_post = [];
        // sort the notifications by user, and then by record
        foreach ($unsent_notifications as $notification) {
            $user_id = $notification["user_id"];
            $post_id = $notification["post_id"];
            if ( ! isset( $notifications_by_user_by_post[$user_id] ) ) {
                $notifications_by_user_by_post[$user_id] = [];
            }
            if ( ! isset( $notifications_by_user_by_post[$user_id][$post_id] ) ) {
                $notifications_by_user_by_post[$user_id][$post_id] = [];
            }
            $notifications_by_user_by_post[$user_id][$post_id][] = $notification;
        }

        // loop through the notifications by user
        foreach ( $notifications_by_user_by_post as $user_id => $notifications_by_post ) {
            $user = get_userdata( $user_id );
            if ( ! $user ) {
                continue;
            }

            $email_body = '';
            $sent_notifications = [];
            foreach ($notifications_by_post as $post_id => $notifications) {
                $sent_notifications = array_merge( $sent_notifications, $notifications );

                $post_notifications_email = '## ' . dt_make_post_email_subject( $post_id ) . "\r\n";
                foreach ($notifications as $notification) {
                    $email_body_for_notification = $this->notifications_manager->get_notification_message_html( $notification, true, true );
                    $post_notifications_email .= "\r\n" . $email_body_for_notification;
                }
                $post_notifications_email .= dt_make_post_email_footer( $post_id );
                $email_body .= "\r\n\r\n" . $post_notifications_email;
            }
            $email_body .= "\r\n" . dt_make_email_footer();

            $subject = '';
            switch ($time_schedule) {
                case 'hourly':
                    $subject = esc_html__( "Hourly Digest", "disciple_tools" );
                    break;
                case 'daily':
                    $subject = esc_html__( "Daily Digest", "disciple_tools" );
                    break;
                default:
                    break;
            }

            $did_send = dt_send_email(
                $user->user_email,
                $subject,
                $email_body
            );

            if ( $did_send ) {
                $this->queue_manager->remove_sent_notifications( $sent_notifications );
            }
        }
    }

    /**
     * Returns the time of the next 'o' clock e.g. 10:00
     *
     * @return DateTime
     */
    private function get_next_hourly_time() {
        $date_string = gmdate( 'c' );
        $date = new DateTime( $date_string );
        $seconds = $date->format( 's' );
        $minutes = $date->format( 'i' );
        if ( $minutes > 0 ) {
            $date->modify( "+1 hour" );
            $date->modify( '-' . $minutes . ' minutes' );
        }
        $date->modify( "-$seconds seconds" );
        return $date;
    }

    /**
     * Returns the time of the next daily schedule e.g. 00:00
     *
     * @return DateTime
     */
    private function get_next_daily_time( int $scheduled_hour ) {
        $date_string = gmdate( 'c' );
        $date = new DateTime( $date_string );
        $hours = intval( $date->format( 'G' ) );
        if ( $hours < $scheduled_hour ) {
            $date->modify( "+1 day" );
        }
        $date->setTime( $scheduled_hour, 0 );

        return $date;
    }

    public function debug_cron_schedules( $schedules ) {
        $schedules['minutely'] = array(
            'interval' => 60,
            'display'  => 'Every Minute'
        );
        return $schedules;
    }
}
