<?php
/**
 * Plugin Name:       Simple Time Tracking
 * Plugin URI:        https://capture.club/plugins/simple_time_tracker/
 * Description:       Simple time-tracking plugin with activity dropdown, date picker, and admin visualization and data aggregation.
 * Version:           1.5.0
 * Author:            Kevin Cowan
 * Author URI:        https://kevinmcowan.com
 * Text Domain:       time-tracking-plugin
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Activation hook: create a "Log Time" page with form shortcode and "My Time Entries" page
 */
function ttp_activate_plugin() {
    // Create "Log Time" page
    $log_page_title   = 'Log Time';
    $log_page_content = '[time_entry_form]';
    $log_page_check   = get_page_by_title( $log_page_title, OBJECT, 'page' );

    if ( ! $log_page_check ) {
        $log_page_id = wp_insert_post( array(
            'post_title'   => $log_page_title,
            'post_content' => $log_page_content,
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ) );
        if ( ! is_wp_error( $log_page_id ) ) {
            update_option( 'ttp_time_entry_page_id', $log_page_id );
        }
    }

    // Create "My Time Entries" page
    $my_entries_title   = 'My Time Entries';
    $my_entries_content = '[my_time_entries]';
    $my_entries_check   = get_page_by_title( $my_entries_title, OBJECT, 'page' );

    if ( ! $my_entries_check ) {
        $my_entries_id = wp_insert_post( array(
            'post_title'   => $my_entries_title,
            'post_content' => $my_entries_content,
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ) );
        if ( ! is_wp_error( $my_entries_id ) ) {
            update_option( 'ttp_my_entries_page_id', $my_entries_id );
        }
    }
}
register_activation_hook( __FILE__, 'ttp_activate_plugin' );

/**
 * Deactivation hook: optionally remove created pages
 */
function ttp_deactivate_plugin() {
    $log_page_id = get_option( 'ttp_time_entry_page_id' );
    if ( $log_page_id ) {
        wp_delete_post( $log_page_id, true );
        delete_option( 'ttp_time_entry_page_id' );
    }

    $my_entries_page_id = get_option( 'ttp_my_entries_page_id' );
    if ( $my_entries_page_id ) {
        wp_delete_post( $my_entries_page_id, true );
        delete_option( 'ttp_my_entries_page_id' );
    }
}
register_deactivation_hook( __FILE__, 'ttp_deactivate_plugin' );

/**
 * Register the "time_entry" custom post type
 */
function ttp_register_time_entry_cpt() {
    $labels = array(
        'name'               => __( 'Time Entries', 'time-tracking-plugin' ),
        'singular_name'      => __( 'Time Entry', 'time-tracking-plugin' ),
        'menu_name'          => __( 'Time Entries', 'time-tracking-plugin' ),
        'name_admin_bar'     => __( 'Time Entry', 'time-tracking-plugin' ),
    );
    $args = array(
        'labels'             => $labels,
        'public'             => false,
        'show_ui'            => false,
        'capability_type'    => 'post',
        'supports'           => array( 'title' ),
    );
    register_post_type( 'time_entry', $args );
}
add_action( 'init', 'ttp_register_time_entry_cpt' );

/**
 * Enqueue jQuery UI Datepicker on the front-end
 */
