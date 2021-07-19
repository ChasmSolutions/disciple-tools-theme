<?php

/**
 * Disciple_Tools_Metrics
 *
 * @class      Disciple_Tools_Metrics
 * @version    0.1.0
 * @since      0.1.0
 * @package    Disciple.Tools
 * @author     Disciple.Tools
 */

if ( !defined( 'ABSPATH' ) ){
    exit; // Exit if accessed directly
}

/**
 * Class Disciple_Tools_Admin_Menus
 */
class Disciple_Tools_Metrics{


    private static $_instance = null;

    public static function instance(){
        if ( is_null( self::$_instance ) ){
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct(){
        $url_path = dt_get_url_path();
        if ( strpos( $url_path, "metrics" ) !== false ){
            // wait for D.T post type classes to be set up before building metrics for them
            add_action( 'after_setup_theme', function (){
                $modules = dt_get_option( "dt_post_type_modules" );
                // Personal
                require_once( get_template_directory() . '/dt-metrics/personal/coaching-tree.php' );
                require_once( get_template_directory() . '/dt-metrics/personal/baptism-tree.php' );
                require_once( get_template_directory() . '/dt-metrics/personal/group-tree.php' );

                require_once( get_template_directory() . '/dt-metrics/personal/mapbox-contacts-maps.php' );
                require_once( get_template_directory() . '/dt-metrics/personal/mapbox-groups-maps.php' );
                require_once( get_template_directory() . '/dt-metrics/personal/overview.php' );

                require_once( get_template_directory() . '/dt-metrics/combined/mapbox-maps.php' );

                if ( dt_has_permissions( [ 'dt_all_access_contacts', 'view_project_metrics' ] ) ){ // tests if project level permissions
                    /* Contacts */
                    if ( !empty( $modules["dmm_module"]["enabled"] ) ){
                        require_once( get_template_directory() . '/dt-metrics/contacts/baptism-tree.php' );
                        require_once( get_template_directory() . '/dt-metrics/contacts/coaching-tree.php' );
                        require_once( get_template_directory() . '/dt-metrics/contacts/milestones.php' );
                        require_once( get_template_directory() . '/dt-metrics/contacts/milestones-map.php' );
                    }
                    if ( !empty( $modules["access_module"]["enabled"] ) ){
                        require_once( get_template_directory() . '/dt-metrics/contacts/mapbox-maps.php' );
                        require_once( get_template_directory() . '/dt-metrics/contacts/sources.php' );
                        require_once( get_template_directory() . '/dt-metrics/contacts/overview.php' );
                    }

                    /* Groups */
                    require_once( get_template_directory() . '/dt-metrics/groups/tree.php' );
                    require_once( get_template_directory() . '/dt-metrics/groups/mapbox-maps.php' );
                    require_once( get_template_directory() . '/dt-metrics/groups/overview.php' );

                    // Combined
                    require_once( get_template_directory() . '/dt-metrics/combined/daily-activity.php' );
                    require_once( get_template_directory() . '/dt-metrics/combined/locations-list.php' );
                    require_once( get_template_directory() . '/dt-metrics/combined/hover-map.php' );
                    if ( !empty( $modules["access_module"]["enabled"] ) ){
                        require_once( get_template_directory() . '/dt-metrics/combined/critical-path.php' );
                        require_once( get_template_directory() . '/dt-metrics/combined/time-charts.php' );
                    }
                }
            }, 1000);

            // default menu order
            add_filter( 'dt_metrics_menu', function ( $content ){
                $modules = dt_get_option( "dt_post_type_modules" );
                if ( $content === "" ){
                    $content .= '<li><a>' . __( "Personal", "disciple_tools" ) . '</a>
                                <ul class="menu vertical nested" id="personal-menu"></ul>
                            </li>';

                    if ( dt_has_permissions( [ 'dt_all_access_contacts', 'view_project_metrics' ] ) ){
                        $content .= '<li><a>' . __( "Project", "disciple_tools" ) . '</a>
                                <ul class="menu vertical nested" id="combined-menu"></ul>
                            </li>';
                        if ( !empty( $modules["dmm_module"]["enabled"] ) ){
                            $content .= '<li><a>' . __( "Contacts", "disciple_tools" ) . '</a>
                                <ul class="menu vertical nested" id="contacts-menu"></ul>
                            </li>';
                        }
                        $content .= '<li><a>' . __( "Groups", "disciple_tools" ) . '</a>
                                <ul class="menu vertical nested" id="groups-menu"></ul>
                            </li>';
                    } // permission check
                }
                return $content;
            }, 10 ); //load menu links

        }

        /**
         * Add Navigation Menu
         */
        if ( user_can( get_current_user_id(), 'access_contacts' ) ) {
            add_filter( 'desktop_navbar_menu_options', function ( $tabs ){
                $tabs['metrics'] = [
                    "link" => site_url( '/metrics/' ),
                    "label" => __( "Metrics", "disciple_tools" )
                ];
                return $tabs;
            }, 25 );
        }

    }
}


Disciple_Tools_Metrics::instance();

