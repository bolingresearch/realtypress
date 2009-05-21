<?php
/*
 * Plugin Name: RealtyPress Core
 * Version: 0.1
 * Plugin URI: http://realtypress.org/
 * Author: Dustin Boling, Boling Research Labs
 * Author URI: http://bolingresearch.com
 * Description: Core functions for RealtyPress.
 */

// Block direct access to this file.
if (preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) {
    die ("You are not allowed to access this file directly!");
}

global $wp_version;

if (version_compare($wp_version, "2.7", "<")) {
    $exit_msg = 'MLS requires WordPress 2.7 or newer.
             <a href="http://codex.wordpress.org/Upgrading_WordPress">
                 Please Update!</a>';
    exit($exit_msg);
}

// Avoid name collisions.
if ( !class_exists('RealtyPress') ) :

/**
 * Description of RealtyPress
 *
 * @author Dustin Boling
 */
class RealtyPress {

    var $image_dir;
    var $db_table;
    var $db_option;
    var $options;

    function __construct() {
        $this->img_dir = ABSPATH."/wp-content/mls-images";
        $this->db_table = "wp_rplistings";
        $this->db_option = "RealtyPress_Options";
        $this->options = $this->get_options();
        // add options page
        add_action('admin_menu', array(&$this, 'admin_menu'));
    }

    function install() {
        global $RPTheme;
        if ( isset($RPTheme) ) {
            add_shortcode('my-listings', array(&$RPTheme, 'agent_listings'));
            add_shortcode('office-listings', array(&$RPTheme, 'office_listings'));
            add_shortcode('broker-listings', array(&$RPTheme, 'broker_listings'));
        }
    }

    /**
     * Create Administration Menu
     */
    function admin_menu() {
        add_menu_page('RealtyPress Options', 'RealtyPress', 8, basename(__FILE__), array(&$this, 'handle_options'));
        add_submenu_page(basename(__FILE__), 'RealtyPress Settings', 'Settings', 7, basename(__FILE__), array(&$this, 'handle_options'));
        
    }

    /**
     * Retrieves plugin options from database.
     *
     * @return array
     */
    function get_options() {
        // default values
        $options = array
        (
            'agent_id' => '',
            'agent_license' => '',
            'office_id' => ''
        );
        // get saved options
        $saved = get_option($this->db_option);

        // assign them
        if (!empty($saved)) {
            foreach ($saved as $key => $option)
            $options[$key] = $option;
        }

        // update the options if necessary
        if ($saved != $options)
        update_option($this->db_option, $options);

        // return the options
        return $options;
    }
    
    /**
     * Options / Admin page
     */
    function handle_options() {
        $options = $this->get_options();

        // if server credentials updated
        if ( isset($_POST['submitted']) ) {
            // check security
            check_admin_referer('rp-options');

            $options = array();

            $options['office_id'] = htmlspecialchars($_POST['office_id']);
            $options['agent_id'] = htmlspecialchars($_POST['agent_id']);
            $options['agent_license'] = htmlspecialchars($_POST['agent_license']);

            update_option($this->db_option, $options);

            echo '<div class="updated fade"><p>Your settings have been saved.</p></div>';
        }

        $office_id = stripslashes($options['office_id']);
        $agent_id = stripslashes($options['agent_id']);
        $agent_license = stripslashes($options['agent_license']);

        // url for form submit, equals our current page
        $action_url = $_SERVER['REQUEST_URI'];

        include('realtypress-core/rp-options.php');
    }

    /*
     * @returns array of listing objects
     */
    function get_listings( $listing_ids = array() ) {
        $listings = array();

        if (!empty($listing_ids)) {
            global $wpdb;

            // build query
            $query = "SELECT * FROM $this->db_table WHERE listing_id IN (";
            $id_count = count($listing_ids);
            for ($i = 0; $i < $id_count; $i++) {
                $query .= $listing_ids[$i];
                if ($i != $id_count - 1) {
                    $query .= ",";
                }
            }
            $query .= ") ORDER BY list_date DESC";
            $listings = $wpdb->get_results($query);
        }
        return $listings;
    }

    /* Get Newest Listings
     * get_newest_listings(date $date = date("Y-m-d")[, int $limit = 100, string $prop_class = ""])
     * @returns array of listing objects
     */
    function get_newest_listings($max_days = 7, $limit = 20, $prop_class = "") {
        global $wpdb;
        $listings = array();
        $date = new DateTime(date("Y-m-d"));
        $date->modify("-$max_days day");

        // build the query
        $query = "SELECT * FROM $this->db_table WHERE Date(list_date) >= {$date->format("Y-m-d")} ";
        if ( $prop_class != "" && $prop_class != "*" ) {
            $query .= "AND class = $prop_class ";
        }
        $query .= "ORDER BY list_date DESC LIMIT $limit";

        $listings = $wpdb->get_results($query);

        return $listings;
    }

    /**
     * @returns array of listing objects
     */
    function get_broker_listings( $broker_id=0 ) {
        $listings = array();
        if ( $broker_id != 0 ) {
            global $wpdb;
            $query = "SELECT * FROM $this->db_table WHERE broker_id=$broker_id ORDER BY list_date DESC";
            $listings = $wpdb->get_results($query);
        }
        return $listings;
    }

    /**
     * @returns array of listing objects
     */
    function get_office_listings( $office_id=0 ) {
        $listings = array();
        if ( $office_id != 0 ) {
            global $wpdb;
            $query = "SELECT * FROM $this->db_table WHERE office_id=$office_id ORDER BY list_date DESC";
            $listings = $wpdb->get_results($query);
        }
        return $listings;
    }

    /**
     * @returns array of listing objects
     */
    function get_agent_listings( $agent_id=0 ) {
        $listings = array();
        if ( $agent_id != 0 ) {
            global $wpdb;
            $query = "SELECT * FROM $this->db_table WHERE agent_id=$agent_id ORDER BY list_date DESC";
            $listings = $wpdb->get_results($query);
        }
        return $listings;
    }

    /**
     * Get Search Results
     */
    function get_search_results($pricelow, $pricehigh, $beds, $baths) {
        $listing = array();
        global $wpdb;
        $query = "SELECT * FROM $this->db_table
                  WHERE price BETWEEN $pricelow AND $pricehigh
                  AND beds >= $beds
                  AND (baths_full + baths_partial) >= $baths
                  ORDER BY price DESC";
        $listings = $wpdb->get_results($query);
        return $listings;
    }
}

else :
exit ("Class RealtyPress already declared!");
endif;

// create an instance of TehamaIDX
$RealtyPress = new RealtyPress();

if (isset($RealtyPress)) {
    // register the activation function by passing the reference to our instance
    register_activation_hook( __FILE__, array(&$RealtyPress, 'install') );
}
?>