function ttp_enqueue_frontend_scripts() {
    if ( ! is_admin() ) {
        wp_enqueue_script( 'jquery-ui-datepicker' );
        wp_enqueue_style( 'jquery-ui-css', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css' );
        $inline_js = "jQuery(function($){ $('#ttp_entry_date').datepicker({ dateFormat: 'yy-mm-dd' }); });";
        wp_add_inline_script( 'jquery-ui-datepicker', $inline_js );
    }
}
add_action( 'wp_enqueue_scripts', 'ttp_enqueue_frontend_scripts' );

/**
 * Shortcode to display the front-end time entry form
 * Usage: [time_entry_form]
 */
function ttp_time_entry_form_shortcode() {
    if ( isset($_POST['ttp_submit']) && ! is_user_logged_in() ) {
        //return '<p>' . __( 'Please <a href="' . wp_login_url( get_permalink() ) . '">log in</a> to record time.', 'time-tracking-plugin' ) . '</p>';
    }

    $activities = array(
        'Development',
        'Meeting',
        'Research',
        'Design',
        'Testing',
        'Fundraising',
        'Community Outreach',
        'Event Planning',
        'Volunteer Training',
        'Maintenance',
        'Administrative Work',
    );
    $activities[]  = 'Other';
    $date_default  = date_i18n( 'Y-m-d' );

    ob_start();
    $img_url = plugin_dir_url( __FILE__ ) . 'assets/imgs/time_tracker_logo.png';
    echo '<img src="' . esc_url( $img_url ) . '" alt="Time Tracker Logo" class="ttp-logo" />';

    ?>

    <form method="post" class="ttp-form" >
        <?php wp_nonce_field( 'ttp_time_entry', 'ttp_nonce' ); ?>

        <p>
            <label for="ttp_entry_date"><?php _e( 'Date:', 'time-tracking-plugin' ); ?></label>
            <input type="text" name="ttp_entry_date" id="ttp_entry_date" value="<?php echo esc_attr( $date_default ); ?>" required>
        </p>

        <p>
            <label for="ttp_activity"><?php _e( 'Activity:', 'time-tracking-plugin' ); ?></label>
            <select name="ttp_activity" id="ttp_activity">
                <?php foreach ( $activities as $activity ) : ?>
                    <option value="<?php echo esc_attr( $activity ); ?>"><?php echo esc_html( $activity ); ?></option>
                <?php endforeach; ?>
            </select>
        </p>

        <p id="ttp_activity_other_wrap" style="display:none;">
            <label for="ttp_activity_other"><?php _e( 'Please specify:', 'time-tracking-plugin' ); ?></label>
            <input type="text" name="ttp_activity_other" id="ttp_activity_other" maxlength="100" onblur="updateActivity()">
        </p>

        <p>
            <label for="ttp_time_spent"><?php _e( 'Time (hours):', 'time-tracking-plugin' ); ?></label>
            <input type="number" step="0.25" min="0.25" name="ttp_time_spent" id="ttp_time_spent" required>
        </p>

        <p>
            <label for="ttp_notes"><?php _e( 'Notes (optional):', 'time-tracking-plugin' ); ?></label><br>
            <textarea id="ttp_notes" name="ttp_notes" rows="3" cols="30"><?php echo esc_textarea( $_POST['ttp_notes'] ?? '' ); ?></textarea>
        </p>


        <p>
            <button type="submit" name="ttp_submit"><?php esc_html_e( 'Log Time', 'time-tracking-plugin' ); ?></button>
        </p>
    </form>

    <script>
        function updateActivity(){
            const activitySelect = document.getElementById('ttp_activity');
            const otherInput = document.getElementById('ttp_activity_other');
            if (activitySelect.value === 'Other' &&
                otherInput.value.trim() !== '') {
                const custom = otherInput.value.trim();
                if (!custom) {
                    alert('Please specify an "Other" activity.');
                    otherInput.focus();
                    return false;
                }
                const newOption = new Option(custom, custom);
                activitySelect.add(newOption);
                activitySelect.value = newOption.value;;

            }
            return true;
        }
        jQuery(function($){
            $('#ttp_activity').on('change', function(){
                $('#ttp_activity_other_wrap').toggle( $(this).val() === 'Other' );
            }).trigger('change');
        });
        document.addEventListener('DOMContentLoaded', function() {
            // clear the form
            const form = document.querySelector('.ttp-form');
            form.reset();
            let notes = document.getElementById('ttp_notes');
            notes.value = "";
        });

    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'time_entry_form', 'ttp_time_entry_form_shortcode' );

/**
 * Shortcode to display user's own time entries
 * Usage: [my_time_entries]
 */
function ttp_my_time_entries_shortcode( $atts ) {
    if ( !is_user_logged_in() ) {
        //return '<p>' . __( 'Please <a href="' . wp_login_url( get_permalink() ) . '">log in</a> to view your time entries.', 'time-tracking-plugin' ) . '</p>';
    }

    $atts = shortcode_atts( array(
        'per_page' => 20,
        'show_totals' => 'yes'
    ), $atts );

    $current_user_id = get_current_user_id();

    // Handle sorting parameters
    $sort_by = isset( $_GET['sort_by'] ) ? sanitize_text_field( $_GET['sort_by'] ) : 'date';
    $sort_order = isset( $_GET['sort_order'] ) ? sanitize_text_field( $_GET['sort_order'] ) : 'DESC';

    // Build query for user's entries
    $query_args = array(
        'post_type' => 'time_entry',
        'posts_per_page' => intval( $atts['per_page'] ),
        'post_status' => 'publish',
        'meta_query' => array(
            array(
                'key' => 'user_id',
                'value' => $current_user_id,
                'compare' => '='
            )
        )
    );

    // Handle sorting
    switch ( $sort_by ) {
        case 'activity':
            $query_args['meta_key'] = 'activity';
            $query_args['orderby'] = 'meta_value';
            $query_args['order'] = $sort_order;
            break;
        case 'hours':
            $query_args['meta_key'] = 'time_spent';
            $query_args['orderby'] = 'meta_value_num';
            $query_args['order'] = $sort_order;
            break;
        case 'entry_date':
            $query_args['meta_key'] = 'entry_date';
            $query_args['orderby'] = 'meta_value';
            $query_args['order'] = $sort_order;
            break;
        default:
            $query_args['orderby'] = 'date';
            $query_args['order'] = $sort_order;
            break;
    }

    $user_entries = new WP_Query( $query_args );

    ob_start();

    // Display logo
    $img_url = plugin_dir_url( __FILE__ ) . 'assets/imgs/time_tracker_logo.png';
    echo '<img src="' . esc_url( $img_url ) . '" alt="Time Tracker Logo" class="ttp-logo" />';

    echo '<div class="ttp-my-entries">';
    echo '<h2>' . esc_html__( 'My Time Entries', 'time-tracking-plugin' ) . '</h2>';

    // Show totals if enabled
    if ( $atts['show_totals'] === 'yes' ) {
        $total_query = new WP_Query( array(
            'post_type' => 'time_entry',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => 'user_id',
                    'value' => $current_user_id,
                    'compare' => '='
                )
            )
        ) );

        $total_hours = 0;
        $activity_totals = array();

        while ( $total_query->have_posts() ) {
            $total_query->the_post();
            $hours = floatval( get_post_meta( get_the_ID(), 'time_spent', true ) );
            $activity = get_post_meta( get_the_ID(), 'activity', true );

            $total_hours += $hours;
            if ( ! isset( $activity_totals[ $activity ] ) ) {
                $activity_totals[ $activity ] = 0;
            }
            $activity_totals[ $activity ] += $hours;
        }
        wp_reset_postdata();

        echo '<div class="ttp-totals-summary">';
        echo '<h3>' . esc_html__( 'Summary', 'time-tracking-plugin' ) . '</h3>';
        echo '<p><strong>' . esc_html__( 'Total Hours Logged:', 'time-tracking-plugin' ) . '</strong> ' . esc_html( round( $total_hours, 2 ) ) . '</p>';

        if ( ! empty( $activity_totals ) ) {
            echo '<p><strong>' . esc_html__( 'By Activity:', 'time-tracking-plugin' ) . '</strong></p>';
            echo '<ul class="ttp-activity-totals">';
            arsort( $activity_totals );
            foreach ( $activity_totals as $activity => $hours ) {
                echo '<li>' . esc_html( $activity ) . ': ' . esc_html( round( $hours, 2 ) ) . ' ' . esc_html__( 'hours', 'time-tracking-plugin' ) . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
    }

    // Sorting controls
    echo '<div class="ttp-user-controls">';
    echo '<form method="get" style="display: inline-block; margin-bottom: 15px;">';

    // Preserve existing query parameters
    foreach ( $_GET as $key => $value ) {
        if ( ! in_array( $key, array( 'sort_by', 'sort_order' ) ) ) {
            echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
        }
    }

    echo '<label for="user_sort_by">' . esc_html__( 'Sort by:', 'time-tracking-plugin' ) . '</label> ';
    echo '<select name="sort_by" id="user_sort_by">';
    echo '<option value="date"' . selected( $sort_by, 'date', false ) . '>' . esc_html__( 'Most Recent', 'time-tracking-plugin' ) . '</option>';
    echo '<option value="entry_date"' . selected( $sort_by, 'entry_date', false ) . '>' . esc_html__( 'Entry Date', 'time-tracking-plugin' ) . '</option>';
    echo '<option value="activity"' . selected( $sort_by, 'activity', false ) . '>' . esc_html__( 'Activity', 'time-tracking-plugin' ) . '</option>';
    echo '<option value="hours"' . selected( $sort_by, 'hours', false ) . '>' . esc_html__( 'Hours', 'time-tracking-plugin' ) . '</option>';
    echo '</select> ';

    echo '<select name="sort_order">';
    echo '<option value="DESC"' . selected( $sort_order, 'DESC', false ) . '>' . esc_html__( 'Descending', 'time-tracking-plugin' ) . '</option>';
    echo '<option value="ASC"' . selected( $sort_order, 'ASC', false ) . '>' . esc_html__( 'Ascending', 'time-tracking-plugin' ) . '</option>';
    echo '</select> ';

    echo '<input type="submit" class="button" value="' . esc_attr__( 'Sort', 'time-tracking-plugin' ) . '" />';
    echo '</form>';
    echo '</div>';

    // Entries table
    if ( $user_entries->have_posts() ) {
        echo '<table class="ttp-user-entries-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . esc_html__( 'Date', 'time-tracking-plugin' ) . '</th>';
        echo '<th>' . esc_html__( 'Activity', 'time-tracking-plugin' ) . '</th>';
        echo '<th>' . esc_html__( 'Hours', 'time-tracking-plugin' ) . '</th>';
        echo '<th>' . esc_html__( 'Notes', 'time-tracking-plugin' ) . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        while ( $user_entries->have_posts() ) {
            $user_entries->the_post();
            $entry_date = get_post_meta( get_the_ID(), 'entry_date', true );
            $activity = get_post_meta( get_the_ID(), 'activity', true );
            $hours = get_post_meta( get_the_ID(), 'time_spent', true );
            $notes = get_post_meta( get_the_ID(), 'notes', true );

            echo '<tr>';
            echo '<td>' . esc_html( $entry_date ) . '</td>';
            echo '<td>' . esc_html( $activity ) . '</td>';
            echo '<td>' . esc_html( $hours ) . '</td>';
            echo '<td>' . esc_html( $notes ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

        // Pagination info
        $total_entries = $user_entries->found_posts;
        $showing_count = $user_entries->post_count;
        if ( $total_entries > $showing_count ) {
            echo '<p class="ttp-pagination-info">';
            echo sprintf(
                esc_html__( 'Showing %d of %d entries', 'time-tracking-plugin' ),
                $showing_count,
                $total_entries
            );
            echo '</p>';
        }

    } else {
        echo '<p>' . esc_html__( 'No time entries found. Start by logging some time!', 'time-tracking-plugin' ) . '</p>';
        $log_page_id = get_option( 'ttp_time_entry_page_id' );
        if ( $log_page_id ) {
            $log_page_url = get_permalink( $log_page_id );
            echo '<p><a href="' . esc_url( $log_page_url ) . '" class="button">' . esc_html__( 'Log Time', 'time-tracking-plugin' ) . '</a></p>';
        }
    }

    wp_reset_postdata();
    echo '</div>';

    return ob_get_clean();
}
add_shortcode( 'my_time_entries', 'ttp_my_time_entries_shortcode' );

/**
 * Handle front-end form submission and save as a custom post
 */
function ttp_handle_time_entry_submission() {


    // security & auth checks
    if ( isset($_POST['ttp_submit']) && !is_user_logged_in()) {
        return '<p>' . __( 'Please <a href="' . wp_login_url( get_permalink() ) . '">log in</a> to view your time entries.', 'time-tracking-plugin' ) . '</p>';

    }

    if ( isset( $_POST['ttp_submit'] ) ) {
        // sanitize inputs
        $user_id = get_current_user_id();
        $entry_date = sanitize_text_field($_POST['ttp_entry_date']);
        $activity = sanitize_text_field($_POST['ttp_activity']);
        $time_spent = floatval($_POST['ttp_time_spent']);
        $notes = isset( $_POST['ttp_notes'] )
            ? sanitize_textarea_field( $_POST['ttp_notes'] )
            : '';


        // don't save zero or negative values
        if ($time_spent <= 0) {
            return;
        }

        // create the time_entry post
        $post_id = wp_insert_post([
            'post_type' => 'time_entry',
            'post_title' => $entry_date . ' – ' . $activity,
            'post_status' => 'publish',
        ]);

        if (!is_wp_error($post_id)) {
            update_post_meta($post_id, 'user_id', $user_id);
            update_post_meta($post_id, 'entry_date', $entry_date);
            update_post_meta($post_id, 'activity', $activity);
            update_post_meta($post_id, 'time_spent', $time_spent);
            update_post_meta($post_id, 'notes', $notes );
        }
    }
}
add_action( 'init', 'ttp_handle_time_entry_submission' );

/**
 * Add the "Time Tracking" admin menu page
 */
function ttp_add_admin_menu() {
    add_menu_page(
        __( 'Time Tracking', 'time-tracking-plugin' ),
        __( 'Time Tracking', 'time-tracking-plugin' ),
        'manage_options',
        'time-tracking',
        'ttp_admin_page_callback',
        'dashicons-clock',
        6
    );
}
add_action( 'admin_menu', 'ttp_add_admin_menu' );

/**
 * Handle CSV export
 */
function ttp_handle_csv_export() {
    if ( ! isset( $_GET['ttp_action'] ) || $_GET['ttp_action'] !== 'export_csv' ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.', 'time-tracking-plugin' ) );
    }

    // Get sorting parameters
    $sort_by = isset( $_GET['sort_by'] ) ? sanitize_text_field( $_GET['sort_by'] ) : 'date';
    $sort_order = isset( $_GET['sort_order'] ) ? sanitize_text_field( $_GET['sort_order'] ) : 'DESC';

    // Build query arguments
    $query_args = array(
        'post_type' => 'time_entry',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    );

    // Handle sorting
    switch ( $sort_by ) {
        case 'user':
            $query_args['meta_key'] = 'user_id';
            $query_args['orderby'] = 'meta_value_num';
            $query_args['order'] = $sort_order;
            break;
        case 'activity':
            $query_args['meta_key'] = 'activity';
            $query_args['orderby'] = 'meta_value';
            $query_args['order'] = $sort_order;
            break;
        case 'hours':
            $query_args['meta_key'] = 'time_spent';
            $query_args['orderby'] = 'meta_value_num';
            $query_args['order'] = $sort_order;
            break;
        case 'entry_date':
            $query_args['meta_key'] = 'entry_date';
            $query_args['orderby'] = 'meta_value';
            $query_args['order'] = $sort_order;
            break;
        default:
            $query_args['orderby'] = 'date';
            $query_args['order'] = $sort_order;
            break;
    }

    $entries = new WP_Query( $query_args );

    // Set headers for CSV download
    header( 'Content-Type: text/csv' );
    header( 'Content-Disposition: attachment; filename="time-entries-' . date( 'Y-m-d' ) . '.csv"' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );

    // Open output stream
    $output = fopen( 'php://output', 'w' );

    // Add CSV headers
    fputcsv( $output, array(
        __( 'Date', 'time-tracking-plugin' ),
        __( 'User', 'time-tracking-plugin' ),
        __( 'Activity', 'time-tracking-plugin' ),
        __( 'Hours', 'time-tracking-plugin' ),
        __( 'Notes', 'time-tracking-plugin' ),
        __( 'Created', 'time-tracking-plugin' )
    ) );

    // Add data rows
    if ( $entries->have_posts() ) {
        while ( $entries->have_posts() ) {
            $entries->the_post();
            $post_id = get_the_ID();
            $user_id = get_post_meta( $post_id, 'user_id', true );
            $user = get_userdata( $user_id );
            $user_name = $user ? $user->display_name : __( 'Unknown User', 'time-tracking-plugin' );

            fputcsv( $output, array(
                get_post_meta( $post_id, 'entry_date', true ),
                $user_name,
                get_post_meta( $post_id, 'activity', true ),
                get_post_meta( $post_id, 'time_spent', true ),
                get_post_meta( $post_id, 'notes', true ),
                get_the_date( 'Y-m-d H:i:s' )
            ) );
        }
    }

    wp_reset_postdata();
    fclose( $output );
    exit;
}
add_action( 'admin_init', 'ttp_handle_csv_export' );

/**
 * Enqueue Chart.js on the admin page
 */
function ttp_enqueue_admin_scripts( $hook ) {
    if ( $hook !== 'toplevel_page_time-tracking' ) {
        return;
    }
    wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true );

    // Add admin styles for sorting and export
    wp_add_inline_style( 'wp-admin', '
        .ttp-controls {
            margin: 20px 0;
            padding: 15px;
            background: #f1f1f1;
            border-radius: 5px;
        }
        .ttp-controls label {
            margin-right: 10px;
            font-weight: bold;
        }
        .ttp-controls select, .ttp-controls .button {
            margin-right: 15px;
        }
        .ttp-sortable th {
            cursor: pointer;
            position: relative;
        }
        .ttp-sortable th:hover {
            background-color: #f0f0f1;
        }
        .ttp-sortable th.sorted-asc::after {
            content: " ↑";
            color: #0073aa;
        }
        .ttp-sortable th.sorted-desc::after {
            content: " ↓";
            color: #0073aa;
        }
    ' );
}
add_action( 'admin_enqueue_scripts', 'ttp_enqueue_admin_scripts' );

/**
 * Render the admin page: chart + recent entries table
 */
function ttp_admin_page_callback() {
    echo "<style>";
    echo '.ttp-logo { ';
    //echo '   background: url(' ../images / logo . png') no-repeat center;\n';
    echo 'width: 25%;';
    //echo 'height: 100px;';
    echo '}';
    echo "</style>";
    $img_url = plugin_dir_url( __FILE__ ) . 'assets/imgs/time_tracker_logo.png';
    echo '<img src="' . esc_url( $img_url ) . '" alt="Time Tracker Logo" class="ttp-logo" />';
    echo '<div class="wrap"><h1>' . esc_html__( 'Time Tracking Summary', 'time-tracking-plugin' ) . '</h1>';

    echo '<p>' . esc_html__( 'Use this plugin to allow your team to log hours against predefined activities and review summaries in the admin.', 'time-tracking-plugin' ) . '</p>';
    echo '<h2>' . esc_html__( 'Front-end Usage', 'time-tracking-plugin' ) . '</h2>';
    echo '<ul>';
    echo '<li>' . esc_html__( 'Authentication required: only logged-in users can submit entries.', 'time-tracking-plugin' ) . '</li>';
    echo '<li>' . esc_html__( 'Shortcode: add [time_entry_form] to any page.', 'time-tracking-plugin' ) . '</li>';
    echo '<li>' . esc_html__( 'User entries: add [my_time_entries] to show individual user summaries.', 'time-tracking-plugin' ) . '</li>';
    echo '<li>' . esc_html__( 'Date picker, activity dropdown, and hours input included.', 'time-tracking-plugin' ) . '</li>';
    echo '</ul>';
    echo '<h2>' . esc_html__( 'Admin Interface', 'time-tracking-plugin' ) . '</h2>';
    echo '<ul>';
    echo '<li>' . esc_html__( 'Summary chart: aggregate hours per activity.', 'time-tracking-plugin' ) . '</li>';
    echo '<li>' . esc_html__( 'Recent entries: view last 10 submissions.', 'time-tracking-plugin' ) . '</li>';
    echo '<li>' . esc_html__( 'Plugin creates "Log Time" and "My Time Entries" pages on activation.', 'time-tracking-plugin' ) . '</li>';
    echo '</ul>';
    echo '</div>';


    // Query and aggregate
    $query = new WP_Query(array('post_type'=>'time_entry','posts_per_page'=>-1,'post_status'=>'publish'));
    $data = array();
    while($query->have_posts()){ $query->the_post();
        $act = get_post_meta(get_the_ID(),'activity',true);
        $hrs = floatval(get_post_meta(get_the_ID(),'time_spent',true));
        $data[$act] = (!isset($data[$act])?0: $data[$act]) + $hrs;
    }
    wp_reset_postdata();
    $labels = json_encode(array_keys($data));
    $values = json_encode(array_values($data));
    ?>
    <canvas id="ttpTimeChart" width="400" height="200"></canvas>

    <?php
    // Get current sorting parameters
    $current_sort = isset( $_GET['sort_by'] ) ? sanitize_text_field( $_GET['sort_by'] ) : 'date';
    $current_order = isset( $_GET['sort_order'] ) ? sanitize_text_field( $_GET['sort_order'] ) : 'DESC';
    ?>

    <div class="ttp-controls">
        <form method="get" style="display: inline-block;">
            <input type="hidden" name="page" value="time-tracking" />
            <label for="sort_by"><?php _e( 'Sort by:', 'time-tracking-plugin' ); ?></label>
            <select name="sort_by" id="sort_by">
                <option value="date" <?php selected( $current_sort, 'date' ); ?>><?php _e( 'Created Date', 'time-tracking-plugin' ); ?></option>
                <option value="entry_date" <?php selected( $current_sort, 'entry_date' ); ?>><?php _e( 'Entry Date', 'time-tracking-plugin' ); ?></option>
                <option value="user" <?php selected( $current_sort, 'user' ); ?>><?php _e( 'User', 'time-tracking-plugin' ); ?></option>
                <option value="activity" <?php selected( $current_sort, 'activity' ); ?>><?php _e( 'Activity', 'time-tracking-plugin' ); ?></option>
                <option value="hours" <?php selected( $current_sort, 'hours' ); ?>><?php _e( 'Hours', 'time-tracking-plugin' ); ?></option>
            </select>

            <label for="sort_order"><?php _e( 'Order:', 'time-tracking-plugin' ); ?></label>
            <select name="sort_order" id="sort_order">
                <option value="DESC" <?php selected( $current_order, 'DESC' ); ?>><?php _e( 'Descending', 'time-tracking-plugin' ); ?></option>
                <option value="ASC" <?php selected( $current_order, 'ASC' ); ?>><?php _e( 'Ascending', 'time-tracking-plugin' ); ?></option>
            </select>

            <input type="submit" class="button" value="<?php esc_attr_e( 'Sort', 'time-tracking-plugin' ); ?>" />
        </form>

        <a href="<?php echo esc_url( add_query_arg( array(
            'ttp_action' => 'export_csv',
            'sort_by' => $current_sort,
            'sort_order' => $current_order
        ) ) ); ?>" class="button button-primary">
            <?php _e( 'Export CSV', 'time-tracking-plugin' ); ?>
        </a>
    </div>

    <h2><?php _e('Recent Entries', 'time-tracking-plugin'); ?></h2>
    <table class="wp-list-table widefat fixed striped ttp-sortable">
        <thead>
        <tr>
            <th class="<?php echo $current_sort === 'entry_date' ? 'sorted-' . strtolower($current_order) : ''; ?>">
                <?php _e('Date','time-tracking-plugin'); ?>
            </th>
            <th class="<?php echo $current_sort === 'user' ? 'sorted-' . strtolower($current_order) : ''; ?>">
                <?php _e('User','time-tracking-plugin'); ?>
            </th>
            <th class="<?php echo $current_sort === 'activity' ? 'sorted-' . strtolower($current_order) : ''; ?>">
                <?php _e('Activity','time-tracking-plugin'); ?>
            </th>
            <th class="<?php echo $current_sort === 'hours' ? 'sorted-' . strtolower($current_order) : ''; ?>">
                <?php _e('Hours','time-tracking-plugin'); ?>
            </th>
            <th><?php _e('Notes','time-tracking-plugin'); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php
        // Build query for sorted results
        $query_args = array(
            'post_type' => 'time_entry',
            'posts_per_page' => 25, // Show more entries when sorted
            'post_status' => 'publish'
        );

        // Handle sorting
        switch ( $current_sort ) {
            case 'user':
                $query_args['meta_key'] = 'user_id';
                $query_args['orderby'] = 'meta_value_num';
                $query_args['order'] = $current_order;
                break;
            case 'activity':
                $query_args['meta_key'] = 'activity';
                $query_args['orderby'] = 'meta_value';
                $query_args['order'] = $current_order;
                break;
            case 'hours':
                $query_args['meta_key'] = 'time_spent';
                $query_args['orderby'] = 'meta_value_num';
                $query_args['order'] = $current_order;
                break;
            case 'entry_date':
                $query_args['meta_key'] = 'entry_date';
                $query_args['orderby'] = 'meta_value';
                $query_args['order'] = $current_order;
                break;
            default:
                $query_args['orderby'] = 'date';
                $query_args['order'] = $current_order;
                break;
        }

        $recent = new WP_Query( $query_args );

        if($recent->have_posts()): while($recent->have_posts()): $recent->the_post();
            $date = get_post_meta(get_the_ID(),'entry_date',true);
            $user = get_userdata(get_post_meta(get_the_ID(),'user_id',true));
            $act  = get_post_meta(get_the_ID(),'activity',true);
            $hrs  = get_post_meta(get_the_ID(),'time_spent',true);
            $notes = get_post_meta(get_the_ID(),'notes',true);

            echo '<tr><td>'.esc_html($date).'</td><td>'.esc_html($user->display_name).'</td><td>'.esc_html($act).'</td><td>'.esc_html($hrs).'</td><td>'.esc_html($notes).'</td></tr>';

        endwhile; else:
            echo '<tr><td colspan="5">'.__('No entries found.','time-tracking-plugin').'</td></tr>';
        endif;
        wp_reset_postdata();
        ?>
        </tbody>
    </table>
    <br/><hr/>
    <h2><?php _e('Activity Summary', 'time-tracking-plugin'); ?></h2>
    <?php echo ttp_render_time_rollup_table(); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('ttpTimeChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo $labels; ?>,
                    datasets: [{
                        label: <?php echo wp_json_encode( __( 'Hours', 'time-tracking-plugin' ) ); ?>,
                        data: <?php echo $values; ?>
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: <?php echo wp_json_encode( __( 'Hours', 'time-tracking-plugin' ) ); ?>
                            }
                        }
                    }
                }
            });
        });
    </script>

    <?php
    echo '</div>';
}

