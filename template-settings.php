<?php
/*
Template Name: Settings
*/
dt_please_log_in();
/* Process $_POST content */
// We're not checking the nonce here because update_user_contact_info will
// phpcs:ignore
if ( isset( $_POST['user_update_nonce'] ) ) {
    Disciple_Tools_Users::update_user_contact_info();
}

/* Build variables for page */
$dt_user = wp_get_current_user(); // Returns WP_User object
$dt_user_meta = get_user_meta( $dt_user->ID ); // Full array of user meta data
$dt_user_contact_id = dt_get_associated_user_id( $dt_user->ID, 'user' );

$dt_user_fields = dt_build_user_fields_display( $dt_user_meta ); // Compares the site settings in the config area with the fields available in the user meta table.
$dt_site_notification_defaults = dt_get_site_notification_defaults(); // Array of site default settings
$dt_available_languages = get_available_languages( get_template_directory() .'/dt-assets/translation' );

$dt_user_locale = get_user_locale( $dt_user->ID );
$translations = dt_get_translations();

$contact_fields = DT_Posts::get_post_settings( "contacts" )["fields"];

/**
 * Filter for adding user apps to the settings area.
 * An app leverages the magic link structure
 */
$apps_list = apply_filters( 'dt_settings_apps_list', $apps_list = [] );

?>

<?php get_header(); ?>

