<?php

/*
	Plugin Name: KB_DEBUG
	Plugin URI: http://kunal-b.in
	Description: A swiss army knife for debugging wordpress plugins.
	Version: 0.2-bleeding
	Author: Kunal Bhalla.
	Author URI: http://kunal-b.in
	License: GPL2

	Copyright 2010  Kunal Bhalla  (email : bhalla.kunal@gmail.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as 
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

	Define KB_DISPLAY_HOOKS anywhere in your plugin/theme/code to show the 
	hooks at the end of execution. Define KB_DISPLAY_CONSTANTS anywhere to
	show all constants defined.

	Everything is pretty printed at shut down, to prevent irrevocable damage
	(perhaps I exaggerate) to your theme. And to keep notices, warnings readable.
*/

/* 1. Logging hooks, notices and warnings and displaying at the end. */

global $kb_gettext_counter; $kb_gettext_counter = 0; 
global $kb_filters; $kb_filters = 0;
global $kb_filters_used; $kb_filters_used = 0;

/**
 * Log all errors to a global array and dump later. 
 *
 * @global Array $kb_notices
 */
global $kb_notices;
$kb_notices = Array();

/**
 * Only display notices/warnings/etc. following the given filters.
 *
 * @global Array $kb_display_keywords
 */
global $kb_display_keywords;

/**
 * Custom error handler -- stores all the errors in the global array.
 *
 * @uses $kb_notices
 */
function ep_error_handler( $errno, $errstr, $errfile, $errline, $errcontext ) {
	global $kb_notices;

	$kb_notices[] = Array(
		'no'		=> $errno,
		'str'		=> $errstr,
		'file'		=> $errfile,
		'line'		=> $errline,
		'context'	=> $errcontext
	);

	return 1;
}

/**
 * Display the errors.
 *
 * Displays the errors on shutdown -- as well as
 * constants, if KB_DISPLAY_CONSTANTS is set to true.
 * Will show hooks if KB_DISPLAY_HOOKS is set to true.
 *
 * @uses $kb_notices
 */
function kb_display_errors() {
	global $kb_notices;
	global $kb_gettext_counter; global $kb_filters; global $kb_filters_used;

	if ( defined( 'KB_DISPLAY_CONSTANTS' ) || isset( $_GET['KB_DISPLAY_CONSTANTS'] ) ) {
		$defined_constants = get_defined_constants( true );
		$defined_constants = $defined_constants['user'];
		ksort( $defined_constants );
		trigger_error( kb_dump( $defined_constants ) );
	}

	if ( defined( 'KB_FORCE_HIDE' ) || isset( $_GET['KB_FORCE_HIDE'] ) )
		return;
	
	echo "<div id = 'kb-debug-results'>";
		
	foreach( $kb_notices as $notice ) {
		extract( $notice );

		switch ($no) {
			case E_ERROR: $type = 'Error'; break;
			case E_WARNING: $type = 'Warning'; break;
			case E_NOTICE: $type = 'Notice'; break;
			case E_STRICT: $type = 'Strict'; break;
			case E_USER_NOTICE: $type = 'Debug'; break;
			case E_USER_WARNING: $type = 'Hook'; break;
			default: $type = 'Strict';
		}

		switch ($type) {
			case 'Hook': 
				if ( defined( 'KB_DISPLAY_HOOKS' ) || isset($_GET['KB_DISPLAY_HOOKS']) ) 
					echo "<div class = 'kb_Hook'>$str</div>"; break;
			default: echo "<div class = 'kb_$type'>$type: $str<br />Line $line, $file</div>";
		}
	}

	echo "</div>";

	if ( defined( 'KB_DISPLAY_HOOKS' ) ) 
		echo "<div style = 'kb_disp_hook'>$kb_gettext_counter gettext calls; $kb_filters actions/filters. $kb_filters_used filters used.</div>";
}

/**
 * A buffered version of var_dump
 *
 * I really should rename this, considering
 * kb is (are?) my initials.
 *
 * @param mixed $var The data to log
 */
function kb_dump( $ivars ) {
	ob_start();
	var_dump( $ivars );
	return ob_get_clean();
}

/**
 * Saves all hooks data.
 *
 * Attached to the all hook. Records details about the 
 * hook called, and all the arguments provided, etc.
 *
 * @param $args
 * @param $vars
 */
function kb_log_hooks( $args, $vars = '' ) {
	global $wp_filter, $wp_query, $kb_gettext_counter, $kb_filters, $kb_filters_used;

	if ( $args != 'gettext' && $args != 'gettext_with_context' ) {
			$functions_called = "";
			if ( array_key_exists( $args, $wp_filter ) ) {
				$funcs = $wp_filter[$args];
				ksort($funcs);
				$functions_called = ( array_key_exists( $args, $wp_filter ) )? kb_dump( $funcs ) : "" ;
			}
			trigger_error( "$args<div class = 'kb_hook_vars'>" . kb_dump( $vars ) . "</div><div class = 'kb_fn_called'>"  . $functions_called . "</div>", E_USER_WARNING );
			$kb_filters++;
	} else $kb_gettext_counter++;

	if ( array_key_exists( $args, $wp_filter ) )
		$kb_filters_used++;
}

//Make PHP use my custom handler to log messages instead of directly displaying them.
set_error_handler( 'ep_error_handler' );

//Log all errors.
add_action( 'all', 'kb_log_hooks' );

//I _need_ jquery to allow expanding text.
wp_enqueue_script( 'jquery' );

//Log everything, but display only if WP_DEBUG is set to true
if ( defined( 'WP_DEBUG' ) && WP_DEBUG )
	//And don't mess up any ajax stuff either.
	if ( !array_key_exists( 'HTTP_X_REQUESTED_WITH', $_SERVER ) || ( array_key_exists( 'HTTP_X_REQUESTED_WITH' , $_SERVER ) && $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest' ) )
		add_action( 'shutdown', 'kb_display_errors' );

/* 2. Reset capabilities to initial state if KB_RESET_CAPS is set in $_GET */

/**
 * Reverts capabilities to default state. 
 *
 * Useful while debugging.
 */
function kb_reset_caps() {
	global $wpdb;
	$key = $wpdb->prefix . 'user_roles';

	//Bye, bye, existing caps
	delete_option( $key );

	//Repopulate
	require_once( "/home/kunalb/dev/eventpress/wp-admin/includes/schema.php" );
	populate_roles();
}
if (isset( $_GET['KB_RESET_CAPS'] ))
	add_action( 'init', kb_reset_caps );

