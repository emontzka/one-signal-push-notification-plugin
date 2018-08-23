<?php
/**
 * Plugin Name: CGI Customized Push Notifications 
 * Description: This plugin customizes the behavior of One Signal push notifications.
 * Author: Eric Montzka
 * License: MIT
 * Version: 1.0
 */

class Cgi_Notifications {

  /**
   *
   * Class Construct
   *
   */
  public function __construct() {
    add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_cgi_notification_script'), 1.0, true);
    add_action( 'the_post', array( $this, 'is_user_subscribed_to_post' ));
    add_action( 'comment_post', array( $this, 'show_message_function'), 10, 2 );
    add_filter( 'the_content', array( $this, 'add_subscribe_button' ));
    add_filter('onesignal_send_notification', array( $this, 'onesignal_send_notification_filter'), 10, 4);


    // AJAX
    add_action( 'wp_ajax_add_notification',  array( $this, 'add_notification_func' ));
    add_action( 'wp_ajax_nopriv_add_notification',  array( $this, 'add_notification_func' ));
    add_action( 'wp_ajax_remove_notification',  array( $this, 'remove_notification_func' ));
    add_action( 'wp_ajax_nopriv_remove_notification',  array( $this, 'remove_notification_func' ));
    add_action( 'wp_ajax_save_player_id',  array( $this, 'save_player_id_func' ));
    add_action( 'wp_ajax_nopriv_save_player_id',  array( $this, 'save_player_id_func' ));
  }


  /**
   *
   * Enqueue Scripts
   *
   */
  public function enqueue_cgi_notification_script() {
    wp_enqueue_script( 'cgi-notification-script', plugin_dir_url( __FILE__ ) . 'js/cgi_notification.js',array('jquery'),1.2, true);
    wp_localize_script( 'cgi-notification-script', 'CGI_Ajax', array('ajaxurl'   => admin_url( 'admin-ajax.php' ),) );
    
  }

  /**
   *
   * Since we can get user meta here, check for post id in user meta. 
   * @return boolean
   *
   */
  public function is_user_subscribed_to_post() {
    global $post;
    $is_subscribed = false;
      $user = wp_get_current_user();
      $userId = $user->ID;
      $thisid = $post->ID;

      if ( metadata_exists( 'user', $userId, 'notificationsubscription' ) ) {
        $current_subscriptions = get_user_meta( $userId, 'notificationsubscription', true);

        if ( ! array($current_subscriptions) ) {
            $current_subscriptions = array();
        }
        if ( isset($current_subscriptions) && in_array($thisid, $current_subscriptions, true) ) {
          $is_subscribed = true;
        }       
      } else {
        error_log("the key does not exist") ;
      }
      
    return $is_subscribed;
  }

  /**
   *
   * Adds subscribe button to post
   *
   */
  public function add_subscribe_button($content) {
    if (is_single() && is_singular( $post_types = 'discussion-topics' )) {

    
    $is_subscribed = $this->is_user_subscribed_to_post();
    if ($is_subscribed ) {
      $status = 'subscribed';
      $buttonText = 'Unsubscribe from this Post';
    } else {
      $status = 'not-subscribed';
      $buttonText = 'Subscribe to this Post';
    }
   
  $content .= "<form class=" . $status . " id='cgi-notification-subscribe'>";
  $content .= "<input type='submit' id='post-data' value='" . $buttonText . "'  data-postId='" . get_the_ID() . "' />";
  $content .= "<input type='hidden' value='" . wp_create_nonce() . "' />"; 
  $content .= "</form><div id='ajax-output'></div>" ;
    return $content;
  } else {
    return $content;
  }
  }

  /**
   *
   * Add post id to users meta
   *
   */
  public function add_notification_func() {
  
    global $post;
    $mypost = (int)$_POST['data'];
    $this_user = get_current_user_id();
    $user_info = get_userdata($this_user);
    $userId = $user_info->ID;
    $notification_array = get_user_meta( $userId, 'notificationsubscription', true );


     if ( ! array($notification_array) ) {
          $notification_array = array();
      }
      // $notification_array = unserialize($notification_array);
      print_r($notification_array);
      $notification_array[] = $mypost;
      // array_push($notification_array, $mypost);
       print_r($notification_array);

      update_usermeta( $userId, 'notificationsubscription' , $notification_array );

    die();
  }

  /**
   *
   * Remove post id from users meta
   *
   */
  public function remove_notification_func() {
    
    global $post;
    $mypost = (int)$_POST['data'];


    $this_user = get_current_user_id();
    $user_info = get_userdata($this_user);
    $userId = $user_info->ID;
    $notification_array = get_user_meta( $userId, 'notificationsubscription', true );

      // $notification_array[] = $mypost; previous method for inserting 
      $array_position = array_search($mypost, $notification_array);
      unset($notification_array[$array_position]);

      update_usermeta( $userId, 'notificationsubscription' , $notification_array );

    die();
  }  

  /**
   *
   * Ajax function using OneSignal player id
   * If there is a OneSignal playerId set up, add it to the user meta
   * If there is a playerId in the user meta that does not match, 
   * it is because the user cleared cached and OneSignal created a new playerId
   * In this case, we will return the current subscriptions for the user meta 
   * so they can be added to the new playerId
   *
   */
  public function save_player_id_func() {
    $initial_playerid = $_POST['data'];
    error_log($initial_playerid);
    return $initial_playerid;
  }
  

  /**
   *
   * Update user when a subscribed post is updated
   *
   */
  public function onesignal_send_notification_filter($fields, $new_status, $old_status, $post) {
       // Change which segment the notification goes to
      $fields['filters'] = array(array(
        "field" => "tag",
        "key" => "post-" . $post->ID,
        "relation" => "exists"
        ));
      
      return $fields;
  }

  /**
   *
   * Send push notification when a subscribed post is commented on
   *
   */
  public function show_message_function( $comment_ID, $comment_approved ) {
    if( 1 === $comment_approved ){
      $current_comment = get_comment( $comment_ID );
      $postId = $current_comment->comment_post_ID;
      $postTitle = get_the_title( $postId );
      $postUrl = get_permalink( $postId );
      $content = array(
        "en" => 'New comment added to ' . $postTitle
        );
      
      $fields = array(
        'app_id' => "8c478c70-6dbe-459a-b2f2-4704a06c2450",
        'contents' => $content,
        'filters' => array(array("field" => "tag", "key" => "post-" . $postId, "relation" => "exists")),
        'url' => $postUrl
      );
      
      $fields = json_encode($fields);
        print("\nJSON sent:\n");
        print($fields);
      
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8',
        'Authorization: Basic NmMxMDZjZGMtZTJiMy00YTg2LTkzZGItZTc0NDE4YjhjNjgx'));
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_HEADER, FALSE);
      curl_setopt($ch, CURLOPT_POST, TRUE);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

      $response = curl_exec($ch);
      curl_close($ch);
      
      return $response;
    }
  }
}

new Cgi_Notifications();

