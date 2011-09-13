<?php

/*
Plugin Name: BP Archive Groups
Author: Boone B Gorges
Version: 1.0
*/

define( 'BPAG_VERSION', '1.0' );
define( 'BPAG_INSTALL_DIR', dirname( __FILE__ ) );

function bpag_load() {
	include( BPAG_INSTALL_DIR . '/bp-archive-groups.php' );
}
add_action( 'bp_include', 'bpag_load' );

?>