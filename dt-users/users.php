<?php
/**
 * Contains create, update and delete functions for users, wrapping access to the database
 *
 * @package  Disciple.Tools
 * @category Plugin
 * @author   Disciple.Tools
 * @since    0.1.0
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

/**
 * Class Disciple_Tools_Users
 * Functions for creating, finding, updating or deleting contacts
 */
class Disciple_Tools_Users
{
    /**
     * Disciple_Tools_Users constructor.
     */
    public function __construct() {
        add_action( "dt_contact_merged", [ $this, "dt_contact_merged" ], 10, 2 );

        //Make sure a contact exists for users
        add_action( "wpmu_new_blog", [ &$this, "create_contacts_for_existing_users" ] );
        add_action( "after_switch_theme", [ &$this, "create_contacts_for_existing_users" ] );
        add_action( "invite_user", [ $this, "user_register_hook" ], 10, 1 );
        add_action( "after_signup_user", [ $this, "after_signup_user" ], 10, 2 );
        add_action( 'wp_login', [ &$this, 'user_login_hook' ], 10, 2 );
        add_action( 'add_user_to_blog', [ &$this, 'user_register_hook' ] );
        add_action( 'user_register', [ &$this, 'user_register_hook' ] ); // used on non multisite?
        add_action( 'profile_update', [ &$this, 'profile_update_hook' ], 99 );
        add_action( 'signup_user_meta', [ $this, 'signup_user_meta' ], 10, 1 );
        add_action( 'wpmu_activate_user', [ $this, 'wpmu_activate_user' ], 10, 3 );

        //invite user and edit user page modifications
        add_action( "user_new_form", [ &$this, "custom_user_profile_fields" ] );
        add_action( "show_user_profile", [ &$this, "custom_user_profile_fields" ] );
        add_action( "edit_user_profile", [ &$this, "custom_user_profile_fields" ] );
        add_action( "add_user_role", [ $this, "add_user_role" ], 10, 2 );


        //wp admin user list customization
        add_filter( 'user_row_actions', [ $this, 'dt_edit_user_row_actions' ], 10, 2 );
        add_filter( 'manage_users_columns', [ $this, 'new_modify_user_table' ] );
        add_filter( 'manage_users_custom_column', [ $this, 'new_modify_user_table_row' ], 10, 3 );

        add_filter( 'dt_settings_js_data', [ $this, 'add_current_locations_list' ], 10, 1 );
        add_filter( 'dt_settings_js_data', [ $this, 'add_date_availability' ], 10, 1 );

        add_action( 'delete_user', [ $this,'dt_delete_user_contact_meta' ], 10, 1 );

        add_action( 'remove_user_from_blog', [ $this,'dt_delete_user_contact_meta' ], 10, 1 );
        add_action( 'wpmu_delete_user', [ $this,'dt_multisite_delete_user_contact_meta' ], 10, 1 );


    }

