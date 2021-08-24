<?php
/*
 Plugin Name: Track unauthorized access
 Plugin URI: http://mswarak.com
 Description: Track unauthorized access to your WP website
 Author: msaleh
 Version: 1.1.0
 Author URI: https://mswarak.com
*/

// Set global variables
$mswarak_track_unauthorized_access_table_name = $wpdb->prefix . "mswarak_track_unauthorized_access";

/**
 * Add the plugin to WP admin menu (if user is website admin)
 */ 
function mswarak_track_unauthorized_access_menu_option()
{
    // Call global variables
    global $wpdb, $mswarak_track_unauthorized_access_table_name;
    
    // Check if the user is admin
    if ( is_admin() )
    {
        // Add the plugin to WP admin menu
        add_menu_page('Track unauthorized access', 'Track unauthorized access', 'exist', 'mswarak_track_unauthorized_access', 'mswarak_track_unauthorized_access_index_page', 'dashicons-list-view');
    }
    
    // Check if the plugin DB exists
    try
    {
        // Search
        $wpdb->hide_errors();
        $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $mswarak_track_unauthorized_access_table_name );
        $wpdb->show_errors();
    }
    catch (Exception $e)
    {
        // Error
        error_log($e);
    }
    finally
    {
        // If not found, create new DB
        mswarak_track_unauthorized_access_create_db();
    }
}

/**
 * Show the data in the database as HTML table
 */ 
function mswarak_track_unauthorized_access_index_page()
{
    // Call global variables
    global $wpdb, $mswarak_track_unauthorized_access_table_name;
    
    // Set local variables
    $mswarak_track_unauthorized_access_table_counter = 1;
    $mswarak_track_unauthorized_access_table_TR = "";
    
    // Loop in the database
    foreach ($wpdb->get_results ("SELECT * FROM {$mswarak_track_unauthorized_access_table_name} ORDER BY id DESC" ) as $value)
    {
        $mswarak_track_data = json_decode($value->data, true);
        $mswarak_track_date = date( "Y-m-d", $value->date );
        //$value->orders
        $mswarak_track_unauthorized_access_table_TR .= "
    <tr style='text-align: center;'>
        <td>{$mswarak_track_unauthorized_access_table_counter}</td>
        <td>{$mswarak_track_data["ip"]["ipaddress"]}</td>
        <td>{$mswarak_track_date}</td>
    </tr>";
        
        $mswarak_track_unauthorized_access_table_counter++;
    }
    
    
    // Priny the table
    echo "
<h2>List of unauthorized access to your website</h2>
<table style='width:100%'>
    <tr>
        <th>#</th>
        <th>IP</th> 
        <th>Date</th>
    </tr>
    {$mswarak_track_unauthorized_access_table_TR}
</table>
";
}

/**
 * Temporarily wp die handler
 * 
 * @param array          $array   Optional. Default empty.
 * @return mswarak_track_unauthorized_access_report_insert()
 */ 
function mswarak_track_unauthorized_access_filter_wp_die_handler( $array )
{
    return 'mswarak_track_unauthorized_access_report_insert';
}

/**
 * Insert a report about the access attempt
 *
 * @param string|WP_Error $message Error message or WP_Error object.
 * @param string          $title   Optional. Error title. Default empty.
 * @param string|array    $args    Optional. Arguments to control behavior. Default empty array.
 * @return _default_wp_die_handler()
 */ 
function mswarak_track_unauthorized_access_report_insert( $message, $title, $args )
{
    global $wpdb, $current_user, $mswarak_track_unauthorized_access_table_name;
    $is_user = false;
    $username = "";
    $ipaddress = "UNKNOWN";
    $actual_link = (isset ( $_SERVER ['HTTPS'] ) && $_SERVER ['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $actual_link = $_SERVER ["REQUEST_URI"];
    
    if (is_user_logged_in ())
    {
        $is_user = true;
        $username = $current_user->user_email;
    }
    
    $user = array(
        "is_user" => $is_user,
        "username" => $username
    );
    $request = array();
    $ip_list = array();
    
    if (isset($_SERVER['HTTP_CLIENT_IP']))
    {
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        $ip_list["HTTP_CLIENT_IP"] = $ipaddress;
    }
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
    {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        $ip_list["HTTP_X_FORWARDED_FOR"] = $ipaddress;
    }
    if (isset($_SERVER['HTTP_X_FORWARDED']))
    {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        $ip_list["HTTP_X_FORWARDED"] = $ipaddress;
    }
    if (isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
    {
        $ipaddress = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
        $ip_list["HTTP_X_CLUSTER_CLIENT_IP"] = $ipaddress;
    }
    if (isset($_SERVER['HTTP_FORWARDED_FOR']))
    {
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        $ip_list["HTTP_FORWARDED_FOR"] = $ipaddress;
    }
    if (isset($_SERVER['HTTP_FORWARDED']))
    {
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
        $ip_list["HTTP_FORWARDED"] = $ipaddress;
    }
    if (isset($_SERVER['REMOTE_ADDR']))
    {
        $ipaddress = $_SERVER['REMOTE_ADDR'];
        $ip_list["REMOTE_ADDR"] = $ipaddress;
    }
    
    $postData = array(
        "url" => $actual_link,
        "user" => $user,
        "request" => $request,
        "ip" => array(
            "ipaddress" => $ipaddress,
            "ip_list" => $ip_list,
        ),
    );
    
    $wpdb->insert($mswarak_track_unauthorized_access_table_name, array(
        'message' => $message,
        'data' => json_encode($postData, JSON_UNESCAPED_UNICODE),
        'date' => time()
    ));
    
    _default_wp_die_handler($message, $title, $args);
}

/**
 * Create new table in the WP database
 */ 
function mswarak_track_unauthorized_access_create_db()
{
    global $wpdb, $mswarak_track_unauthorized_access_table_name;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $mswarak_track_unauthorized_access_table_name (
      `id` INT(5) NOT NULL AUTO_INCREMENT ,
      `message` TEXT NOT NULL , 
      `data` TEXT NOT NULL , 
      `date` INT(11) NOT NULL , 
      PRIMARY KEY (`id`)
      ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

// add plugin to WP admin menu
add_action("admin_menu", "mswarak_track_unauthorized_access_menu_option");

// Call custom die handler
add_filter( 'wp_die_handler', 'mswarak_track_unauthorized_access_filter_wp_die_handler', 10, 1 );