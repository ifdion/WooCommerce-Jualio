<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

add_action('admin_menu', 'jl_pg_plugin_setup_menu');
 
function jl_pg_plugin_setup_menu(){
    add_menu_page( 'Jualio Payment Tutorial', 'Jualio Payment Tutorial', 'manage_options', 'jl-pg-Tutorial', 'jl_pg_init' );
}

  function jl_pg_init(){
      include_once('template/tutorial-pg.php');
  }

add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'my_plugin_action_links' );

function my_plugin_action_links( $links ) {
   $links[] = '<a href="'. esc_url( get_admin_url(null, 'admin.php?page=jl-pg-Tutorial') ) .'">Tutorial</a>';
   return $links;
}

?>