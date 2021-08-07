<?php
/*
 Plugin Name: Teack unauthorized access
 Plugin URI: http://mswarak.com
 Description: Teack unauthorized access to your WP website
 Author: msaleh
 Version: 1.0
 Author URI: https://mswarak.com
*/

$mswarak_teack_unauthorized_access_table_name = $wpdb->prefix . "mswarak_teack_unauthorized_access";
function mswarak_teack_unauthorized_access_menu_option()
{
    global $wpdb, $mswarak_teack_unauthorized_access_table_name;
    /*
     * -install db
     * -find unauthorized access
     * -insert
     * show in table
     */
    
    try
    {
        $wpdb->hide_errors();
        $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $mswarak_teack_unauthorized_access_table_name );
        $wpdb->show_errors();
    }
    catch (Exception $e)
    {
        error_log($e);
    }
    finally
    {
        mswarak_teack_unauthorized_access_create_db();
    }
}

function filter_wp_die_handler( $array )
{
    return 'mswarak_teack_unauthorized_access_report_insert';
}
function mswarak_teack_unauthorized_access_report_insert( $message, $title, $args )
{
    global $wpdb, $current_user, $mswarak_teack_unauthorized_access_table_name;
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
    
    if(isset($_COOKIE)){$request["COOKIE"] = $_COOKIE;}
    if(isset($_ENV)){$request["ENV"] = $_ENV;}
    if(isset($_FILES)){$request["FILES"] = $_FILES;}
    if(isset($_GET)){$request["GET"] = $_GET;}
    if(isset($_POST)){$request["POST"] = $_POST;}
    if(isset($_REQUEST)){$request["REQUEST"] = $_REQUEST;}
    if(isset($_SESSION)){$request["SESSION"] = $_SESSION;}
    
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
    
    $wpdb->insert($mswarak_teack_unauthorized_access_table_name, array(
        'message' => $message,
        'data' => json_encode($postData, JSON_UNESCAPED_UNICODE),
        'date' => time()
    ));
    
    _default_wp_die_handler($message, $title, $args);
}

function mswarak_teack_unauthorized_access_create_db()
{
    global $wpdb, $mswarak_teack_unauthorized_access_table_name;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $mswarak_teack_unauthorized_access_table_name (
      `id` INT(5) NOT NULL AUTO_INCREMENT ,
      `message` TEXT NOT NULL , 
      `data` TEXT NOT NULL , 
      `date` INT(11) NOT NULL , 
      PRIMARY KEY (`id`)
      ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

add_action("admin_menu", "mswarak_teack_unauthorized_access_menu_option");
add_filter( 'wp_die_handler', 'filter_wp_die_handler', 10, 1 );