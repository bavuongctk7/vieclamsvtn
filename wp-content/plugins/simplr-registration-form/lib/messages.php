<?php 
class SREG_Messages {
  
  public static function set($code, $message)
  {
    $session = WP_Session::get_instance();
    if (!isset($session['messages'])) {
      $session['messages'] = array();
    } 
    $session['messages'][] = array('class'=>$code,'text'=>$message);
    $session->write_data();
    return $session;
  } 
  
  public static function getAll() {
    
    $session = WP_Session::get_instance();
    $messages = $session['messages'];
    unset($session['messages']);
    // don't write data until shutdown just in case other data is changed
    add_action('shutdown', function() {
      WP_Session::get_instance()->write_data();
    });
    return $messages;
  }
  
}