<div id="content" class="template-settings">

    <div id="inner-content" class="grid-x grid-margin-x grid-margin-y">

        <div class="large-3 medium-3 small-12 cell " data-sticky-container>

            <section id="" class="small-12 cell sticky" data-sticky data-top-anchor="settings" data-sticky-on="medium" data-margin-top="5">

                <div class="bordered-box">

                    <ul class="menu vertical expanded" data-smooth-scroll data-offset="100">
                        <li><a href="#profile"><?php esc_html_e( 'Profile', 'disciple_tools' )?></a></li>
                        <?php if ( ! empty( $apps_list ) ) : ?>
                            <li><a href="#apps"><?php esc_html_e( 'Apps', 'disciple_tools' )?></a></li>
                        <?php endif; ?>
                        <li><a href="#multiplier"><?php esc_html_e( 'Multiplier Preferences', 'disciple_tools' )?></a></li>
                        <li><a href="#availability"><?php esc_html_e( 'Availability', 'disciple_tools' )?></a></li>
                        <li><a href="#notifications"><?php esc_html_e( 'Notifications', 'disciple_tools' )?></a></li>

                        <?php
                        /**
                         * Add a page menu item that jumps the settings page down to a custom tile.
                         * Add a link with an li wrapper and a named #hash to your tile.
                         *
                         * Use this in combination with the action at the bottom the page called: dt_profile_settings_page_sections
                         * @param $dt_user WP_User object
                         * @param $dt_user_meta array Full array of user meta data
                         * @param $dt_user_contact_id bool/int returns either id for contact connected to user or false
                         * @param $contact_fields array Array of fields on the contact record
                         */
                        do_action( 'dt_profile_settings_page_menu', $dt_user, $dt_user_meta, $dt_user_contact_id, $contact_fields ) ?>

                    </ul>

                </div>

            </section>

        </div>

        <div class="large-9 medium-9 small-12 cell ">

            <div class="grid-margin-x grid-x grid-margin-y" id="settings">

                <div class="small-12 cell">

                    <div class="bordered-box cell" id="profile" data-magellan-target="profile">

                        <button class="help-button float-right" data-section="profile-help-text">
                            <img class="help-icon" src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/help.svg' ) ?>"/>
                        </button>

                        <span class="section-header"><?php esc_html_e( 'Your Profile', 'disciple_tools' )?></span>

                        <button class="float-right" data-open="edit-profile-modal"><i class="fi-pencil"></i> <?php esc_html_e( 'Edit', 'disciple_tools' )?></button>

                        <hr/>

                        <div class="grid-x grid-margin-x grid-padding-x grid-padding-y ">

                            <div class="small-12 medium-4 cell">

                                <p><?php echo get_avatar( $dt_user->ID, '150' ); ?></p>

                                <p>
                                    <strong><?php esc_html_e( 'Username', 'disciple_tools' )?></strong><br>
                                    <?php echo esc_html( $dt_user->user_login ); ?>
                                </p>

                                <?php if ( ! empty( $dt_user->first_name ) || ! empty( $dt_user->last_name ) ) : ?>
                                    <p>
                                        <strong><?php esc_html_e( 'Name', 'disciple_tools' )?></strong><br>
                                        <?php echo esc_html( $dt_user->first_name ); ?> <?php echo esc_html( $dt_user->last_name ); ?>
                                    </p>
                                <?php endif; ?>

                                <?php if ( ! empty( $dt_user->nickname ) ) : ?>
                                    <p>
                                        <strong dir="auto"><?php esc_html_e( 'Nickname (Display Name)', 'disciple_tools' )?></strong><br>
                                        <span dir="auto"><?php echo esc_html( $dt_user->nickname ); ?></span>
                                    </p>
                                <?php endif; ?>

                                <p>
                                    <strong><?php esc_html_e( 'Roles', 'disciple_tools' ); ?></strong><br>
                                    <?php echo esc_html( implode( ", ", dt_get_user_role_names( get_current_user_id() ) ) ); ?>
                                </p>

                            </div>
                            <div class="small-12 medium-4 cell" style="border-left: 1px solid lightgrey; padding-left: 1em;">

                                <p><strong><?php esc_html_e( 'Email', 'disciple_tools' )?></strong></p>
                                <ul>
                                    <li>
                                        <a href="mailto:'<?php echo esc_html( $dt_user->user_email ); ?>'"><?php echo esc_html( $dt_user->user_email ); ?></a><br><span class="text-small">(<?php esc_html_e( 'System Email', 'disciple_tools' ); ?>)</span>
                                    </li>
                                    <?php foreach ( $dt_user_fields as $dt_field ) {
                                        if ( $dt_field['type'] == 'email' && !empty( $dt_field['value'] ) ) {
                                            echo '<li><a href="mailto:' . esc_html( $dt_field['value'] ) . '" target="_blank">' . esc_html( $dt_field['value'] ) . '</a> <br><span class="text-small">(' . esc_html( $dt_field['label'] ) . ')</span></li>';
                                        }
                                    }
                                    ?>
                                </ul>

                                <strong><?php esc_html_e( 'Phone', 'disciple_tools' )?></strong>
                                <ul>
                                    <?php
                                    foreach ( $dt_user_fields as $dt_field ) {
                                        if ( $dt_field['type'] == 'phone' && !empty( $dt_field['value'] ) ) {
                                            echo '<li>' . esc_html( $dt_field['value'] ) . ' (' . esc_html( $dt_field['label'] ) . ')</li>';
                                        }
                                    }
                                    ?>
                                </ul>

                                <strong><?php esc_html_e( 'Address', 'disciple_tools' )?></strong>
                                <ul>
                                    <?php
                                    foreach ( $dt_user_fields as $dt_field ) {
                                        if ( $dt_field['type'] == 'address' && !empty( $dt_field['value'] ) ) {
                                            echo '<li>' . esc_html( $dt_field['value'] ) . ' (' . esc_html( $dt_field['label'] ) . ')</li>';
                                        }
                                    }
                                    ?>
                                </ul>

                                <strong><?php esc_html_e( 'Social', 'disciple_tools' )?></strong>
                                <ul>
                                    <?php
                                    foreach ( $dt_user_fields as $dt_field ) {
                                        if ( $dt_field['type'] == 'social' && !empty( $dt_field['value'] ) ) {
                                            echo '<li>' . esc_html( $dt_field['value'] ) . ' (' . esc_html( $dt_field['label'] ) . ')</li>';
                                        }
                                    }
                                    ?>
                                </ul>

                                <strong><?php esc_html_e( 'Other', 'disciple_tools' )?></strong>
                                <ul>
                                    <?php
                                    foreach ( $dt_user_fields as $dt_field ) {
                                        if ( $dt_field['type'] == 'other' && !empty( $dt_field['value'] ) ) {
                                            echo '<li>' . esc_html( $dt_field['value'] ) . ' (' . esc_html( $dt_field['label'] ) . ')</li>';
                                        }
                                    }
                                    ?>
                                </ul>

                            </div>
                            <div class="small-12 medium-4 cell" style="border-left: 1px solid lightgrey; padding-left: 1em;">

                                <strong><?php esc_html_e( 'Language', 'disciple_tools' )?></strong>
                                <p>
                                <?php
                                if ( !empty( $dt_user_locale ) && $dt_user_locale !== 'en_US' ){
                                    echo esc_html( $translations[$dt_user_locale]['native_name'] );
                                } else {
                                    echo esc_html__( 'English', 'disciple_tools' );
                                }
                                ?></p>

                                <strong><?php esc_html_e( 'Biography', 'disciple_tools' )?></strong>
                                <p><?php echo esc_html( $dt_user->user_description ); ?></p>

                                <?php $field_key = 'gender';
                                $user_field = get_user_option( 'user_gender', get_current_user_id() );
                                ?>

                                <!-- gender -->
                                <strong style="display: inline-block"><?php echo esc_html( $contact_fields[$field_key]["name"] ) ?></strong>
                                <p><?php echo esc_html( $user_field ); ?></p>
                            </div>
                        </div>

                    </div> <!-- End Profile -->
                </div>

                <?php if ( ! empty( $apps_list ) ) : ?>
                <!-- Begin Apps-->
                <div class="small-12 cell">

                    <div class="bordered-box cell" id="apps" data-magellan-target="apps">
                        <span class="section-header"><?php esc_html_e( 'Your Apps', 'disciple_tools' )?></span>
                        <hr/>
                        <table class="form-table">
                            <thead>
                            <tr>
                                <td><?php esc_html_e( 'App Name', 'disciple_tools' )?></td>
                                <td><?php esc_html_e( 'App Description', 'disciple_tools' )?></td>
                                <td><?php esc_html_e( 'App Link', 'disciple_tools' )?></td>
                                <td><?php esc_html_e( 'App Activation', 'disciple_tools' )?></td>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            if ( empty( $apps_list ) ) {
                                ?>
                                <tr>
                                    <td colspan="4">
                                        <?php echo esc_html__( 'No apps installed', 'disciple_tools' ); ?>
                                    </td>
                                </tr>
                                <?php
                            } else {
                                foreach ( $apps_list as $app_key => $app_value ) :
                                    $app_user_key = get_user_option( $app_key );
                                    $app_url_base = trailingslashit( trailingslashit( site_url() ) . $app_value['url_base'] );
                                    $app_link = false;
                                    if ( $app_user_key ) {
                                        $app_link = $app_url_base . $app_user_key;
                                    }
                                    ?>
                                    <tr>
                                        <td class="tall-3"><?php echo esc_html( $app_value["label"] )?></td>
                                        <td class="tall-3"><?php echo esc_html( $app_value["description"] )?></td>
                                        <td class="tall-3" id="app_link_<?php echo esc_attr( $app_key )?>" data-url-base="<?php echo esc_url( $app_url_base ) ?>">
                                            <?php if ( $app_link ) { ?>
                                                <a class="button small"  href="<?php echo esc_url( $app_link ) ?>" title="<?php esc_html_e( 'link', 'disciple_tools' ) ?>"><i class="fi-link"></i></a>
                                                <button class="button small copy_to_clipboard" data-value="<?php echo esc_url( $app_link ) ?>" title="<?php esc_html_e( 'copy', 'disciple_tools' ) ?>"><i class="fi-page-copy"></i></button>
                                            <?php } ?>
                                        </td>
                                        <td class="tall-3">
                                            <input class="switch-input" id="app_state_<?php echo esc_attr( $app_key )?>" type="checkbox" name="follow_all"
                                                   onclick="app_switch('<?php echo esc_attr( $app_key )?>');" <?php ( isset( $dt_user_meta[ $wpdb->prefix . $app_key] ) ) ? print esc_attr( 'checked' ) : print esc_attr( '' ); ?> />
                                            <label class="switch-paddle" for="app_state_<?php echo esc_attr( $app_key )?>">
                                                <span class="show-for-sr"><?php esc_html_e( 'Enable', 'disciple_tools' )?></span>
                                                <span class="switch-active" aria-hidden="true" style="color:white;"><?php esc_html_e( 'Yes', 'disciple_tools' )?></span>
                                                <span class="switch-inactive" aria-hidden="false"><?php esc_html_e( 'No', 'disciple_tools' )?></span>
                                            </label>
                                        </td>
                                    </tr>
                                    <?php
                                endforeach;
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- End Apps -->
                <?php endif; ?>


                <!-- Multiplier Interests -->
                <div class="small-12 cell bordered-box " id="multiplier" data-magellan-target="multiplier">
                    <span class="section-header" style="display: inline-block"><?php esc_html_e( 'Multiplier Preferences', 'disciple_tools' )?></span>
                    <hr>
                    <div class="grid-x grid-margin-x grid-padding-x grid-padding-y ">

                        <div class="small-12 medium-6 cell">

                            <!-- Locations -->
                            <?php if ( DT_Mapbox_API::get_key() ) : /* If Mapbox is enabled. */?>
                                <strong><?php esc_html_e( "Locations you are willing to be responsible for", 'disciple_tools' ) ?><a class="button clear float-right" id="new-mapbox-search"><?php esc_html_e( "add", 'disciple_tools' ) ?></a></strong>
                                <div id="mapbox-wrapper"></div>
                            <?php else : ?>
                                <div class="section-subheader cell">
                                    <img src="<?php echo esc_url( get_template_directory_uri() ) . "/dt-assets/images/location.svg" ?>">
                                    <?php esc_html_e( "Locations you are willing to be responsible for", 'disciple_tools' ) ?>
                                </div>
                                <div class="location_grid">
                                    <var id="location_grid-result-container" class="result-container"></var>
                                    <div id="location_grid_t" name="form-location_grid" class="scrollable-typeahead typeahead-margin-when-active">
                                        <div class="typeahead__container">
                                            <div class="typeahead__field">
                                                <span class="typeahead__query">
                                                    <input class="js-typeahead-location_grid input-height"
                                                           name="location_grid[query]"
                                                           placeholder="<?php esc_html_e( "Search Locations", 'disciple_tools' ) ?>"
                                                           autocomplete="off">
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>


                            <!-- Languages -->
                            <div class="section-subheader cell" style="margin-top:30px">
                                <img src="<?php echo esc_url( get_template_directory_uri() ) . "/dt-assets/images/languages.svg" ?>">
                                <strong style="display: inline-block;"><?php esc_html_e( 'Languages you are comfortable speaking', 'disciple_tools' )?></strong>
                                <span id="languages-spinner" style="display: inline-block" class="loading-spinner"></span>
                            </div>
                            <div class="small button-group" style="display: inline-block">
                                <?php foreach ( $contact_fields["languages"]["default"] as $option_key => $option_value ): ?>
                                    <?php
                                    $user_languages = get_user_option( 'user_languages', get_current_user_id() );
                                    $class = ( in_array( $option_key, $user_languages ?: [] ) ) ?
                                        "selected-select-button" : "empty-select-button"; ?>
                                    <button id="<?php echo esc_html( $option_key ) ?>" data-field-key="<?php echo esc_html( "languages" ) ?>"
                                            class="dt_multi_select <?php echo esc_html( $class ) ?> select-button button ">
                                        <?php echo esc_html( $contact_fields["languages"]["default"][$option_key]["label"] ) ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>


                            <!-- People Groups -->
                            <?php if ( isset( $contact_fields["people_groups"]["name"] ) ): ?>
                            <div class="section-subheader cell" style="margin-top:20px">
                                <img src="<?php echo esc_url( get_template_directory_uri() ) . "/dt-assets/images/people-group.svg" ?>">
                                <?php esc_html_e( 'People Groups you wish to serve', 'disciple_tools' ); ?>
                            </div>
                            <div class="people_groups full-width">
                                <var id="people_groups-result-container" class="result-container"></var>
                                <div id="people_groups_t" name="form-people_groups" class="scrollable-typeahead">
                                    <div class="typeahead__container">
                                        <div class="typeahead__field">
                                            <span class="typeahead__query">
                                                <input class="js-typeahead-people_groups"
                                                       name="people_groups[query]"
                                                       placeholder="<?php echo esc_html( sprintf( _x( "Search %s", "Search 'something'", 'disciple_tools' ), $contact_fields["people_groups"]["name"] ) )?>"
                                                       autocomplete="off">
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="small-12 medium-6 cell" style="border-left: 1px solid lightgrey; padding-left: 1em;">
                            <!-- Workload -->
                            <div class="section-subheader cell">
                                <?php esc_html_e( "Availability to receive contacts from the Dispatcher", 'disciple_tools' ) ?>
                                <span id="workload-spinner" style="display: inline-block" class="loading-spinner"></span>
                            </div>

                            <?php $options = dt_get_site_custom_lists()["user_workload_status"] ?? [];
                            foreach ( $options as $option_key => $option_val ) :
                                $icon = $option_key === "active" ? "play" : ( $option_key === "existing" ? "pause" : "stop" ); ?>
                                <button style="display: block" class="button hollow status-button" name="<?php echo esc_html( $option_key ) ?>">
                                    <i class="fi-<?php echo esc_html( $icon ) ?>"></i> <?php echo esc_html( $option_val["label"] )?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Begin Availability-->
                <div class="small-12 cell">

                    <div class="bordered-box cell" id="availability" data-magellan-target="availability">
                        <span class="section-header"><?php esc_html_e( 'Availability', 'disciple_tools' )?></span>
                        <hr/>

                        <p><?php esc_html_e( "Set the dates you will be traveling or unavailable so the Dispatcher will know your availability to receive new contacts", 'disciple_tools' ) ?></p>

                        <p>
                            <strong>
                                <?php esc_html_e( "Schedule Travel or Dates Unavailable", 'disciple_tools' ) ?>
                            </strong>
                        </p>
                        <div style="display: flex; justify-content: flex-start; align-items: center">
                            <div style="flex-shrink: 1">
                                <div class="section-subheader cell">
                                    <?php esc_html_e( 'Start Date', 'disciple_tools' )?>
                                </div>
                                <div class="start_date"><input type="text" class="date-picker" id="start_date" autocomplete="off"></div>
                            </div>
                            <div style="margin: 0 20px">
                                <div class="section-subheader cell">
                                    <?php esc_html_e( 'End Date', 'disciple_tools' )?>
                                </div>
                                <div class="end_date"><input type="text" class="date-picker" id="end_date" autocomplete="off"></div>
                            </div>
                            <div style="display: flex;">
                                <button id="add_unavailable_dates" class="button" disabled style="margin: 0;"><?php esc_html_e( "Add Unavailable dates", 'disciple_tools' ) ?></button>
                                <div id="add_unavailable_dates_spinner" style="display: inline-block" class="loading-spinner"></div>
                            </div>
                        </div>
                        <div >
                            <table class="hover stack-for-small striped">
                                <thead>
                                <tr>
                                    <th><?php esc_html_e( "Start Date", 'disciple_tools' ) ?></th>
                                    <th><?php esc_html_e( "End Date", 'disciple_tools' ) ?></th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody id="unavailable-list">
                                <tr><td><?php esc_html_e( "No Travel Scheduled", 'disciple_tools' ) ?></td></tr>
                                </tbody>

                            </table>
                        </div>
                    </div>
                </div>
                <!-- End Availability -->


                <!-- Begin Notification-->
                <div class="small-12 cell">
                    <div class="bordered-box cell" id="notifications" data-magellan-target="notifications">
                        <button class="help-button float-right" data-section="notifications-help-text">
                            <img class="help-icon" src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/help.svg' ) ?>"/>
                        </button>
                        <span class="section-header"><?php esc_html_e( 'Notifications', 'disciple_tools' )?></span>
                        <hr/>

                        <?php $email_preference = isset( $dt_user_meta['email_preference'] ) ? $dt_user_meta['email_preference'][0] : null ?>
                        <p>
                            <strong><?php echo esc_html_e( 'Email preferences', 'disciple_tools' ) ?></strong>
                            <span id="email-preference-spinner" class="loading-spinner"></span>
                        </p>
                        <div>
                            <label for="real-time-preference">
                                <input id="real-time-preference" type="radio" name="email-preference" <?php echo !$email_preference || $email_preference === 'real-time' ? 'checked' : '' ?>>
                                <?php echo esc_html_e( 'Real time', 'disciple_tools' ) ?>
                            </label>
                            <label for="hourly-preference">
                                <input id="hourly-preference" type="radio" name="email-preference" <?php echo $email_preference && $email_preference === 'hourly' ? 'checked' : '' ?>>
                                <?php echo esc_html_e( 'Hourly digest', 'disciple_tools' ) ?>
                            </label>
                            <label for="daily-preference">
                                <input id="daily-preference" type="radio" name="email-preference" <?php echo $email_preference && $email_preference === 'daily' ? 'checked' : '' ?>>
                                <?php echo esc_html_e( 'Daily digest', 'disciple_tools' ) ?>
                            </label>
                        </div>

                        <table class="form-table">
                            <thead>
                            <tr>
                                <td><?php esc_html_e( 'Type of Notification', 'disciple_tools' )?></td>
                                <?php foreach ( $dt_site_notification_defaults["channels"] as $channel_key => $channel_value ) : ?>
                                    <td><?php echo esc_html( $channel_value["label"] )?></td>
                                <?php endforeach; ?>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ( $dt_site_notification_defaults["types"] as $dt_notification_key => $dt_notification_default ) : ?>
                                <tr>
                                    <td class="tall-4"><?php echo esc_html( $dt_notification_default["label"] )?></td>
                                    <?php foreach ( $dt_site_notification_defaults["channels"] as $channel_key => $channel_value ) : ?>
                                        <td>
                                            <?php if ( $dt_notification_default[$channel_key] ) : ?>
                                                <div style="height:2em;"><?php esc_html_e( "required", 'disciple_tools' ) ?></div>
                                            <?php else :
                                                $channel_notification_key = $dt_notification_key . '_' . $channel_key;
                                                ?>
                                                <div class="switch">
                                                    <input class="switch-input" id="<?php echo esc_html( $channel_notification_key ) ?>" type="checkbox"
                                                           name="<?php echo esc_html( $channel_notification_key ) ?>"
                                                           onclick="switch_preference( '<?php echo esc_html( $channel_notification_key ) ?>', 'notifications' );"
                                                        <?php print esc_attr( ( isset( $dt_user_meta[$channel_notification_key ] ) && $dt_user_meta[$channel_notification_key ][0] == false ) ? '' : 'checked' ); ?>>
                                                    <label class="switch-paddle inactive" for="<?php echo esc_html( $channel_notification_key ) ?>">
                                                        <span class="show-for-sr"><?php echo esc_html( $dt_notification_default['label'] ) ?></span>
                                                    </label>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>

                        <?php if ( current_user_can( "dt_all_access_contacts" ) ): ?>
                            <p>
                                <strong><?php esc_html_e( 'Follow all contacts', 'disciple_tools' )?></strong>
                            </p>
                            <p><?php esc_html_e( "You will receive a notification for any update that happens in the system.", 'disciple_tools' ) ?></p>
                            <div class="switch large">
                                <input class="switch-input" id="follow_all" type="checkbox" name="follow_all"
                                       onclick="switch_preference('dt_follow_all');" <?php ( isset( $dt_user_meta['dt_follow_all'] ) && $dt_user_meta['dt_follow_all'][0] == true ) ? print esc_attr( 'checked' ) : print esc_attr( '' ); ?> />
                                <label class="switch-paddle" for="follow_all">
                                    <span class="show-for-sr"><?php esc_html_e( 'Enable', 'disciple_tools' )?></span>
                                    <span class="switch-active" aria-hidden="true"><?php esc_html_e( 'Yes', 'disciple_tools' )?></span>
                                    <span class="switch-inactive" aria-hidden="false"><?php esc_html_e( 'No', 'disciple_tools' )?></span>
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- End Notifications -->

                <?php
                /**
                 * Action hook to add additional tiles to the bottom of the personal settings page
                 *
                 * Wrap your section of code in <div class="cell bordered-box" id="your_id" data-magellan-target="your_id"></div>
                 *
                 * In addition to this section, you can add a navigation link above on with the dt_profile_settings_page_menu at the top of this page
                 *
                 * @param $dt_user WP_User object
                 * @param $dt_user_meta array Full array of user meta data
                 * @param $dt_user_contact_id bool/int returns either id for contact connected to user or false
                 * @param $contact_fields array Array of fields on the contact record
                 */
                do_action( 'dt_profile_settings_page_sections', $dt_user, $dt_user_meta, $dt_user_contact_id, $contact_fields )
                ?>

                <div class="reveal" id="edit-profile-modal" data-reveal>
                    <button class="close-button" data-close aria-label="Close modal" type="button">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h2><?php esc_html_e( 'Edit', 'disciple_tools' )?></h2>

                    <div class="row column medium-12">

                        <form method="post">

                            <?php wp_nonce_field( "user_" . $dt_user->ID . "_update", "user_update_nonce", false, true ); ?>

                            <table class="table">

                                <tr>
                                    <td><?php echo get_avatar( $dt_user->ID, '32' ); ?></td>
                                    <td>
                                        <span data-tooltip data-click-open="true" class="top" tabindex="1"
                                              title="<?php esc_html_e( 'Disciple Tools System does not store images. For profile images we use Gravatar (Globally Recognized Avatar). If you have security concerns, we suggest not using a personal photo, but instead choose a cartoon, abstract, or alias photo to represent you.', 'disciple_tools' ) ?>">
                                            <a href="http://gravatar.com" class="small"><?php esc_html_e( 'edit image on gravatar.com', 'disciple_tools' ) ?> <i class="fi-link"></i></a>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><?php esc_html_e( 'Username', 'disciple_tools' )?> </td>
                                    <td><span data-tooltip data-click-open="true" class="top" tabindex="2" title="<?php esc_html_e( 'Username cannot be changed', 'disciple_tools' ) ?>"><?php echo esc_html( $dt_user->user_login ); ?> <i class="fi-info primary-color" onclick="jQuery('#username-message').toggle()"></i></span>
                                        <span id="username-message" style="display: none; font-size: .7em;"><br><?php esc_html_e( 'Username cannot be changed', 'disciple_tools' ) ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><?php esc_html_e( 'System Email', 'disciple_tools' )?></td>
                                    <td><span data-tooltip data-click-open="true" class="top" tabindex="3" title="<?php esc_html_e( 'User email can be changed by site administrator.', 'disciple_tools' ) ?>">
                                        <input type="text" class="profile-input" id="user_email"
                                            name="user_email"
                                            value="<?php echo esc_html( $dt_user->user_email ); ?>"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td><?php esc_html_e( 'Password', 'disciple_tools' )?></td>
                                    <td><span data-tooltip data-click-open="true" class="top" tabindex="1" title="<?php esc_html_e( 'Use this email reset form to create a new password.', 'disciple_tools' ) ?>">
                                            <a href="<?php echo esc_url( wp_logout_url( '/wp-login.php?action=lostpassword' ) ); ?>" target="_blank" rel="nofollow noopener">
                                                <?php esc_html_e( 'go to password change form', 'disciple_tools' ) ?> <i class="fi-link"></i>
                                            </a>
                                        </span>
                                    </td>
                                </tr>

                                <tr>
                                    <td><label for="first_name"><?php esc_html_e( 'First Name', 'disciple_tools' )?></label></td>
                                    <td><input type="text" class="profile-input" id="first_name"
                                               name="first_name"
                                               value="<?php echo esc_html( $dt_user->first_name ); ?>"/></td>
                                </tr>
                                <tr>
                                    <td><label for="last_name"><?php esc_html_e( 'Last Name', 'disciple_tools' )?></label></td>
                                    <td><input type="text" class="profile-input" id="last_name" name="last_name"
                                               value="<?php echo esc_html( $dt_user->last_name ); ?>"/></td>
                                </tr>
                                <tr>
                                    <td><label for="nickname"><span dir="auto"><?php esc_html_e( 'Nickname (Display Name)', 'disciple_tools' )?></span></label></td>
                                    <td><input type="text" class="profile-input" id="nickname" name="nickname" dir="auto"
                                               value=" <?php echo esc_html( $dt_user->nickname ); ?>"/></td>
                                </tr>

                                <?php // site defined fields
                                foreach ( $dt_user_fields as $dt_field ) {
                                    ?>
                                    <tr>
                                        <td>
                                            <label for="<?php echo esc_attr( $dt_field['key'] ) ?>"><?php echo esc_html( $dt_field['label'] ) ?></label>
                                        </td>
                                        <td><input type="text"
                                                   class="profile-input"
                                                   id="<?php echo esc_attr( $dt_field['key'] ) ?>"
                                                   name="<?php echo esc_attr( $dt_field['key'] ) ?>"
                                                   value="<?php echo esc_html( $dt_field['value'] ) ?>"/>
                                        </td>
                                    </tr>
                                    <?php
                                } // end foreach
                                ?>

                                <tr>
                                    <td><label for="description"><?php esc_html_e( 'Biography', 'disciple_tools' )?></label></td>
                                    <td><textarea type="text" class="profile-input" id="description"
                                                  name="description"
                                                  rows="5"><?php echo esc_html( $dt_user->description ); ?></textarea>
                                    </td>
                                </tr>
                                <tr>
                                    <td><label for="gender">
                                        <?php esc_html_e( 'Gender', 'disciple_tools' ) ?>
                                    </label></td>
                                    <td>
                                        <select class="select-field" id="<?php echo esc_html( $field_key ); ?>" style="width:auto; display: block">
                                            <?php foreach ($contact_fields[$field_key]["default"] as $option_key => $option_value):
                                                $selected = $user_field === $option_key; ?>
                                                <option value="<?php echo esc_html( $option_key )?>" <?php echo esc_html( $selected ? "selected" : "" )?>>
                                                <?php echo esc_html( $option_value["label"] ) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td><label for="systemLanguage"><?php esc_html_e( 'System Language', 'disciple_tools' )?></label></td>
                                    <td dir="auto">
                                        <?php
                                        wp_dropdown_languages( array(
                                            'name'                        => 'locale',
                                            'id'                          => 'locale',
                                            'selected'                    => esc_html( $dt_user_locale ),
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

                            <div class="alert alert-box" style="display:none;" id="alert">
                                <strong><?php echo esc_html( 'Oh snap!' ) ?></strong>
                            </div>

                            <button class="button" type="submit"><?php esc_html_e( 'Save', 'disciple_tools' )?></button>

                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div> <!-- end #inner-content -->
</div> <!-- end #content -->

<?php get_footer(); ?>
