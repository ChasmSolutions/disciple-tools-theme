<?php

/**
 * Contains create, update and delete functions for notifications, wrapping access to
 * the database
 *
 * @class      Disciple_Tools_Notifications
 * @version    0.1.0
 * @since      0.1.0
 * @package    Disciple.Tools
 * @author     Disciple.Tools
 */

if ( !defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * @since 0.1.0
 * @see   Disciple_Tools_Activity_Log_API::insert
 *
 * @param array $args
 *
 * @return void
 */
function dt_notification_insert( $args = [] ) {
    Disciple_Tools_Notifications::insert_notification( $args );
}

/**
 * @param array $args
 */
function dt_notification_delete( $args = [] ) {
    Disciple_Tools_Notifications::delete_notification( $args );
}

/**
 * @param array $args
 */
function dt_notification_delete_by_post( $args = [] ) {
    Disciple_Tools_Notifications::delete_by_post( $args );
}

/**
 * Class Disciple_Tools_Notifications
 */
class Disciple_Tools_Notifications
{

    /**
     * Disciple_Tools_Admin_Menus The single instance of Disciple_Tools_Admin_Menus.
     *
     * @var     object
     * @access    private
     * @since     0.1.0
     */
    private static $_instance = null;
    const DEFAULT_CHANNELS = [ 'web', 'email' ];

    /**
     * Main Disciple_Tools_Notifications Instance
     * Ensures only one instance of Disciple_Tools_Notifications is loaded or can be loaded.
     *
     * @since 0.1.0
     * @static
     * @return Disciple_Tools_Notifications instance
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }
    /** End instance() */

    /**
     * Constructor function.
     *
     * @access  public
     * @since   0.1.0
     */
    public function __construct() {
        include( 'notifications-comments.php' );
        new Disciple_Tools_Notifications_Comments();
        add_action( 'send_notification_on_channels', [ $this, "send_notification_on_channels" ], 10, 4 );
    } // End __construct()

    /**
     * Insert statement
     *
     * @since 0.1.0
     *
     * @param array $args
     *
     * @return void
     */
    public static function insert_notification( $args ) {
        global $wpdb;

        if ( $args['user_id'] == $args['source_user_id'] ) { // check if source of the event and notification target are the same, if so, don't create notification. i.e. I don't want notifications of my own actions.
            return;
        }

        $wpdb->insert(
            $wpdb->dt_notifications,
            [
                'user_id'             => $args['user_id'],
                'source_user_id'      => $args['source_user_id'],
                'post_id'             => $args['post_id'],
                'secondary_item_id'   => $args['secondary_item_id'],
                'notification_name'   => $args['notification_name'],
                'notification_action' => $args['notification_action'],
                'notification_note'   => "", // notification note is generated (and translated)
                'date_notified'       => $args['date_notified'],
                'is_new'              => $args['is_new'],
                'channels'            => isset( $args['channels'] ) ? $args['channels'] : null,
                'field_key'           => $args['field_key'],
                'field_value'         => $args['field_value'],
            ],
            [ '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ]
        );

        /** Fire action after insert */
        do_action( 'dt_insert_notification', $args );
    }

    /**
     * Get single notification
     *
     * @since 1.5.0
     *
     * @param array $args
     *
     * @return stdClass|null
     */
    public static function get_notification( $args ) {
        global $wpdb;

        $args = wp_parse_args(
            $args,
            [
                'user_id'           => '',
                'post_id'           => '',
                'secondary_item_id' => '',
                'notification_name' => '',
                'date_notified'     => '',
            ]
        );

        $results = $wpdb->get_results(
            $wpdb->prepare( "
                SELECT * FROM $wpdb->dt_notifications
                WHERE user_id = %s
                AND post_id = %s
                AND secondary_item_id = %s
                AND notification_name = %s
                AND date_notified = %s
            ", [
                    $args['user_id'],
                    $args['post_id'],
                    $args['secondary_item_id'],
                    $args['notification_name'],
                    $args['date_notified'],
                ]
            )
        );

        do_action( 'dt_get_notification', $args, $results );

        return empty( $results ) ? null : $results[0];
    }

    /**
     * Delete single notification
     *
     * @since 0.1.0
     *
     * @param array $args
     *
     * @return void
     */
    public static function delete_notification( $args ) {
        global $wpdb;

        $args = wp_parse_args(
            $args,
            [
                'user_id'           => '',
                'post_id'           => '',
                'secondary_item_id' => '',
                'notification_name' => '',
                'date_notified'     => '',
            ]
        );

        $wpdb->delete(
            $wpdb->dt_notifications,
            [
                'user_id'           => $args['user_id'],
                'post_id'           => $args['post_id'],
                'secondary_item_id' => $args['secondary_item_id'],
                'notification_name' => $args['notification_name'],
                'date_notified'     => $args['date_notified'],
            ]
        );

        /** Final action on insert */
        do_action( 'dt_delete_notification', $args );
    }

    /**
     * Delete all notifications for a post with a certain notification name
     *
     * @since 0.1.0
     *
     * @param array $args
     *
     * @return void
     */
    public static function delete_by_post( $args ) {
        global $wpdb;

        $args = wp_parse_args(
            $args,
            [
                'post_id'           => '',
                'notification_name' => '',
            ]
        );

        $wpdb->delete(
            $wpdb->dt_notifications,
            [
                'post_id'           => $args['post_id'],
                'notification_name' => $args['notification_name'],
            ]
        );

        /** Final action on insert */
        do_action( 'dt_delete_post_notifications', $args );
    }

    /**
     * Mark the is_new field to 0 after user has viewed notification
     *
     * @param $notification_id
     *
     * @return array
     */
    public static function mark_viewed( $notification_id ) {
        global $wpdb;

        $wpdb->update(
            $wpdb->dt_notifications,
            [
                'is_new' => 0,
            ],
            [
                'id' => $notification_id,
                'user_id' => get_current_user_id()
            ]
        );

        return $wpdb->last_error ? [
        'status' => false,
        'message' => $wpdb->last_error
        ] : [
        'status' => true,
        'rows_affected' => $wpdb->rows_affected
        ];
    }

    /**
     * Mark the is_new field to 0 after user has viewed notification
     *
     * @param $notification_id
     *
     * @return array
     */
    public static function mark_unread( $notification_id ) {
        global $wpdb;

        $wpdb->update(
            $wpdb->dt_notifications,
            [
                'is_new' => 1,
            ],
            [
                'id' => $notification_id,
                'user_id' => get_current_user_id()
            ]
        );

        return $wpdb->last_error ? [
        'status' => false,
        'message' => $wpdb->last_error
        ] : [
        'status' => true,
        'rows_affected' => $wpdb->rows_affected
        ];
    }

    /**
     * Mark all web notifications as viewed by user_id
     *
     * @param $user_id int
     *
     * @return array
     */
    public static function mark_all_viewed( int $user_id ) {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare("
                UPDATE
                    $wpdb->dt_notifications
                SET
                    is_new = %d
                WHERE user_id = %d
                    AND (
                        channels LIKE %s
                        OR channels IS NULL
                    )
            ", 0, $user_id, '%web%')
        );

        /* $wpdb->update(
            $wpdb->dt_notifications,
            [
                'is_new' => 0,
            ],
            [
                'user_id' => $user_id,
            ]
        ); */

        return $wpdb->last_error ? [
        'status' => false,
        'message' => $wpdb->last_error
        ] : [
        'status' => true,
        'rows_affected' => $wpdb->rows_affected
        ];
    }

    /**
     * Get all web notifications
     *
     * @param bool $all
     * @param int  $page
     * @param int  $limit
     *
     * @return array
     */
    public static function get_notifications( bool $all, int $page, int $limit ) {
        global $wpdb;

        $all_where = '';
        if ( !$all ) {
            $all_where = " AND is_new = '1'";
        }

        $user_id = get_current_user_id();

        $result = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                n.*,
                p.post_title
             FROM `$wpdb->dt_notifications` AS n
             INNER JOIN `$wpdb->posts` AS p
                ON n.post_id = p.ID
             WHERE user_id = %d
             AND is_new LIKE %s
             AND (
                 channels LIKE %s
                 OR channels IS NULL
                 )
             ORDER BY date_notified
             DESC LIMIT %d OFFSET %d",
            $user_id,
            $all ? '%' : '1',
            '%web%',
            $limit,
            $page
        ), ARRAY_A );

        if ( !$result ){
            $result = [];
        }

        /** User friendly timestamp */
        foreach ( $result as $key => $value ) {
            $result[ $key ]['pretty_time'] = self::pretty_timestamp( $value['date_notified'] );
            $result[ $key ]["notification_note"] = self::get_notification_message_html( $value );
        }

        return $result;

    }

    /**
     * Pretty time stamp helper function
     *
     * @param $timestamp
     *
     * @return false|string
     */

    public static function pretty_timestamp( $timestamp ) {
        /** Get current time */
        $now = current_time( 'timestamp' );

        /** Get "this" notification timestamp */
        $notification_date = strtotime( $timestamp );

        /** Initalize vars */
        $minutes = $hours = $days = $weeks = $diff = $months = $years = $message = "";

        /** Calculate time */
        $minutes = round( abs( $now - $notification_date ) / 60, 0 );
        $hours = round( ( $now - $notification_date ) / ( 60 * 60 ) );
        $days = round( ( $now - $notification_date ) / ( 60 * 60 * 24 ) );
        $weeks = ceil( abs( $now - $notification_date ) / 60 / 60 / 24 / 7 );

        /** Get number of months between now and timestamp. This was tricky... */
        $min_date = min( $now, $notification_date );
        $max_date = max( $now, $notification_date );
        $i = 0;
        while ( ( $min_date = strtotime( "+1 MONTH", $min_date ) ) <= $max_date ) {
            $i++;
        }
        $months = $i;

        /** Get number of years */
        $years = abs( $now - $notification_date ) / ( 60 * 60 * 24 * 365.25 );

        /** Cast an object onto our array of values for readability purposes moving forward */
        $range = array(
            'minutes' => $minutes,
            'hours' => $hours,
            'days' => $days,
            'weeks' => $weeks,
            'months' => $months,
            'years' => $years,
          );
          $range = (object) $range;

        /** Determine which condition meets "this" notification timestamp */

        /** The following 6 sprintf() items are the only items in this function that need to be translated in WP */
        if ($range->minutes < 60) {
            /** The exact number our minutes if this timestamp is < 60 minutes ago */
            $message = sprintf( _n( '%s minute ago', '%s minutes ago', $range->minutes, 'disciple_tools' ), $range->minutes );
        }

        elseif ( ( $range->minutes >= 60 ) && ( $range->hours < 24 ) ) {
            /** The exact number our hours if this timestamp is < 24 hours ago */
            $message = sprintf( _n( '%s hour ago', '%s hours ago', $range->hours, 'disciple_tools' ), $range->hours );
        }

        elseif ( ( $range->hours >= 24 ) && ( $range->days < 14 ) ) {
            /** The exact number of days if this timestamp is < 2 weeks ago */
            $message = sprintf( _n( '%s day ago', '%s days ago', $range->days, 'disciple_tools' ), $range->days );
        }

        elseif ( ( $range->days >= 14 ) && ( $range->weeks < 8 ) ) {
            /** The exact number of weeks if this timestamp is < 2 months ago */
            $message = sprintf( _n( '%s week ago', '%s weeks ago', $range->weeks, 'disciple_tools' ), $range->weeks );
        }

        elseif ( ( $range->weeks >= 8 ) && ( $range->months <= 12 ) ) {
            /** The exact number of months if this timestamp is < 1 year */
            $message = sprintf( _n( '%s month ago', '%s months ago', floor( $range->months ), 'disciple_tools' ), floor( $range->months ) );
        }

            /** The exact number of years and months if this timestamp is >= 1 year */
        elseif ( $range->months > 12 ) {
                /** Gets the exact number of years */
                $years = floor( abs( $range->months / 12 ) );

                /** Gets the exact number months after the number of years has been substracted */
                $months = $range->months - ( $years * 12 );

            if ( $range->months % 12 == 0 ) {

                /** Show exact amount of years version of message */
                $message = sprintf( _n( '%s year ago', '%s years ago', $years, 'disciple_tools' ), $years );
            } else {

                /** Show non-exact number of years version of message */
                $message = sprintf( _n( 'over %s year ago', 'over %s years ago', $years, 'disciple_tools' ), $years );
            }
        }
        /**
         * Use this as a test for future issues
         *  else {
         * $message = "the now : " . $now . " | the notification date : " . $notification_date . " | the minutes : " .$range->minutes . " | the hours : " .$range->hours . " | the days : " .$range->days. " | the weeks : " .$range->weeks. " | the months : ".$range->months. " |  the years : ".$range->years;
         * }
         */
        return array( $message, gmdate( "m/d/Y", $notification_date ) );
    }

    /**
     * Get user new web notifications count
     *
     * @return int
     */
    public static function get_new_notifications_count() {
        global $wpdb;

        $user_id = get_current_user_id();

        $result = $wpdb->get_var( $wpdb->prepare(
            "SELECT
                count(id)
            FROM
                `$wpdb->dt_notifications`
            WHERE
                user_id = %d
                AND is_new = '1'
                AND (
                    channels LIKE %s
                    OR channels IS NULL
                )", $user_id, '%web%' )
        );

        if ( !$result ) {
            $result = 0;
        }
        return (int) $result;
    }



    public static function send_notification_on_channels( $user_id, $notification, $notification_type, $already_sent = [] ){
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        $message = self::get_notification_message_html( $notification );
        $user_meta = get_user_meta( $user_id );
        $email_preference = get_user_meta( $user_id, 'email_preference', true );

        /**
         * Filter the available channels of communication, defaults to [ 'web', 'email' ]
         *
         * @since 1.7.0
         */
        $available_channels = apply_filters( 'dt_communication_channels', self::DEFAULT_CHANNELS );

        $open_channels = array_filter( $available_channels, function ( $channel ) use ( $notification_type, $user_meta, $user_id ) {
            return dt_user_notification_is_enabled( $notification_type, $channel, $user_meta, $user_id );
        });

        if ( empty( $open_channels ) ) {
            return;
        }

        // insert notification with all selected channels set.
        $is_only_email_channel = ( count( $open_channels ) === 1 && in_array( 'email', $open_channels ) );
        $notification_insert_needed = ! ( $is_only_email_channel && $email_preference === 'real-time' );
        if ( $notification_insert_needed ) {
            $notification['channels'] = implode( ', ', $open_channels );
            dt_notification_insert( $notification );
        }

        if ( in_array( 'email', $open_channels, true ) ) {
            // if the user is getting batch emails, schedule an email even if the channel exists in $already_sent
            if ( $email_preference !== 'real-time' ) {
                $notifications_queue = new Disciple_Tools_Notifications_Queue();
                $db_notification = self::get_notification( $notification );
                if ( $db_notification ) {
                    $notifications_queue->schedule_email( $db_notification, $email_preference );
                }
            } elseif ( !in_array( 'email', $already_sent ) ) {
                    $message_plain_text = wp_specialchars_decode( $message, ENT_QUOTES );
                    dt_send_email_about_post( $user->user_email, $notification["post_id"], $message_plain_text );
            }
        }
    }

    /**
     * Insert notification for share
     *
     * @param int $user_id
     * @param int $post_id
     */
    public static function insert_notification_for_share( int $user_id, int $post_id ) {

        if ( $user_id != get_current_user_id() ) { // check if share is not to self, else don't notify

            $args = [
                'user_id'             => $user_id,
                'source_user_id'      => get_current_user_id(),
                'post_id'             => $post_id,
                'secondary_item_id'   => 0,
                'notification_name'   => 'share',
                'notification_action' => 'alert',
                'notification_note'   => '<a href="' . home_url( '/' ) . get_post_type( $post_id ) . '/' . $post_id . '" >' . strip_tags( get_the_title( $post_id ) ) . '</a> was shared with you.',
                'date_notified'       => current_time( 'mysql' ),
                'is_new'              => 1,
                'field_key'           => "comments",
                'field_value'         => ''
            ];

            do_action( 'send_notification_on_channels', $user_id, $args, 'share', [] );
        }
    }

    /**
     * Insert notification for subassigned
     *
     * @param int $user_id
     * @param int $post_id
     */
    public static function insert_notification_for_subassigned( int $user_id, int $post_id ) {

        if ( $user_id != get_current_user_id() ) { // check if subassigned is not to self, else don't notify

            $args = [
                'user_id'             => $user_id,
                'source_user_id'      => get_current_user_id(),
                'post_id'             => $post_id,
                'secondary_item_id'   => 0,
                'notification_name'   => 'subassigned',
                'notification_action' => 'alert',
                'notification_note'   => '<a href="' . home_url( '/' ) . get_post_type( $post_id ) . '/' . $post_id . '" >' . strip_tags( get_the_title( $post_id ) ) . '</a> was sub-assigned to you.',
                'date_notified'       => current_time( 'mysql' ),
                'is_new'              => 1,
                'field_key'           => "comments",
                'field_value'         => ''
            ];

            do_action( 'send_notification_on_channels', $user_id, $args, 'subassigned', [] );
        }
    }

    /**
     * Insert notification for assignment_declined
     *
     * @param int $user_who_declined
     * @param int $new_assigned_to
     * @param int $post_id
     */
    public static function insert_notification_for_assignment_declined( int $user_who_declined, int $new_assigned_to, int $post_id ) {

        $user_id = get_current_user_id();
        if ( $new_assigned_to != $user_id ) { // check if assignment_declined is not to self, else don't notify

            $args = [
                'user_id'             => $new_assigned_to,
                'source_user_id'      => $user_id,
                'post_id'             => $post_id,
                'secondary_item_id'   => $user_who_declined,
                'notification_name'   => 'assignment_declined',
                'notification_action' => 'alert',
                'notification_note'   => '',
                'date_notified'       => current_time( 'mysql' ),
                'is_new'              => 1,
                'field_key'           => "",
                'field_value'         => ''
            ];

            do_action( 'send_notification_on_channels', $user_id, $args, 'assignment_declined', [] );
        }
    }

    public static function insert_notification_for_new_post( $post_type, $fields, $post_id ){
        // Don't fire off notifications when the contact represents a user.
        if ( $post_type === "contacts" ){
            $contact_type = get_post_meta( $post_id, "type", true );
            if ($contact_type === "user"){
                return;
            }
        }

        if ( isset( $fields["assigned_to"] ) ){
            $user_id = dt_get_user_id_from_assigned_to( $fields["assigned_to"] );
            if ( $user_id && $user_id != get_current_user_id()){
                $notification = [
                    'user_id'             => $user_id,
                    'source_user_id'      => get_current_user_id(),
                    'post_id'             => (int) $post_id,
                    'secondary_item_id'   => '',
                    'notification_name'   => 'created',
                    'notification_action' => 'alert',
                    'notification_note'   => '',
                    'date_notified'       => current_time( 'mysql' ),
                    'is_new'              => 1,
                    'field_key'           => $post_type,
                    'field_value'         => '',
                ];

                do_action( 'send_notification_on_channels', $user_id, $notification, 'new_assigned', [] );
            }
        }
    }

    public static function insert_notification_for_post_update( $post_type, $fields, $old_fields, $fields_changed_keys ){
        if ( isset( $fields["type"] ) && $fields["type"] === "user"){
            return;
        }
        $notification_on_fields = [];
        //determine the fields that need a notification
        foreach ( $fields_changed_keys as $key ){
            if ( $key === "assigned_to" && isset( $fields["assigned_to"] ) &&
                ( !isset( $old_fields["assigned_to"] ) || $old_fields["assigned_to"] != $fields["assigned_to"] ) ){
                $notification_on_fields[] = "assigned_to";
            } elseif ( $key === "requires_update" && isset( $fields["requires_update"] ) &&
                ( $fields["requires_update"] === true || $fields["requires_update"] === '1' ) &&
                ( !isset( $old_fields["requires_update"] ) || $old_fields["requires_update"] != $fields["requires_update"] ) ){
                $notification_on_fields[] = "requires_update";
            } elseif ( strpos( $key, "contact_" ) === 0 && isset( $fields[$key] ) &&
                ( !isset( $old_fields[$key] ) || $old_fields[$key] != $fields[$key] )){
                $notification_on_fields[] = "contact_info_update";
            } elseif ( $key === "milestones" && isset( $fields[$key] ) && sizeof( $fields[$key] ) > sizeof( $old_fields[$key] ?? [] ) ){
                $notification_on_fields[] = "milestone";
            } elseif ( $key === "health_metrics" && isset( $fields[$key] ) && sizeof( $fields[$key] ) > sizeof( $old_fields[$key] ?? [] ) ){
                $notification_on_fields[] = "milestone";
            }
        }

        if ( sizeof( $notification_on_fields ) > 0 ){
            $source_user_id = get_current_user_id();
            $followers = DT_Posts::get_users_following_post( $post_type, $fields["ID"] );
            $subassigned = $post_type === "contacts" ? Disciple_Tools_Posts::get_subassigned_users( $fields["ID"] ) : [];
            $assigned_to = isset( $fields["assigned_to"]["id"] ) ? $fields["assigned_to"]["id"] : false;
            foreach ( $followers as $follower ){
                $email = "";
                if ( $follower != $source_user_id ){
                    $user_meta = get_user_meta( $follower );
                    if ( $user_meta ) {
                        $notification = [
                            'user_id'             => $follower,
                            'source_user_id'      => $source_user_id,
                            'post_id'             => (int) $fields["ID"],
                            'secondary_item_id'   => '',
                            'notification_name'   => 'mention',
                            'notification_action' => 'alert',
                            'notification_note'   => '',
                            'date_notified'       => current_time( 'mysql' ),
                            'is_new'              => 1,
                            'field_key'           => '',
                            'field_value'         => '',
                        ];

                        if ( in_array( "assigned_to", $notification_on_fields ) ) {
                            if ( $assigned_to ) {
                                if ( (int) $follower === (int) $assigned_to ) {
                                    $notification["notification_name"] = "assigned_to";
                                    $notification_type                 = 'new_assigned';
                                    if ( dt_user_notification_is_enabled( $notification_type, 'email', $user_meta, $follower ) ) {
                                        $email .= self::get_notification_message_html( $notification ) . "\n";
                                    }
                                    do_action( 'send_notification_on_channels', $follower, $notification, $notification_type, [ 'email' ] );
                                } else {
                                    $notification["notification_name"] = "assigned_to_other";
                                    $notification['field_key'] = "assigned_to";
                                    $notification['field_value'] = $fields["assigned_to"]["id"];
                                    $notification_type                 = 'changes';
                                    if ( dt_user_notification_is_enabled( $notification_type, 'email', $user_meta, $follower ) ) {
                                        $email .= self::get_notification_message_html( $notification ) . "\n";
                                    }
                                    do_action( 'send_notification_on_channels', $follower, $notification, $notification_type, [ 'email' ] );
                                }
                            }
                        }
                        if ( in_array( "requires_update", $notification_on_fields ) ) {
                            if ( (int) $follower === (int) $assigned_to || in_array( $follower, $subassigned ) ) {
                                $notification["notification_name"] = "requires_update";
                                $notification_type                 = 'updates';
                                if ( dt_user_notification_is_enabled( $notification_type, 'email', $user_meta, $follower ) ) {
                                    $email .= self::get_notification_message_html( $notification ) . "\n";
                                }
                                do_action( 'send_notification_on_channels', $follower, $notification, $notification_type, [ 'email' ] );
                            }
                        }
                        if ( in_array( "milestone", $notification_on_fields ) ) {
                            $notification["notification_name"] = "milestone";
                            $notification_type                 = 'milestones';
                            $notification["field_key"] = "milestones";
                            $diff = array_diff( $fields["milestones"] ?? [], $old_fields["milestones"] ?? [] );
                            foreach ( $diff as $k => $v ){
                                $notification["field_value"] = $v;
                            }
                            if ( dt_user_notification_is_enabled( $notification_type, 'email', $user_meta, $follower ) ) {
                                $email .= self::get_notification_message_html( $notification ) . "\n";
                            }
                            do_action( 'send_notification_on_channels', $follower, $notification, $notification_type, [ 'email' ] );
                        }
                        if ( in_array( "contact_info_update", $notification_on_fields ) ) {
                            $notification["notification_name"] = "contact_info_update";
                            $notification_type                 = 'changes';
                            if ( dt_user_notification_is_enabled( $notification_type, 'email', $user_meta, $follower ) ) {
                                $email .= self::get_notification_message_html( $notification ) . "\n";
                            }
                            do_action( 'send_notification_on_channels', $follower, $notification, $notification_type, [ 'email' ] );
                        }

                        $email_preference = get_user_meta( $follower, 'email_preference', true );
                        if ( $email && $email_preference === 'real-time' ) {
                            $user = get_userdata( $follower );
                            $message_plain_text = wp_specialchars_decode( $email, ENT_QUOTES );
                            dt_send_email_about_post(
                                $user->user_email,
                                $fields["ID"],
                                $message_plain_text
                            );
                        }
                    }
                }
            }
        }
    }

    /**
     * Process post notifications for a user who has visited the post. This removes the new status for all notifications for this post
     *
     * @param $post_id
     */
    public static function process_new_notifications( $post_id ) {
        global $wpdb;
        $user_id = get_current_user_id();

        // change new notifications to viewed
        $wpdb->update(
            $wpdb->dt_notifications,
            [
                'is_new' => 0,
            ],
            [
                'post_id' => $post_id,
                'user_id' => $user_id,
            ],
            [
                '%d'
            ],
            [
                '%d',
                '%d'
            ]
        );

    }


    /**
     * Format comment replacing @mentions with a readable value
     *
     * @param $comment_content
     *
     * @return mixed
     */
    public static function format_comment( $comment_content ){
        preg_match_all( '/\@\[(.*?)\]\((.+?)\)/', $comment_content, $matches );
        foreach ( $matches[0] as $match_key => $match ){
            $comment_content = str_replace( $match, '@' . $matches[1][$match_key], $comment_content );
        }
        return $comment_content;
    }


    /**
     * @param $notification
     *
     * @param bool $html
     * @return string $notification_note the return value is expected to contain HTML.
     */
    public static function get_notification_message_html( $notification, $html = true, $condensed = false ){
        // load the local for the destination usr so emails are sent our correctly.
        $destination_user = get_user_by( 'id', $notification["user_id"] );

        $destination_user_locale = !empty( $destination_user->locale ) ? $destination_user->locale : Disciple_Tools::instance()->site_locale;

        add_filter( "determine_locale", function ( $locale ) use ( $destination_user_locale ) {
            if ( $destination_user_locale ){
                $locale = $destination_user_locale;
            }
            return $locale;
        }, 10, 1 );
        //make sure correct translation is loaded for destination user.
        unload_textdomain( "disciple_tools" );
        load_theme_textdomain( 'disciple_tools', get_template_directory() . '/dt-assets/translation' );

        $object_id = $notification["post_id"];
        $post = get_post( $object_id );
        $post_title = isset( $post->post_title ) ? sanitize_text_field( $post->post_title ) : "";
        $notification_note = $notification["notification_note"]; // $notification_note is expected to contain HTML
        $vertical_spacing = $condensed ? "\r\n" : "\r\n\r\n";
        if ( $html ){
            $link = '<a href="' . home_url( '/' ) . get_post_type( $object_id ) . '/' . $object_id . '">' . $post_title . '</a>';
        } else {
            $link = $post_title;
        }
        if ( $notification["notification_name"] === "created" ) {
            $notification_note = sprintf( esc_html_x( '%s was created and assigned to you.', '%s was created and assigned to you.', 'disciple_tools' ), $link );
        } elseif ( $notification["notification_name"] === "assigned_to" ) {
            $notification_note = sprintf( esc_html_x( 'You have been assigned: %1$s.', 'You have been assigned: contact1.', 'disciple_tools' ), $link );
        } elseif ( $notification["notification_name"] === "assigned_to_other" ) {
            $source_user = get_userdata( $notification["source_user_id"] );
            $source_user_name = $source_user ? "@" . $source_user->display_name : '';
            $new_assigned = get_userdata( $notification["field_value"] );
            $new_assigned_name = $new_assigned ? '@' . $new_assigned->display_name : '';
            $notification_note = sprintf( esc_html_x( '%1$s assigned %2$s to %3$s.', 'user1 assigned contact1 to user2.', 'disciple_tools' ), $source_user_name, $link, $new_assigned_name );
        } elseif ( $notification["notification_name"] ==="share" ){
            $source_user = get_userdata( $notification["source_user_id"] );
            $display_name = $source_user ? $source_user->display_name : __( "System", "disciple_tools" );
            $notification_note = sprintf( esc_html_x( '%1$s shared %2$s with you.', 'User1 shared contact1 with you.', 'disciple_tools' ), $display_name, $link );
        } elseif ( $notification["notification_name"] ==="mention" ){
            $source_user = get_userdata( $notification["source_user_id"] );
            $comment = get_comment( $notification["secondary_item_id"] );
            $comment_content = $comment ? self::format_comment( $comment->comment_content ) : "";
            $comment_content = $vertical_spacing . ' ' . $comment_content;
            $display_name = $source_user ? $source_user->display_name : __( "System", "disciple_tools" );
            $notification_note = sprintf( esc_html_x( '%1$s mentioned you on %2$s saying: %3$s', 'User1 mentioned you on contact1 saying: test', 'disciple_tools' ), $display_name, $link, $comment_content );
        } elseif ( $notification["notification_name"] ==="comment" ){
            $source_user = get_userdata( $notification["source_user_id"] );
            $comment = get_comment( $notification["secondary_item_id"] );
            $comment_content = $comment ? self::format_comment( $comment->comment_content ) : "";
            $comment_content = $vertical_spacing . ' '  . $comment_content;
            $display_name = $source_user ? $source_user->display_name : ( $comment->comment_author ?: __( "System", "disciple_tools" ) );
            $notification_note = sprintf( esc_html_x( '%1$s commented on %2$s saying: %3$s', 'User1 commented on contact1 saying: test', 'disciple_tools' ), $display_name, $link, $comment_content );
        } elseif ( $notification["notification_name"] === "subassigned" ){
            $source_user = get_userdata( $notification["source_user_id"] );
            $display_name = $source_user ? $source_user->display_name : __( "System", "disciple_tools" );
            $notification_note = sprintf( esc_html_x( '%1$s subassigned %2$s to you.', 'User1 subassigned contact1 to you.', 'disciple_tools' ), $display_name, $link );
        } elseif ( $notification["notification_name"] ==="milestone" ){
            $meta_value = $notification["field_value"] ?? '';
            $contact_fields = DT_Posts::get_post_field_settings( "contacts", false ); //no cache to get the labels in the correct language
            $label = $meta_value;
            if ( isset( $contact_fields["milestones"]["default"][$meta_value]["label"] ) ){
                $label = $contact_fields["milestones"]["default"][$meta_value]["label"];
            }
            $source_user = get_userdata( $notification["source_user_id"] );
            $display_name = $source_user ? $source_user->display_name : __( "System", "disciple_tools" );
            $notification_note = sprintf( esc_html_x( '%1$s added milestone %2$s on %3$s.', 'User1 added milestone Baptizing on contact1.', 'disciple_tools' ), $display_name, $label, $link );
        } elseif ( $notification["notification_name"] ==="requires_update" ) {
            $assigned_to = dt_get_assigned_name( $notification["post_id"], true );
            $notification_note = $notification_note = sprintf( esc_html_x( '@%1$s, an update is requested on %2$s.', '@Multiplier1, an update is requested on contact1.', 'disciple_tools' ), $assigned_to, $link );
        } elseif ( $notification["notification_name"] ==="contact_info_update" ){
            $source_user = get_userdata( $notification["source_user_id"] );
            $display_name = $source_user ? $source_user->display_name : __( "System", "disciple_tools" );
            $notification_note = sprintf( esc_html_x( '%1$s modified contact details on %2$s.', 'User1 modified contact details on contact1.', 'disciple_tools' ), $display_name, $link );
        } elseif ( $notification["notification_name"] === "assignment_declined" ){
            $user_who_declined = get_userdata( $notification["source_user_id"] );
            $notification_note = sprintf( esc_html_x( '%1$s declined assignment on: %2$s.', 'User1 declined assignment on: contact1', 'disciple_tools' ), $user_who_declined->display_name, $link );
        } else {
            $notification_note = apply_filters( 'dt_custom_notification_note', '', $notification );
        }
        return $notification_note;
    }
}
