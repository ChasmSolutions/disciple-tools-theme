<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

class DT_Metrics_Daily_Activity extends DT_Metrics_Chart_Base {

    //slug and title of the top menu folder
    public $base_slug = 'combined'; // lowercase
    public $base_title;
    public $title;
    public $slug = 'daily-activity'; // lowercase
    public $js_object_name = 'wp_js_object'; // This object will be loaded into the metrics.js file by the wp_localize_script.
    public $js_file_name = '/dt-metrics/combined/daily-activity.js'; // should be full file name plus extension
    public $permissions = [ 'dt_all_access_contacts', 'view_project_metrics' ];

    public function __construct() {
        parent::__construct();
        if ( ! $this->has_permission() ) {
            return;
        }
        $this->title      = __( 'Activity by Day', 'disciple_tools' );
        $this->base_title = __( 'Project', 'disciple_tools' );

        $url_path = dt_get_url_path();
        if ( "metrics/$this->base_slug/$this->slug" === $url_path ) {
            add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ], 99 );
        }
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
    }

    /**
     * Load scripts for the plugin
     */
    public function scripts() {

        wp_register_script( 'datepicker', 'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js', false );
        wp_enqueue_style( 'datepicker-css', 'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css', array() );

        wp_register_script( 'amcharts-core', 'https://cdn.amcharts.com/lib/4/core.js', false, '4' );
        wp_register_script( 'amcharts-charts', 'https://cdn.amcharts.com/lib/4/charts.js', false, '4' );
        wp_register_script( 'amcharts-plugins-timeline', 'https://cdn.amcharts.com/lib/4/plugins/timeline.js', false, '4' );
        wp_register_script( 'amcharts-plugins-bullets', 'https://cdn.amcharts.com/lib/4/plugins/bullets.js', false, '4' );
        wp_register_script( 'amcharts-themes-animated', 'https://cdn.amcharts.com/lib/4/themes/animated.js', false, '4' );

        wp_enqueue_script( 'dt_' . $this->slug . '_script',
            get_template_directory_uri() . $this->js_file_name,
            [
                'moment',
                'jquery',
                'jquery-ui-core',
                'datepicker',
                'amcharts-core',
                'amcharts-charts',
                'amcharts-plugins-timeline',
                'amcharts-plugins-bullets',
                'amcharts-themes-animated'
            ],
            filemtime( get_theme_file_path() . $this->js_file_name )
        );

        // Localize script with array data
        wp_localize_script(
            'dt_' . $this->slug . '_script', $this->js_object_name, [
                'rest_endpoints_base' => esc_url_raw( rest_url() ) . "dt-metrics/$this->base_slug/$this->slug",
                "data"                => [
                    'activities' => $this->daily_activity( 'this-month' )
                ],
                'translations'        => [
                    'activities'           => __( "Activity by Day", 'disciple_tools' ),
                    'filter_to_date_range' => __( "Filter to date range", 'disciple_tools' ),
                ]
            ]
        );
    }

    public function add_api_routes() {
        $namespace = "dt-metrics/$this->base_slug/$this->slug";
        register_rest_route(
            $namespace, '/daily-activity/', [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'daily_activity_endpoint' ],
                    'permission_callback' => '__return_true',
                ],
            ]
        );
    }

    public function daily_activity_endpoint( WP_REST_Request $request ) {
        if ( ! $this->has_permission() ) {
            return new WP_Error( "daily-activity", "Missing Permissions", [ 'status' => 400 ] );
        }
        $params = $request->get_params();
        if ( isset( $params["date_range"] ) ) {
            $result = $this->daily_activity( $params["date_range"] );
            if ( is_wp_error( $result ) ) {
                return $result;
            } else {
                return new WP_REST_Response( $result );
            }
        } else {
            return new WP_Error( "daily-activity", "Missing required parameters", [ 'status' => 400 ] );
        }
    }


    public function daily_activity( $date_range ): array {

        // First, identify date range boundaries.
        $start = null;
        $end   = null;

        switch ( $date_range ) {
            case 'this-month':
                $start = gmdate( 'Y-m-01' );
                $end   = gmdate( 'Y-m-d', strtotime( '+3 day' ) );
                break;
            case 'last-month':
                $base_ts = strtotime( '-1 month' );
                $start   = gmdate( 'Y-m-01', $base_ts );
                $end     = gmdate( 'Y-m-d', strtotime( gmdate( 'Y-m-t', $base_ts ) . ' +3 day' ) );
                break;
            case '2-months-ago':
                $base_ts = strtotime( '-2 months' );
                $start   = gmdate( 'Y-m-01', $base_ts );
                $end     = gmdate( 'Y-m-d', strtotime( gmdate( 'Y-m-t', $base_ts ) . ' +3 day' ) );
                break;
            case '3-months-ago':
                $base_ts = strtotime( '-3 months' );
                $start   = gmdate( 'Y-m-01', $base_ts );
                $end     = gmdate( 'Y-m-d', strtotime( gmdate( 'Y-m-t', $base_ts ) . ' +3 day' ) );
                break;
            case '4-months-ago':
                $base_ts = strtotime( '-4 months' );
                $start   = gmdate( 'Y-m-01', $base_ts );
                $end     = gmdate( 'Y-m-d', strtotime( gmdate( 'Y-m-t', $base_ts ) . ' +3 day' ) );
                break;
            case '5-months-ago':
                $base_ts = strtotime( '-5 months' );
                $start   = gmdate( 'Y-m-01', $base_ts );
                $end     = gmdate( 'Y-m-d', strtotime( gmdate( 'Y-m-t', $base_ts ) . ' +3 day' ) );
                break;
            case '6-months-ago':
                $base_ts = strtotime( '-6 months' );
                $start   = gmdate( 'Y-m-01', $base_ts );
                $end     = gmdate( 'Y-m-d', strtotime( gmdate( 'Y-m-t', $base_ts ) . ' +3 day' ) );
                break;
        }

        if ( ! empty( $start ) && ! empty( $end ) ) {

            $days = null;

            try {

                // Convert daily intervals into array elements.
                $days = new DatePeriod(
                    new DateTime( $start ),
                    new DateInterval( 'P1D' ),
                    new DateTime( $end )
                );

            } catch ( Exception $e ) {
                $days = null;
            }

            if ( ! empty( $days ) ) {

                // Fetch field settings
                $contact_field_settings = DT_Posts::get_post_field_settings( "contacts" );
                $group_fields_settings  = DT_Posts::get_post_field_settings( "groups" );

                // Cycle through each day with range and obtain required metric counts
                $daily_activities = [];

                // Iterate each day, sourcing relevant counts.
                foreach ( $days as $day ) {

                    $current_day_format = $day->format( 'Y-m-d' );
                    $next_day_format    = gmdate( 'Y-m-d', strtotime( '+1 day', $day->getTimestamp() ) );

                    $current_day_ts = strtotime( $current_day_format );
                    $next_day_ts    = strtotime( $next_day_format );

                    $new_contacts        = Disciple_Tools_Counter_Contacts::get_contacts_count( 'new_contacts', $current_day_ts, $next_day_ts );
                    $new_groups          = $this->new_groups_count( $current_day_format, $next_day_format );
                    $seeker_path_updates = Disciple_Tools_Counter_Contacts::seeker_path_activity( $current_day_ts, $next_day_ts );
                    $health              = $this->health_metrics( $current_day_ts, $next_day_ts, $group_fields_settings );

                    $multiselect_fields = [];
                    foreach ( $contact_field_settings as $field_key => $field_settings ) {
                        if ( isset( $field_settings['type'] ) && ( $field_settings['type'] === 'multi_select' ) && in_array( $field_key, [ 'milestones' ] ) ) {
                            $metrics = $this->multiselect_field_metrics( $current_day_ts, $next_day_ts, $field_key );
                            if ( ! empty( $metrics ) ) {
                                foreach ( $metrics as $metric ) {
                                    $multiselect_fields[ $field_settings['name'] ][] = [
                                        'label' => $field_settings['default'][ $metric->label ]['label'] ?? $metric->label,
                                        'value' => $metric->value
                                    ];
                                }
                            }
                        }
                    }

                    // Package counts
                    $daily_activities[ $current_day_format ] = [
                        'new_contacts'        => $new_contacts,
                        'new_groups'          => $new_groups,
                        'seeker_path_updates' => $seeker_path_updates,
                        'health'              => $health,
                        'multiselect_fields'  => $multiselect_fields
                    ];
                }

                return [
                    'start' => $start,
                    'end'   => $end,
                    'days'  => $daily_activities
                ];
            }
        }

        return [];
    }

    private function health_metrics( $start, $end, $group_fields ): array {
        global $wpdb;

        $labels = [];
        foreach ( $group_fields["health_metrics"]["default"] as $key => $option ) {
            $labels[ $key ] = $option["label"];
        }

        $chart = [];

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT d.meta_value as health_key,
              count(distinct(a.ID)) as count,
              ( SELECT count(*)
              FROM $wpdb->posts as a
                JOIN $wpdb->postmeta as c
                  ON a.ID=c.post_id
                     AND c.meta_key = 'group_status'
                     AND c.meta_value = 'active'
                JOIN $wpdb->postmeta as d
                  ON a.ID=d.post_id
                     AND d.meta_key = 'group_type'
                     AND ( d.meta_value = 'group' OR d.meta_value = 'church' )
              WHERE a.post_status = 'publish'
                    AND a.post_type = 'groups'
              ) as out_of
              FROM $wpdb->posts as a
                JOIN $wpdb->postmeta as c
                  ON a.ID=c.post_id
                     AND c.meta_key = 'group_status'
                     AND c.meta_value = 'active'
                JOIN $wpdb->postmeta as d
                  ON ( a.ID=d.post_id
                    AND d.meta_key = 'health_metrics' )
                JOIN $wpdb->postmeta as e
                  ON a.ID=e.post_id
                     AND e.meta_key = 'group_type'
                     AND ( e.meta_value = 'group' OR e.meta_value = 'church' )

                JOIN $wpdb->dt_activity_log f
                    ON a.ID = f.object_id
                        AND f.object_type = 'groups'
                        AND f.meta_key = 'health_metrics'
                        AND f.hist_time BETWEEN %d AND %d

              WHERE a.post_status = 'publish'
                    AND a.post_type = 'groups'
              GROUP BY d.meta_value", $start, $end ), ARRAY_A );

        if ( $results ) {
            $out_of = 0;
            if ( isset( $results[0]['out_of'] ) ) {
                $out_of = $results[0]['out_of'];
            }
            foreach ( $labels as $label_key => $label_value ) {
                $row = [
                    "label"      => $label_value,
                    "practicing" => 0,
                    "remaining"  => (int) $out_of
                ];
                foreach ( $results as $result ) {
                    if ( $result['health_key'] === $label_key ) {
                        $row["practicing"] = (int) $result["count"];
                        $row["remaining"]  = intval( $result['out_of'] ) - intval( $result['count'] );
                    }
                }
                $chart[] = $row;
            }
        }

        return $chart;
    }

    private function multiselect_field_metrics( $start, $end, $field_name ): array {
        global $wpdb;

        return $wpdb->get_results( $wpdb->prepare( "
        SELECT COUNT( DISTINCT(log.object_id) ) as `value`, log.meta_value as `label`
        FROM $wpdb->dt_activity_log log
        INNER JOIN $wpdb->postmeta as type ON ( log.object_id = type.post_id AND type.meta_key = 'type' AND type.meta_value != 'user' )
        INNER JOIN $wpdb->posts post
        ON (
            post.ID = log.object_id
            AND post.post_type = 'contacts'
            AND post.post_status = 'publish'
        )
        INNER JOIN $wpdb->postmeta pm
        ON (
            pm.post_id = post.ID
            AND pm.meta_key = %s
            AND pm.meta_value = log.meta_value
        )
        WHERE log.meta_key = %s
        AND log.object_type = 'contacts'
        AND log.hist_time BETWEEN %d AND %d
        GROUP BY log.meta_value
        ", $field_name, $field_name, $start, $end ) );

    }

    private function new_groups_count( $start, $end ): int {
        global $wpdb;

        return $wpdb->get_var( $wpdb->prepare( "
        SELECT COUNT(ID) as count
        FROM $wpdb->posts
        WHERE post_type = 'groups'
          AND post_status = 'publish'
          AND post_date BETWEEN %s AND %s
          AND ID NOT IN (
            SELECT post_id
            FROM $wpdb->postmeta
            WHERE meta_key = 'type' AND  meta_value = 'user'
            GROUP BY post_id)
        ", $start, $end ) );
    }
}

new DT_Metrics_Daily_Activity();
