<?php
/**
 * Plugin Name:       Simple Time Tracking
 * Plugin URI:        https://capture.club/plugins/simple_time_tracker/
 * Description:       Simple time-tracking plugin with activity dropdown, date picker, and admin visualization and data aggregation.
 * Version:           1.3.0
 * Author:            Kevin Cowan
 * Author URI:        https://kevinmcowan.com
 * Text Domain:       time-tracking-plugin
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Activation hook: create a "Log Time" page with form shortcode
 */
function ttp_activate_plugin() {
    // Check if page exists
    $page_title   = 'Log Time';
    $page_content = '[time_entry_form]';
    $page_check   = get_page_by_title( $page_title, OBJECT, 'page' );

    if ( ! $page_check ) {
        $page_id = wp_insert_post( array(
            'post_title'   => $page_title,
            'post_content' => $page_content,
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ) );
        if ( ! is_wp_error( $page_id ) ) {
            update_option( 'ttp_time_entry_page_id', $page_id );
        }
    }
}
register_activation_hook( __FILE__, 'ttp_activate_plugin' );

/**
 * Deactivation hook: optionally remove created page
 */
function ttp_deactivate_plugin() {
    $page_id = get_option( 'ttp_time_entry_page_id' );
    if ( $page_id ) {
        wp_delete_post( $page_id, true );
        delete_option( 'ttp_time_entry_page_id' );
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
    if ( ! is_user_logged_in() ) {
        return '<p>' . __( 'Please <a href="' . wp_login_url( get_permalink() ) . '">log in</a> to record time.', 'time-tracking-plugin' ) . '</p>';
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
    /*
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.querySelector('.ttp-form');
      const activitySelect = document.getElementById('ttp_activity');
      const otherInput = document.getElementById('ttp_activity_other');

      form.addEventListener('submit', function(e) {
        if (activitySelect.value === 'Other') {
          const custom = otherInput.value.trim();
          if (!custom) {
            alert('Please specify an activity.');
            otherInput.focus();
            e.preventDefault();
            return;
          }
          activitySelect.value = custom;
        }
      });
    });*/
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'time_entry_form', 'ttp_time_entry_form_shortcode' );

/**
 * Handle front-end form submission and save as a custom post
 */
function ttp_handle_time_entry_submission() {


    // security & auth checks
    if ( !is_user_logged_in()) {
        echo "You must be logged in to submit a time entry.";
        return;
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


        // don’t save zero or negative values
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
 * Enqueue Chart.js on the admin page
 */
function ttp_enqueue_admin_scripts( $hook ) {
    if ( $hook !== 'toplevel_page_time-tracking' ) {
        return;
    }
    wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true );
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
    echo '<li>' . esc_html__( 'Date picker, activity dropdown, and hours input included.', 'time-tracking-plugin' ) . '</li>';
    echo '</ul>';
    echo '<h2>' . esc_html__( 'Admin Interface', 'time-tracking-plugin' ) . '</h2>';
    echo '<ul>';
    echo '<li>' . esc_html__( 'Summary chart: aggregate hours per activity.', 'time-tracking-plugin' ) . '</li>';
    echo '<li>' . esc_html__( 'Recent entries: view last 10 submissions.', 'time-tracking-plugin' ) . '</li>';
    echo '<li>' . esc_html__( 'Plugin creates a "Log Time" page on activation.', 'time-tracking-plugin' ) . '</li>';
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
    <h2><?php _e('Recent Entries', 'time-tracking-plugin'); ?></h2>
    <table class="wp-list-table widefat fixed striped">
        <thead><tr><th><?php _e('Date','time-tracking-plugin'); ?></th><th><?php _e('User','time-tracking-plugin'); ?></th><th><?php _e('Activity','time-tracking-plugin'); ?></th><th><?php _e('Hours','time-tracking-plugin'); ?></th><th><?php _e('Notes','time-tracking-plugin'); ?></th></tr></thead>
        <tbody>
        <?php
        $recent = new WP_Query(array('post_type'=>'time_entry','posts_per_page'=>10,'orderby'=>'date','order'=>'DESC'));
        if($recent->have_posts()): while($recent->have_posts()): $recent->the_post();
            $date = get_post_meta(get_the_ID(),'entry_date',true);
            $user = get_userdata(get_post_meta(get_the_ID(),'user_id',true));
            $act  = get_post_meta(get_the_ID(),'activity',true);
            $hrs  = get_post_meta(get_the_ID(),'time_spent',true);
            $notes = get_post_meta(get_the_ID(),'notes',true);

            echo '<tr><td>'.esc_html($date).'</td><td>'.esc_html($user->display_name).'</td><td>'.esc_html($act).'</td><td>'.esc_html($hrs).'</td><td>'.esc_html($notes).'</td></tr>';

        endwhile; else:
            echo '<tr><td colspan="4">'.__('No entries found.','time-tracking-plugin').'</td></tr>';
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