    /**
     * Get assignable users
     *
     * @param  $search_string
     *
     * @return array|\WP_Error
     */
    public static function get_assignable_users_compact( string $search_string = null, $get_all = false ) {
        if ( !current_user_can( "access_contacts" ) ) {
            return new WP_Error( __FUNCTION__, __( "No permissions to assign", 'disciple_tools' ), [ 'status' => 403 ] );
        }

        global $wpdb;
        $user_id = get_current_user_id();
        $users = [];
        $update_needed = [];
        if ( !current_user_can( 'dt_all_access_contacts' ) && !current_user_can( 'dt_list_users' ) ){
            // users that are shared posts that are shared with me
            if ( $search_string ){
                $users_ids = $wpdb->get_results( $wpdb->prepare("
                    SELECT user_id
                    FROM $wpdb->dt_share
                    INNER JOIN $wpdb->users as u ON ( u.ID = user_id AND display_name LIKE %s )
                    WHERE post_id IN (
                          SELECT post_id
                          FROM $wpdb->dt_share
                          WHERE user_id = %s
                    )
                    GROUP BY user_id
                    ",
                    '%' . $search_string .'%',
                    $user_id
                ), ARRAY_N );
            } else {
                $users_ids = $wpdb->get_results( $wpdb->prepare("
                    SELECT user_id
                    FROM $wpdb->dt_share
                    WHERE post_id IN (
                          SELECT post_id
                          FROM $wpdb->dt_share
                          WHERE user_id = %1\$s
                    )
                    GROUP BY user_id
                    ",
                    $user_id
                ), ARRAY_N );

            }

            if ( $search_string ){
                $dispatchers = $wpdb->get_results($wpdb->prepare( "
                    SELECT user_id FROM $wpdb->usermeta um
                    INNER JOIN $wpdb->users u ON ( u.ID = um.user_id AND display_name LIKE %s )
                    WHERE meta_key = '{$wpdb->prefix}capabilities'
                    AND meta_value LIKE %s
                ", '%' . esc_sql( $search_string ) . '%', '%dispatcher%' ) );

            } else {
                $dispatchers = $wpdb->get_results("
                    SELECT user_id FROM $wpdb->usermeta
                    WHERE meta_key = '{$wpdb->prefix}capabilities'
                    AND meta_value LIKE '%dispatcher%'
                ");
            }

            $assure_unique = [];
            foreach ( $dispatchers as $index ){
                $id = $index->user_id;
                if ( $id && !in_array( $id, $assure_unique )){
                    $assure_unique[] = $id;
                    $users[] = get_user_by( "ID", $id );
                }
            }
            foreach ( $users_ids as $index ){
                $id = $index[0];
                if ( $id && !in_array( $id, $assure_unique )){
                    $assure_unique[] = $id;
                    $users[] = get_user_by( "ID", $id );
                }
            }
        } else {
            $correct_roles = dt_multi_role_get_cap_roles( "access_contacts" );
            $search_string = esc_attr( $search_string );
            $user_query = new WP_User_Query( [
                'search'         => '*' . $search_string . '*',
                'search_columns' => [
                    'user_login',
                    'user_nicename',
                    'user_email',
                    'display_name'
                ],
                'role__in' => $correct_roles,
                'number' => $get_all ? 1000 : 50
            ] );

            $users = $user_query->get_results();

            //used cached updated needed data if available
            //@todo refresh the cache if not available
            $dispatcher_user_data = get_transient( 'dispatcher_user_data' );
            if ( $dispatcher_user_data ){
                foreach ( maybe_unserialize( $dispatcher_user_data ) as $uid => $val ){
                    $update_needed['user-' . $uid] = $val["number_update"];
                }
            } else {
                $ids = [];
                foreach ( $users as $a ){
                    $ids[] = 'user-' . $a->ID;
                }
                $user_ids = dt_array_to_sql( $ids );
                //phpcs:disable
                $update_needed_result = $wpdb->get_results("
                    SELECT pm.meta_value, COUNT(update_needed.post_id) as count
                    FROM $wpdb->postmeta pm
                    INNER JOIN $wpdb->postmeta as update_needed on (update_needed.post_id = pm.post_id and update_needed.meta_key = 'requires_update' and update_needed.meta_value = '1' )
                    WHERE pm.meta_key = 'assigned_to' and pm.meta_value IN ( $user_ids )
                    GROUP BY pm.meta_value
                ", ARRAY_A );
                //phpcs:enable
                foreach ( $update_needed_result as $up ){
                    $update_needed[$up["meta_value"]] = $up["count"];
                }
            }
        }
        $list = [];

        $workload_status_options = dt_get_site_custom_lists()["user_workload_status"] ?? [];

        foreach ( $users as $user ) {
            if ( user_can( $user, "access_contacts" ) ) {
                $u = [
                    "name" => $user->display_name,
                    "ID"   => $user->ID,
                    "avatar" => get_avatar_url( $user->ID, [ 'size' => '16' ] ),
                    "contact_id" => self::get_contact_for_user( $user->ID )
                ];
                //extra information for the dispatcher
                if ( current_user_can( 'dt_all_access_contacts' ) && !$get_all ){
                    $workload_status = get_user_option( 'workload_status', $user->ID );
                    if ( $workload_status && isset( $workload_status_options[ $workload_status ]["color"] ) ) {
                        $u['status_color'] = $workload_status_options[$workload_status]["color"];
                    }
                    $u["update_needed"] = $update_needed['user-' . $user->ID] ?? 0;
                }
                $list[] = $u;
            }
        }

        function asc_meth( $a, $b ) {
            $a["name"] = strtolower( $a["name"] );
            $b["name"] = strtolower( $b["name"] );
            return strcmp( $a["name"], $b["name"] );
        }

        $list = apply_filters( 'dt_assignable_users_compact', $list, $search_string, $get_all );

        usort( $list, "asc_meth" );
        return $list;
    }

    /**
     * Switch user preference for notifications and availability meta fields.
     *
     * @param int $user_id
     * @param string $preference_key
     *
     * @param string|null $type
     *
     * @return array
     */
    public static function switch_preference( int $user_id, string $preference_key, string $type = null ) {

        $value = get_user_meta( $user_id, $preference_key, true );

        $label = '';
        $default = false;
        if ( $type === "notifications" ){
            $default = true;
        }

        if ( $value === '' ){
            $status = update_metadata( 'user', $user_id, $preference_key, $default ? '0' : '1' );
            $label = $default ? "false" : "true";
        } elseif ( $value === '0'){
            $status = update_metadata( 'user', $user_id, $preference_key, "1" );
            $label = "true";
        } else {
            $status = update_metadata( 'user', $user_id, $preference_key, '0' );
            $label = "false";
        }

        if ( $status ) {
            return [
                'status'   => true,
                'response' => $status,
            ];
        } else {
            return [
                'status'  => false,
                'message' => 'Unable to update_user_option ' . $preference_key . ' to ' . $label
            ];
        }
    }

    public static function app_switch( int $user_id, string $preference_key ) {

        $value = get_user_option( $preference_key );
        $hash = dt_create_unique_key();

        if ( $value === '' || $value === false || $value === '0' ){
            $status = update_user_option( $user_id, $preference_key, $hash );
            $action = $hash;
        } else {
            $status = delete_user_option( $user_id, $preference_key );
            $action = 'removed';
        }

        if ( $status ) {
            return [
                'status'   => true,
                'response' => $action,
            ];
        } else {
            return [
                'status'  => false,
                'message' => 'Unable to update_user_option.'
            ];
        }
    }

    /**
     * Processes updates posted for current user details.
     */
    public static function update_user_contact_info() {
        global $wpdb;
        $current_user = wp_get_current_user();

        // validate nonce
        if ( isset( $_POST['user_update_nonce'] ) && !wp_verify_nonce( sanitize_key( $_POST['user_update_nonce'] ), 'user_' . $current_user->ID . '_update' ) ) {
            return new WP_Error( 'fail_nonce_verification', 'The form requires a valid nonce, in order to process.' );
        }

        $args = [];
        $args['ID'] = $current_user->ID;

        // build user name variables
        if ( isset( $_POST['first_name'] ) ) {
            $args['first_name'] = sanitize_text_field( wp_unslash( $_POST['first_name'] ) );
        }
        if ( isset( $_POST['last_name'] ) ) {
            $args['last_name'] = sanitize_text_field( wp_unslash( $_POST['last_name'] ) );
        }
        if ( isset( $_POST['user_email'] ) && !empty( $_POST['user_email'] ) ) {
            $args['user_email'] = sanitize_email( wp_unslash( $_POST['user_email'] ) );
        }
        if ( isset( $_POST['description'] ) ) {
            $args['description'] = sanitize_text_field( wp_unslash( $_POST['description'] ) );
        }
        if ( isset( $_POST['nickname'] ) ) {
            $args['nickname'] = sanitize_text_field( wp_unslash( $_POST['nickname'] ) );
        }
        if ( isset( $_POST['display_name'] ) && !empty( $_POST['display_name'] ) ) {
            $args['display_name'] = $args['nickname'];
        }
        //locale
        if ( isset( $_POST['locale'] ) && !empty( $_POST['locale'] ) ) {
            $args['locale'] = sanitize_text_field( wp_unslash( $_POST['locale'] ) );
        } else {
            $args['locale'] = "en_US";
        }
        // _user table defaults
        $result = wp_update_user( $args );

        if ( is_wp_error( $result ) ) {
            return new WP_Error( 'fail_update_user_data', 'Error while updating user data in user table.' );
        }

        // Update custom site fields
        $fields = array_keys( dt_get_site_default_user_fields() );

        foreach ( $fields as $f ) {

            if ( isset( $_POST[ $f ] ) ) {
                ${$f} = trim( sanitize_text_field( wp_unslash( $_POST[ $f ] ) ) );

                if ( get_user_meta( $current_user->ID, $f, true ) == '' ) {
                    update_user_meta( $current_user->ID, $f, ${$f} );
                } elseif ( ${$f} == '' ) {
                    delete_user_meta( $current_user->ID, $f, get_user_meta( $current_user->ID, $f, true ) );
                } elseif ( ${$f} != get_user_meta( $current_user->ID, $f, true ) ) {
                    update_user_meta( $current_user->ID, $f, ${$f} );
                }
            }
        }

        //check that display name is not null and is a new name
        if ( !empty( $args['nickname'] ) && $current_user->display_name != $args['nickname'] ) {
            //set display name to nickname
            $user_id = wp_update_user( array(
                'ID' => (int) $args['ID'],
                'display_name' => $args['nickname']
                )
            );

        }

        return wp_redirect( get_site_url() ."/settings" );
    }


    /**
     * @param $user_id
     * @return int|WP_Error|null the contact ID
     */
    public static function get_contact_for_user( $user_id ){
        if ( !current_user_can( "access_contacts" )){
            return new WP_Error( 'no_permission', __( "Insufficient permissions", 'disciple_tools' ), [ 'status' => 403 ] );
        }
        $contact_id = get_user_option( "corresponds_to_contact", $user_id );

        if ( !empty( $contact_id ) && get_post( $contact_id ) ){
            return (int) $contact_id;
        }
        $args = [
            'post_type'  => 'contacts',
            'relation'   => 'AND',
            'meta_query' => [
                [
                    'key' => "corresponds_to_user",
                    "value" => $user_id
                ],
                [
                    'key' => "type",
                    "value" => "user"
                ],
            ],
        ];
        $contacts = new WP_Query( $args );
        if ( isset( $contacts->post->ID ) ){
            update_user_option( $user_id, "corresponds_to_contact", $contacts->post->ID );
            return (int) $contacts->post->ID;
        } else {
            return null;
        }
    }

    /**
     * Create a Contact for each user that registers
     *
     * @param $user_id
     * @return bool|int|WP_Error
     */
    public static function create_contact_for_user( $user_id ) {
        $user = get_user_by( 'id', $user_id );
        $corresponds_to_contact = get_user_option( "corresponds_to_contact", $user_id );
        if ( $user && $user->has_cap( 'access_contacts' ) && is_user_member_of_blog( $user_id ) ) {
            if ( empty( $corresponds_to_contact )){
                $args = [
                    'post_type'  => 'contacts',
                    'relation'   => 'AND',
                    'meta_query' => [
                        [
                            'key' => "corresponds_to_user",
                            "value" => $user_id
                        ],
                        [
                            'key' => "type",
                            "value" => "user"
                        ],
                    ],
                ];
                $contacts = new WP_Query( $args );
                if ( isset( $contacts->post->ID ) ){
                    $corresponds_to_contact = $contacts->post->ID;
                    update_user_option( $user_id, "corresponds_to_contact", $corresponds_to_contact );
                }
            }
            if ( empty( $corresponds_to_contact )){
                $args = [
                    'post_type'  => 'contacts',
                    'relation'   => 'AND',
                    'meta_query' => [
                        [
                            'key' => "corresponds_to_user_name",
                            "value" => $user->user_login
                        ]
                    ],
                ];
                $contacts = new WP_Query( $args );
                if ( isset( $contacts->post->ID ) ){
                    $corresponds_to_contact = $contacts->post->ID;
                    update_user_option( $user_id, "corresponds_to_contact", $corresponds_to_contact );
                    update_post_meta( $corresponds_to_contact, "corresponds_to_user", $user_id );
                }
            }

            if ( empty( $corresponds_to_contact ) || get_post( $corresponds_to_contact ) === null ) {
                $current_user_id = get_current_user_id();
//                wp_set_current_user( 0 );
                $new_user_contact = DT_Posts::create_post( "contacts", [
                    "title"               => $user->display_name,
                    "type"                => "user",
                    "corresponds_to_user" => $user_id,
                ], true, false );
//                wp_set_current_user( $current_user_id );
                if ( !is_wp_error( $new_user_contact )){
                    update_user_option( $user_id, "corresponds_to_contact", $new_user_contact["ID"] );
                    return $new_user_contact["ID"];
                }
            } else {
                $contact = get_post( $corresponds_to_contact );
                if ( $contact && $contact->post_title != $user->display_name && $user->display_name != $user->user_login ){
                    DT_Posts::update_post( "contacts", $corresponds_to_contact, [
                        "title" => $user->display_name
                    ], false, false );
                }
                return $contact->ID;
            }
        }
        return false;
    }

    /**
     * When a new user is invited to a multisite and the "corresponds_to_contact" field is filled out
     * save the username to the contact to be linked to the user when they activate their account.
     *
     * @param $user_name
     * @param $user_email
     */
    public function after_signup_user( $user_name, $user_email ){
        if ( isset( $_REQUEST['action'] ) && 'createuser' == $_REQUEST['action'] ) {
            check_admin_referer( 'create-user', '_wpnonce_create-user' );
        }
        if ( isset( $_REQUEST['action'] ) && 'adduser' == $_REQUEST['action'] ) {
            check_admin_referer( 'add-user', '_wpnonce_add-user' );
        }
        if ( isset( $_POST["corresponds_to_contact_id"] ) && !empty( $_POST["corresponds_to_contact_id"] ) ) {
            $corresponds_to_contact = sanitize_text_field( wp_unslash( $_POST["corresponds_to_contact_id"] ) );
            update_post_meta( $corresponds_to_contact, 'corresponds_to_user_name', $user_name );
        }
    }

    /*
     * Multisite only
     * Save who added the user so that their contact record can later be shared.
     */
    public function signup_user_meta( $meta ){
        $current_user_id = get_current_user_id();
        if ( $current_user_id ){
            $meta["invited_by"] = $current_user_id;
        }
        return $meta;
    }

    /**
     * Multisite only
     * Share the newly added user-contact with the admin who added the user.
     * @param $user_id
     * @param $password
     * @param $meta
     */
    public function wpmu_activate_user( $user_id, $password, $meta ){
        if ( isset( $meta["invited_by"] ) && !empty( $meta["invited_by"] ) ){
            $contact_id = get_user_option( "corresponds_to_contact", $user_id );
            if ( $contact_id && !is_wp_error( $contact_id )){
                DT_Posts::add_shared( "contacts", $contact_id, $meta["invited_by"], null, false, false );
            }
        }
    }

    /**
     * User register hook
     * Check to see if the user is linked to a contact.
     *
     *  When adding an existing multisite to the D.T instance.
     *  Link the user with the existing contact or create a contact for the user.
     *
     * @param $user_id
     */
    public static function user_register_hook( $user_id ) {
        if ( isset( $_REQUEST['action'] ) && 'createuser' == $_REQUEST['action'] ) {
            check_admin_referer( 'create-user', '_wpnonce_create-user' );
        }
        if ( isset( $_REQUEST['action'] ) && 'adduser' == $_REQUEST['action'] ) {
            check_admin_referer( 'add-user', '_wpnonce_add-user' );
        }
        if ( dt_is_rest() ){
            $data = json_decode( WP_REST_Server::get_raw_data(), true );

            if ( isset( $data["locale"] )) {
                if ( $data["locale"] === "" ) {
                    $locale = "en-US";
                } else {
                    $locale = $data["locale"];
                }
                switch_to_locale( $locale );
            }
            if ( isset( $data["corresponds_to_contact"] ) ){
                $corresponds_to_contact = $data["corresponds_to_contact"];
                update_user_option( $user_id, "corresponds_to_contact", $corresponds_to_contact );
                $contact = DT_Posts::update_post( "contacts", (int) $corresponds_to_contact, [
                    "corresponds_to_user" => $user_id,
                    "type" => "user"
                ], false, true );
                $user = get_user_by( 'id', $user_id );
                $user->display_name = $contact["title"];
                if ( isset( $data["locale"] )) {
                    $user->locale = $locale;
                }
                wp_update_user( $user );
            }
        }
        if ( isset( $_POST["corresponds_to_contact_id"] ) && !empty( $_POST["corresponds_to_contact_id"] ) ) {
            $corresponds_to_contact = sanitize_text_field( wp_unslash( $_POST["corresponds_to_contact_id"] ) );
            update_user_option( $user_id, "corresponds_to_contact", $corresponds_to_contact );
            $contact = DT_Posts::update_post( "contacts", (int) $corresponds_to_contact, [
                "corresponds_to_user" => $user_id,
                "type" => "user"
            ], false, true );
            $user = get_user_by( 'id', $user_id );
            $user->display_name = $contact["title"];
            wp_update_user( $user );
        }
        $corresponds_to_contact = get_user_option( "corresponds_to_contact", $user_id );
        if ( empty( $corresponds_to_contact ) ){
            self::create_contact_for_user( $user_id );
        }
        if ( isset( $_POST["dt_locale"] ) ) {
            $userdata = get_user_by( 'id', $user_id );

            if ( isset( $_POST["dt_locale"] )) {
                if ( $_POST["dt_locale"] === "" ) {
                    $locale = "en-US";
                } else {
                    $locale = sanitize_text_field( wp_unslash( $_POST["dt_locale"] ) );
                }
            }
            $userdata->locale = sanitize_text_field( wp_unslash( $locale ) );

            wp_update_user( $userdata );
        }
    }

    /**
     * Makes sure a user is linked to a contact when logging in.
     * @param $user_name
     * @param $user
     */
    public static function user_login_hook( $user_name, $user ){
        $corresponds_to_contact = get_user_option( "corresponds_to_contact", $user->ID );
        if ( empty( $corresponds_to_contact ) && is_user_member_of_blog( $user->ID ) ){
            self::create_contact_for_user( $user->ID );
        }
    }

    /**
     * Profile update hook
     *
     * @param $user_id
     */
    public static function profile_update_hook( $user_id ) {
        self::create_contact_for_user( $user_id );

        if ( isset( $_POST["corresponds_to_contact_id"] ) && !empty( $_POST["corresponds_to_contact_id"] ) ){
            $corresponds_to_contact = sanitize_text_field( wp_unslash( $_POST["corresponds_to_contact_id"] ) );
            update_user_option( $user_id, "corresponds_to_contact", $corresponds_to_contact );
            DT_Posts::update_post( "contacts", (int) $corresponds_to_contact, [
                "corresponds_to_user" => $user_id
            ], true, false );
        }

        if ( !empty( $_POST["allowed_sources"] ) ) {
            if ( isset( $_REQUEST['action'] ) && 'update' == $_REQUEST['action'] ) {
                check_admin_referer( 'update-user_' . $user_id );
            }
            $allowed_sources = [];
            foreach ( $_POST["allowed_sources"] as $s ) {  // @codingStandardsIgnoreLine
                $allowed_sources[] = sanitize_key( wp_unslash( $s ) );
            }
            if ( in_array( "restrict_all_sources", $allowed_sources ) ){
                $allowed_sources = [ "restrict_all_sources" ];
            }
            update_user_option( $user_id, "allowed_sources", $allowed_sources );
        }
    }

    public static function add_user_role( $user_id, $role ){
        if ( user_can( $user_id, "access_specific_sources" ) ){
            $allowed_sources = get_user_option( "allowed_sources", $user_id ) ?: [];
            if ( in_array( "restrict_all_sources", $allowed_sources ) || empty( $allowed_sources ) ){
                $allowed_sources = [ "restrict_all_sources" ];
                update_user_option( $user_id, "allowed_sources", $allowed_sources );
            }
        }
    }

    public static function create_contacts_for_existing_users(){
        if ( is_user_logged_in() ){
            $users = get_users();
            foreach ( $users as $user ){
                self::create_contact_for_user( $user->ID );
            }
        }
    }

    /**
     * Get the base user for the system
     * You can call this function using dt_get_base_user( $id_only = false )
     *
     * @since 0.1.0
     *
     * @param bool $id_only     (optional) Default is false and function returns entire WP_User object.
     *
     * @return array|false|\WP_Error|\WP_User
     */
    public static function get_base_user( $id_only = false ) {

        // get base user id
        $base_user_id = dt_get_option( 'base_user' );
        if ( ! $base_user_id ) {
            return new WP_Error( 'failed_to_get_base_user', 'Failed to get base user. dt_get_option( base_user ) failed.' );
        }

        // get base user object and test if user exists
        $base_user = get_user_by( 'ID', $base_user_id );
        if ( empty( $base_user ) ) { // if base_user has been deleted.
            update_option( 'dt_base_user', false ); // clear current value
            $base_user_id = dt_get_option( 'base_user' ); // call the reset process to re-assign new base user.
            $base_user = get_user_by( 'ID', $base_user_id );
        }

        // test if id and object are populated
        if ( empty( $base_user ) || empty( $base_user_id ) ) {
            return new WP_Error( 'failed_to_get_base_user', 'Failed to get base user object or id using id: '. $base_user_id );
        }

        if ( $id_only ) {
            return $base_user_id;
        }

        return $base_user;
    }

    public static function get_user_filters( $post_type, $force_refresh = false ){
        $current_user_id = get_current_user_id();
        $filters = [];
        if ( $current_user_id ){
            $filters = get_user_option( "dt_cached_filters_$post_type", $current_user_id );
            if ( !empty( $filters ) && $force_refresh === false ) {
                return $filters;
            }
            $custom_filters = maybe_unserialize( get_user_option( "saved_filters", $current_user_id ) );
            $filters = [
                "tabs" => [
                    [
                        "key" => "custom",
                        "label" => _x( "Custom Filters", 'List Filters', 'disciple_tools' ),
                        "order" => 99
                    ]
                ],
                "filters" => []
            ];
            foreach ( $custom_filters[$post_type] ?? [] as $filter ){
                $filter["tab"] = "custom";
                $filter["ID"] = (string) $filter["ID"];
                $filters["filters"][] = $filter;
            }


            $filters = apply_filters( "dt_user_list_filters", $filters, $post_type );
            usort( $filters["tabs"], function ( $a, $b ) {
                return ( $a["order"] ?? 50 ) >= ( $b["order"] ?? 51 );
            } );
            update_user_option( $current_user_id, "dt_cached_filters_$post_type", $filters );
        }
        return $filters;
    }

    public static function save_user_filter( $filter, $post_type ){
        $current_user_id = get_current_user_id();
        if ( $current_user_id && isset( $filter["ID"] ) ){
            $filter = filter_var_array( $filter, FILTER_SANITIZE_STRING );
            $filters = get_user_option( "saved_filters", $current_user_id );
            if ( !isset( $filters[$post_type] ) ){
                $filters[$post_type] = [];
            }

            $updated = false;
            foreach ( $filters[$post_type] as $index => $f ){
                if ( $f["ID"] === $filter["ID"] ){
                    $filters[$post_type][$index] = $filter;
                    $updated = true;
                }
            }
            if ( $updated === false ){
                $filters[$post_type][] = $filter;
            }
            update_user_option( $current_user_id, "saved_filters", $filters );
            update_user_option( $current_user_id, "dt_cached_filters_$post_type", [] );
        }
        return true;
    }

    public static function delete_user_filter( $id, $post_type ){
        $current_user_id = get_current_user_id();
        if ( $current_user_id ){
            $filters = get_user_option( "saved_filters", $current_user_id );
            if ( !isset( $filters[$post_type] ) ){
                $filters[$post_type] = [];
            }
            $index_to_remove = null;
            foreach ( $filters[$post_type] as $index => $filter ){
                if ( $filter["ID"] === $id ){
                    $index_to_remove =$index;
                }
            }
            if ( $index_to_remove !== null ){
                unset( $filters[$post_type][$index_to_remove] );
                $filters[$post_type] = array_values( $filters[$post_type] );
                update_user_option( $current_user_id, "saved_filters", $filters );
                return true;
            } else {
                return false;
            }
        }
        return false;
    }

    /**
     * Modifies the add user wp-admin page to add the 'corresponds to contact' field.
     *
     * @param $user
     */
    public function custom_user_profile_fields( $user ){
        $contact_id = "";
        $contact_title = "";
        if ( $user != "add-new-user" && $user != "add-existing-user" && isset( $user->ID ) ) {
            $contact_id   = get_user_option( "corresponds_to_contact", $user->ID );
            if ( $contact_id ){
                $contact = get_post( $contact_id );
                if ( $contact ){
                    $contact_title = $contact->post_title;
                }
            }
        }
        if ( empty( $contact_title ) ) : ?>
            <script type="application/javascript">
                jQuery(document).ready(function($) {
                    //removes the Wordpress Language selector that only shows the Wordpress languages and not the Disciple tools languages.
                    jQuery(".form-field.user-language-wrap").remove();

                    jQuery(".corresponds_to_contact").each(function () {
                        jQuery(this).autocomplete({
                            source: function (request, response) {
                                jQuery.ajax({
                                    url: '<?php echo esc_html( rest_url() ) ?>dt-posts/v2/contacts/compact',
                                    data: {
                                        s: request.term
                                    },
                                    beforeSend: function (xhr) {
                                        xhr.setRequestHeader('X-WP-Nonce',
                                            "<?php echo esc_html( wp_create_nonce( 'wp_rest' ) ) ?>");
                                    }
                                }).then(data => {
                                    response(data.posts);
                                })
                            },
                            minLength: 2,
                            select: function (event, ui) {
                                $(".corresponds_to_contact").val(ui.item.name);
                                $(".corresponds_to_contact_id").val(ui.item.ID);
                                return false;
                            }
                        }).autocomplete("instance")._renderItem = function (ul, item) {
                            return $("<li>")
                                .append(`<div>${item.name} (${item.ID})</div>`)
                                .appendTo(ul);
                        };
                    })
                });
            </script>
        <?php endif; ?>
        <h3><?php esc_html_e( "Extra Disciple.Tools Information", 'disciple_tools' ) ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="contact"><?php esc_html_e( "Corresponds to Contact", 'disciple_tools' ) ?></label></th>
                <td>
                    <?php if ( !empty( $contact_title ) ) : ?>
                        <a href="<?php echo esc_html( get_permalink( $contact_id ) ) ?>"><?php echo esc_html( $contact_title ) ?></a>
                    <?php else : ?>
                        <input type="text" class="regular-text corresponds_to_contact" name="corresponds_to_contact" value="<?php echo esc_html( $contact_title )?>" /><br />
                        <input type="hidden" class="regular-text corresponds_to_contact_id" name="corresponds_to_contact_id" value="<?php echo esc_html( $contact_id )?>" />
                        <?php if ( $contact_id ) : ?>
                            <span class="description"><a href="<?php echo esc_html( get_site_url() . '/contacts/' . $contact_id )?>" target="_blank"><?php esc_html_e( "View Contact", 'disciple_tools' ) ?></a></span>
                        <?php else :?>
                            <span class="description"><?php esc_html_e( "Add the name of the contact record this user corresponds to.", 'disciple_tools' ) ?>
                                <a target="_blank" href="https://disciple.tools/user-docs/getting-started-info/users/inviting-users/"><?php esc_html_e( "Learn more.", "disciple_tools" ) ?></a>
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label for="dt_locale"><?php esc_html_e( "User Language", 'disciple_tools' ) ?></label></th>
                <td>
                    <?php
                         $dt_available_languages = get_available_languages( get_template_directory() .'/dt-assets/translation' );
                         $translations = dt_get_translations();
                         $site_default_locale = get_option( 'WPLANG' );
                    wp_dropdown_languages( array(
                        'name'                        => 'dt_locale',
                        'id'                          => 'dt_locale',
                        'selected'                    => $site_default_locale,
                        'languages'                   => $dt_available_languages,
                        'show_available_translations' => false,
                        'show_option_site_default'    => false,
                        'show_option_en_us'           => true,
                        'translations'                => $translations
                    ) );
                    ?>
                </td>
            </tr>
        </table>
        <?php if ( isset( $user->ID ) && user_can( $user->ID, 'access_specific_sources' ) ) :
            $selected_sources = get_user_option( 'allowed_sources', $user->ID );
            $post_settings = DT_Posts::get_post_settings( "contacts" );
            $sources = isset( $post_settings["fields"]["sources"]["default"] ) ? $post_settings["fields"]["sources"]["default"] : [];
            ?>
            <h3>Access by Source</h3>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( "Sources", 'disciple_tools' ) ?></th>
                    <td>
                        <ul>
                            <li>
                                <?php $checked = in_array( 'all', $selected_sources === false ? [ 'all' ] : $selected_sources ) ? "checked" : ''; ?>
                                <label>
                                    <input type="radio" name="allowed_sources[]" value="all" <?php echo esc_html( $checked ) ?>/>
                                    <?php esc_html_e( 'All Sources - gives access to all contacts', 'disciple_tools' ); ?>
                                </label>
                            </li>
                            <li>
                                <?php $checked = in_array( 'custom_source_restrict', $selected_sources === false ? [] : $selected_sources ) ? "checked" : ''; ?>
                                <label>
                                    <input type="radio" name="allowed_sources[]" value="custom_source_restrict" <?php echo esc_html( $checked ) ?>/>
                                    <?php esc_html_e( 'Custom - Access own contacts and all the contacts of the selected sources below', 'disciple_tools' ); ?>
                                </label>
                            </li>
                            <li>
                                <?php $checked = in_array( 'restrict_all_sources', $selected_sources === false ? [] : $selected_sources ) ? "checked" : ''; ?>
                                <label>
                                    <input type="radio" name="allowed_sources[]" value="restrict_all_sources" <?php echo esc_html( $checked ) ?>/>
                                    <?php esc_html_e( 'No Sources - only own contacts', 'disciple_tools' ); ?>
                                </label>
                            </li>
                            <li>
                                &nbsp;
                            </li>
                        <?php foreach ( $sources as $source_key => $source_value ) :
                            $checked = in_array( $source_key, $selected_sources === false ? [] : $selected_sources ) ? "checked" : '';
                            ?>
                            <li>
                                <label>
                                    <input type="checkbox" name="allowed_sources[]" value="<?php echo esc_html( $source_key ) ?>" <?php echo esc_html( $checked ) ?>/>
                                    <?php echo esc_html( $source_value["label"] ) ?>
                                </label>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    </td>
                </tr>
            </table>

        <?php endif;
    }


    public function dt_contact_merged( $master_id, $non_master_id ){
        //check to make sure both contacts don't point to a user
        $corresponds_to_user = get_post_meta( $master_id, "corresponds_to_user", true );
        if ( $corresponds_to_user ){
            $contact_id = get_user_option( "corresponds_to_contact", $corresponds_to_user );
            //make sure the user points to the right contact
            if ( $contact_id != $master_id ){
                update_user_option( $corresponds_to_user, "corresponds_to_contact", $master_id );
            }
            $dup_corresponds_to_contact = get_post_meta( $non_master_id, "corresponds_to_user", true );
            if ( $dup_corresponds_to_contact ){
                delete_post_meta( $non_master_id, "corresponds_to_user" );
            }
            //update the user display name to the new contact name
            $user = get_user_by( 'id', $corresponds_to_user );
            $user->display_name = get_the_title( $master_id );
            wp_update_user( $user );
        }


        //make sure only one contact keeps the user type
        $master_contact_type = get_post_meta( $master_id, "type", true );
        $non_master_contact_type = get_post_meta( $non_master_id, "type", true );
        if ( $master_contact_type === "user" || $non_master_contact_type === "user" ){
            //keep both records as type "user"
            update_post_meta( $master_id, "type", "user" );
            update_post_meta( $non_master_id, "type", "user" );
        }
    }

    private static function invite_existing_user_to_site( $user_id, $user_email, $role ){
        $user_details = get_user_by( "ID", $user_id );
        $newuser_key = wp_generate_password( 20, false );
        add_option(
            'new_user_' . $newuser_key,
            array(
                'user_id' => $user_id,
                'email'   => $user_details->user_email,
                'role'    => $role,
            )
        );

        $all_roles = wp_roles()->roles;
        $roles = apply_filters( 'editable_roles', $all_roles );
        $role  = $roles[ $role ];

        /**
         * Fires immediately after a user is invited to join a site, but before the notification is sent.
         *
         * @since 4.4.0
         *
         * @param int    $user_id     The invited user's ID.
         * @param array  $role        The role of invited user.
         * @param string $newuser_key The key of the invitation.
         */
        do_action( 'invite_user', $user_id, $role, $newuser_key );

        $switched_locale = switch_to_locale( get_user_locale( $user_details ) );

        /* translators: 1: Site name, 2: site URL, 3: role, 4: activation URL */
        $message = __(
            'Hi,

You\'ve been invited to join \'%1$s\' at
%2$s with the role of %3$s.

Please click the following link to confirm the invite:
%4$s', 'disciple_tools'
        );

        /* translators: Joining confirmation notification email subject. %s: Site title */
        wp_mail( $user_email, sprintf( __( '[%s] Joining Confirmation', 'disciple_tools' ), wp_specialchars_decode( get_option( 'blogname' ) ) ), sprintf( $message, get_option( 'blogname' ), home_url(), wp_specialchars_decode( translate_user_role( $role['name'] ) ), home_url( "/newbloguser/$newuser_key/" ) ) );

        if ( $switched_locale ) {
            restore_previous_locale();
        }
        return $user_id;
    }

    /**
     * @param $user_name
     * @param $user_email
     * @param $display_name
     * @param string $user_role
     * @param null $corresponds_to_contact
     * @return int|WP_Error
     */
    public static function create_user( $user_name, $user_email, $display_name, $user_role = 'multiplier', $corresponds_to_contact = null ){
        if ( !current_user_can( "create_users" ) ){
            return new WP_Error( "no_permissions", "You don't have permissions to create users", [ 'status' => 401 ] );
        }
        $user_email = sanitize_email( wp_unslash( $user_email ) );
        $user_name = sanitize_user( wp_unslash( $user_name ), true );
        $display_name = sanitize_text_field( wp_unslash( $display_name ) );

        $user_id = email_exists( $user_email );
        if ( $user_id ){

            if ( is_user_member_of_blog( $user_id ) ){
                $contact_id = self::get_contact_for_user( $user_id );
                if ( ! $contact_id ) {
                    self::create_contact_for_user( $user_id );
                    return $user_id;
                }

                return new WP_Error( "email_exists", __( "Email already exists and is a user on this site", 'disciple_tools' ), [ 'status' => 409 ] );
            } else {

                $blog_id = get_current_blog_id();
                $addition = add_user_to_blog( $blog_id, $user_id, 'multiplier' );
                if ( is_wp_error( $addition ) ) {
                    return new WP_Error( "failed_to_add_user", __( "Failed to add user to site.", 'disciple_tools' ), [ 'status' => 409 ] );
                }
            }
        } else {

            if ( 'dt_admin' === $user_role || 'administrator' === $user_role ) {
                return new WP_Error( "failed_to_add_user", __( "Error setting user role to administrator or Disciple.Tools Admin.", 'disciple_tools' ) );
            }

            $user_id = register_new_user( $user_name, $user_email );
            if ( is_wp_error( $user_id ) ){
                return $user_id;
            }
            $user = get_user_by( 'id', $user_id );
            $user->display_name = $display_name;
            $user->set_role( $user_role );
            wp_update_user( $user );
        }

        global $wpdb;
        update_user_meta( $user_id, $wpdb->prefix . 'user_status', 'active' );
        update_user_meta( $user_id, $wpdb->prefix . 'workload_status', 'active' );

        if ( $corresponds_to_contact ) {
            update_user_meta( $user_id, $wpdb->prefix . 'corresponds_to_contact', $corresponds_to_contact );
            update_post_meta( $corresponds_to_contact, 'corresponds_to_user', $user_id );
            update_post_meta( $corresponds_to_contact, 'type', 'user' );
        }

        return $user_id;
    }


    /**
     * Modifies the wp-admin users list table to add a link to the users's contact
     *
     * @param $actions
     * @param $user
     *
     * @return mixed
     */
    public function dt_edit_user_row_actions( $actions, $user ){
        $contact_id = self::get_contact_for_user( $user->ID );
        $link = get_permalink( $contact_id );
        if ( $contact_id && $link ){
            $actions["view"] = '<a href="' . $link . '" aria-label="View contact">' . esc_html( __( "View contact record", 'disciple_tools' ) ) . '</a>';
        } else {
            unset( $actions["view"] );
        }
        return $actions;
    }

    /**
     * Modifies the wp-admin users list table to add the display name column
     *
     * @param $column
     *
     * @return array
     */
    public function new_modify_user_table( $column ) {
        $column = array_slice( $column, 0, 3, true ) +
        array( "display_name" => "Display Name" ) +
        array_slice( $column, 3, null, true );
        return $column;
    }

    public function new_modify_user_table_row( $val, $column_name, $user_id ) {
        switch ( $column_name ) {
            case 'display_name' :
                return dt_get_user_display_name( $user_id );
                break;
            default:
        }
        return $val;
    }
    public function user_deleted( $user_id, $blog_id = null ){
        $corresponds_to_contact = self::get_contact_for_user( $user_id );
        if ( $corresponds_to_contact ){
            delete_post_meta( $corresponds_to_contact, "corresponds_to_user" );
        }
    }
    public function add_date_availability( $custom_data ) {
        $dates_unavailable = get_user_option( "user_dates_unavailable", get_current_user_id() );
        if ( !$dates_unavailable ) {
            $dates_unavailable = [];
        }
        foreach ( $dates_unavailable ?? [] as &$range ) {
            $range["start_date"] = dt_format_date( $range["start_date"] );
            $range["end_date"] = dt_format_date( $range["end_date"] );
        }
        $custom_data['availability'] = $dates_unavailable;
        return $custom_data;
    }

    public function add_current_locations_list( $custom_data ) {
        $custom_data['current_locations'] = DT_Mapping_Module::instance()->get_post_locations( dt_get_associated_user_id( get_current_user_id() ) );
        return $custom_data;
    }

    public static function add_user_location( $grid_id, $user_id = null ) {
        if ( empty( $user_id ) ) {
            $user_id = get_current_user_id();
        }

        global $wpdb;
        $umeta_id = add_user_meta( $user_id, $wpdb->prefix . 'location_grid', $grid_id );

        if ( $umeta_id ) {
            return true;
        }
        return false;
    }

    public static function delete_user_location( $grid_id, $user_id = null ) {
        if ( empty( $user_id ) ) {
            $user_id = get_current_user_id();
        }

        global $wpdb;
        $umeta_id = delete_user_meta( $user_id, $wpdb->prefix . 'location_grid', $grid_id );
        if ( $umeta_id ) {
            return true;
        }
        return false;
    }

    /**
     *
     * @param $location_grid_meta
     * @param null $user_id
     * @return array|bool|int|WP_Error
     */
    public static function add_user_location_meta( $location_grid_meta, $user_id = null ) {
        if ( empty( $user_id ) ) {
            $user_id = get_current_user_id();
        }
        $umeta_id = null;
        // use grid_id as primary value
        if ( isset( $location_grid_meta["grid_id"] ) && ! empty( $location_grid_meta["grid_id"] ) ) {
            $geocoder = new Location_Grid_Geocoder();

            $grid = $geocoder->query_by_grid_id( $location_grid_meta["grid_id"] );
            if ($grid) {
                $lgm = [];

                Location_Grid_Meta::validate_location_grid_meta( $lgm );
                $lgm['post_id'] = $user_id;
                $lgm['post_type'] = 'users';
                $lgm['grid_id'] = $grid["grid_id"];
                $lgm['lng'] = $grid["longitude"];
                $lgm['lat'] = $grid["latitude"];
                $lgm['level'] = $grid["level_name"];
                $lgm['label'] = $geocoder->_format_full_name( $grid );

                $umeta_id = Location_Grid_Meta::add_user_location_grid_meta( $user_id, $lgm );
                if (is_wp_error( $umeta_id )) {
                    return $umeta_id;
                }
            }
        // use lng lat as base value
        } else {

            if (empty( $location_grid_meta['lng'] ) || empty( $location_grid_meta['lat'] )) {
                return new WP_Error( __METHOD__, 'Missing required lng or lat' );
            }

            $umeta_id = Location_Grid_Meta::add_user_location_grid_meta( $user_id, $location_grid_meta );
        }

        if ( $umeta_id ) {
            return self::get_user_location( $user_id );
        }
        return false;
    }

    public static function delete_user_location_meta( $grid_meta_id, $user_id = null ) {
        if ( empty( $user_id ) ) {
            $user_id = get_current_user_id();
        }

        return Location_Grid_Meta::delete_user_location_grid_meta( $user_id, 'grid_meta_id', $grid_meta_id );
    }

    public static function get_user_location( $user_id = null ) {
        if ( empty( $user_id ) ) {
            $user_id = get_current_user_id();
        }
        $grid = [];

        global $wpdb;
        if ( DT_Mapbox_API::get_key() ) {
            $location_grid = get_user_meta( $user_id, $wpdb->prefix . 'location_grid_meta' );
            $grid['location_grid_meta'] = [];
            foreach ( $location_grid as $meta ) {
                $location_grid_meta = Location_Grid_Meta::get_location_grid_meta_by_id( $meta );
                if ( $location_grid_meta ) {
                    $grid['location_grid_meta'][] = $location_grid_meta;
                }
            }
            $grid['location_grid'] = [];
            foreach ( $grid['location_grid_meta'] as $meta ) {
                $grid['location_grid'][] = [
                    'id' => (int) $meta['grid_id'],
                    'label' => $meta['label']
                ];
            }
        } else {
            $location_grid = get_user_meta( $user_id, $wpdb->prefix . 'location_grid' );
            if ( ! empty( $location_grid ) ) {
                $names = Disciple_Tools_Mapping_Queries::get_names_from_ids( $location_grid );
                $grid['location_grid'] = [];
                foreach ( $names as $id => $name ) {
                    $grid['location_grid'][] = [
                        "id" => $id,
                        "label" => $name
                    ];
                }
            }
        }

        return $grid;
    }

    public static function copy_locations_from_contact_to_user( $contact_id, $user_id ) {
        // @todo finish writing transfer
        $contact_meta = get_post_meta( $contact_id, 'location_grid' );
        if ( ! empty( $contact_meta ) ) {
            foreach ( $contact_meta as $item ) {
                dt_write_log( $item );
            }
        }
    }

    public static function dt_delete_user_contact_meta( $user_id ) {
        global $wpdb;
        $wpdb->get_results(
            $wpdb->prepare( "DELETE FROM $wpdb->postmeta pm WHERE meta_key = 'corresponds_to_user' AND pm.meta_value = %d
            ", $user_id )
        );
    }

    /** Multisite Only
     *  This will remove the 'corresponds_to_user' meta key and value from all sites on the network if deleted by a super admin
     */
    public static function dt_multisite_delete_user_contact_meta( $user_id ) {
        $blogs = get_sites();
        if ( ! empty( $blogs ) ) {
            foreach ( $blogs as $blog ) {
                switch_to_blog( $blog->userblog_id );
                global $wpdb;
                $wpdb->get_results(
                    $wpdb->prepare( "DELETE FROM $wpdb->postmeta pm WHERE meta_key = 'corresponds_to_user' AND pm.meta_value = %d
                    ", $user_id )
                );

                restore_current_blog();
            }
        }
    }
}