function ttp_enqueue_frontend_assets() {
    $base = plugin_dir_url( __FILE__ ) . 'assets/';

    // CSS
    wp_enqueue_style(
        'ttp-frontend',
        $base . 'css/frontend.css',
        array(),
        '1.0'
    );

    // Add inline styles for user entries page
    wp_add_inline_style( 'ttp-frontend', '
        .ttp-my-entries {
            max-width: 100%;
            margin: 20px 0;
        }
        .ttp-totals-summary {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #0073aa;
        }
        .ttp-totals-summary h3 {
            margin-top: 0;
            color: #0073aa;
        }
        .ttp-activity-totals {
            list-style-type: none;
            padding-left: 20px;
        }
        .ttp-activity-totals li {
            margin-bottom: 5px;
            padding: 3px 0;
        }
        .ttp-user-controls {
            margin-bottom: 20px;
            padding: 10px;
            background: #f1f1f1;
            border-radius: 3px;
        }
        .ttp-user-controls label {
            font-weight: bold;
            margin-right: 8px;
        }
        .ttp-user-controls select {
            margin-right: 10px;
        }
        .ttp-user-entries-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .ttp-user-entries-table th,
        .ttp-user-entries-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .ttp-user-entries-table th {
            background-color: #f9f9f9;
            font-weight: bold;
        }
        .ttp-user-entries-table tr:hover {
            background-color: #f5f5f5;
        }
        .ttp-pagination-info {
            font-style: italic;
            color: #666;
            text-align: center;
        }
        @media (max-width: 768px) {
            .ttp-user-entries-table {
                font-size: 14px;
            }
            .ttp-user-entries-table th,
            .ttp-user-entries-table td {
                padding: 8px 5px;
            }
        }
    ' );

    // JS (if needed)
    /*
    wp_enqueue_script(
        'ttp-frontend',
        $base . 'js/frontend.js',
        array( 'jquery' ),
        '1.0',
        true
    );
    */
}
add_action( 'wp_enqueue_scripts', 'ttp_enqueue_frontend_assets' );

/**
Helper functions
 */
/**
 * Get a roll-up of time entries grouped by a given meta key.
 *
 * @param string $group_by Meta key to group by (e.g. 'activity', 'entry_date', 'user_id').
 * @return array Associative array: group_value => total_hours.
 */
function ttp_get_time_rollup( $group_by = 'activity' ) {
    // Fetch all time_entry IDs
    $entries = get_posts( array(
        'post_type'      => 'time_entry',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'fields'         => 'ids',
    ) );

    $rollup = array();
    foreach ( $entries as $post_id ) {
        $key   = get_post_meta( $post_id, $group_by, true );
        $hours = floatval( get_post_meta( $post_id, 'time_spent', true ) );
        if ( $key === '' ) {
            $key = __( 'Unspecified', 'time-tracking-plugin' );
        }
        if ( ! isset( $rollup[ $key ] ) ) {
            $rollup[ $key ] = 0;
        }
        $rollup[ $key ] += $hours;
    }

    // Sort descending by hours
    arsort( $rollup );
    return $rollup;
}

/**
 * Render a roll-up table in admin.
 *
 * @param string $group_by Meta key to group by (default 'activity').
 * @param string $group_label Column heading for the group (e.g. 'Activity', 'Date', 'User').
 */
function ttp_render_time_rollup_table( $group_by = 'activity', $group_label = '' ) {
    if ( empty( $group_label ) ) {
        // Humanize the key if no label provided
        $group_label = ucwords( str_replace( '_', ' ', $group_by ) );
    }

    $rollup = ttp_get_time_rollup( $group_by );

    echo '<h2>' . esc_html( sprintf(
        /* translators: %s: group label */
            __( '%s Summary', 'time-tracking-plugin' ),
            $group_label
        ) ) . '</h2>';

    echo '<table class="widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>' . esc_html( $group_label ) . '</th>';
    echo '<th>' . esc_html__( 'Total Hours', 'time-tracking-plugin' ) . '</th>';
    echo '</tr></thead><tbody>';

    if ( empty( $rollup ) ) {
        echo '<tr><td colspan="2">' . esc_html__( 'No entries found.', 'time-tracking-plugin' ) . '</td></tr>';
    } else {
        foreach ( $rollup as $key => $total ) {
            echo '<tr>';
            echo '<td>' . esc_html( $key ) . '</td>';
            echo '<td>' . esc_html( round( $total, 2 ) ) . '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';
}
