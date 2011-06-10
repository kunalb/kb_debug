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

/**
 * The URL for the plugin.
 * @global string KB_DEBUG_RELURL
 */
define( 'KB_DEBUG_RELURL', content_url() . "/mu-plugins" );

/**
 * The main (and only) file for KB_Debug, divided into classes based on functionality.
 * @package KB_Debug
 * @author Kunal Bhalla
 * @version 0.1
 */

/**
 * Used to save debugging information logged by the user.
 */ 
class KB_Debug {

	/**
	 * Store any errors, notices, warnings or debug information.
	 * @access private
	 * @var Array
	 */
	private $logged;

	/**
	 * Constructor. Initializes values and assigns actions.
	 */
	public function __construct() {
		$this->logged = Array();

		add_action( 'shutdown', Array( &$this, 'display' ), 98 );
		wp_enqueue_style( 'kb-debug-css', KB_DEBUG_RELURL . "/kb-debug.css" ); 
	}

	/**
	 * Display all logged debugging information.
	 * @access public
	 */
	function display() {
		echo "<div class = 'kb-debug-box' id = 'kb-debug'><div class = 'effect-border'>";
		echo "<h2>Logged</h2>";	
		echo "<ul>";
		foreach( $this->logged as $log ) {
			echo "<li>";
			echo "<h3>{$log->backtrace['line']}, {$log->backtrace['file']}</h3>";
			foreach( $log->data as $data ) {
				echo "<pre>"; print_r( $data ); echo "</pre>";
			}
			echo "</li>";
		}
		echo "</ul>";
		echo "</div></div>";
	}

	/**
	 * Log array of arguments passed to it. Later pretty printed with a backtrace using print_r.
	 * Do not call this function directly.
	 * @access public
	 */
	function log( $arglist ) {
		$log = new STDClass();
		$log->data = $arglist;
		$backtrace = debug_backtrace();
		$log->backtrace = $backtrace[1];

		$this->logged[] = $log;
	}

}
 

/**
 * Used to save errors and warnings information, if any.
 * @package KB_Debug
 */
class KB_Debug_Errors {

	/**
	 * Store any errors, notices, warnings or debug information.
	 * @access private
	 * @var Array
	 */
	private $logged;

	/**
	 * Counters for filters available, filters used and gettexts.
	 * @access private
	 * @var Array
	 */
	private $counters;

	/**
	 * Warning in case an existing error handler is removed.
	 * @access private
	 * @var String
	 */
	private $message; 

	/**
	 * Constructor. Initializes $counters and $logged.
	 */
	public function __construct() {
		$this->logged = Array();

		$this->counters = new STDClass();
		$this->counters->errors   = 0;
		$this->counters->warnings = 0;
		$this->counters->strict   = 0;
		$this->counters->notices  = 0;

		add_action( 'shutdown', Array( &$this, 'display' ), 99 );
		$original_handler = set_error_handler( Array( &$this, 'log' ) );

		$this->message = "";
		if( $original_handler != NULL )
			$this->message = "<p>Warning: An existing error handler was replaced." . print_r( $original_handler, true ) . "</p>";

		wp_enqueue_style( 'kb-debug-css', KB_DEBUG_RELURL . "/kb-debug.css" ); 
	}

	/**
	 * Displays the logged data, if any.
	 * @access public
	 */
	public function display() {
		echo "<div class = 'kb-debug-box'><div class = 'effect-border'>";
		echo "<h2>Errors and Warnings</h2>";	
		echo $this->message;
		echo "<ul>";
		foreach( $this->logged as $log ) {
			$type = 'Strict';
			switch ($log['no']) {
				case E_ERROR: $type = 'E_ERROR'; break;
				case E_WARNING: $type = 'E_WARNING'; break;
				case E_NOTICE: $type = 'E_NOTICE'; break;
				case E_STRICT: $type = 'E_STRICT'; break;
				case E_USER_NOTICE: $type = 'E_USER_NOTICE'; break;
				case E_USER_WARNING: $type = 'E_USER_WARNING'; break;
			}

			echo "<li class = 'kb-debug-{$type}'>";

			echo "<h3>{$type}</h3>";
			echo "<h4>{$log['line']}, {$log['file']}.</h4>";

			echo "<p>{$log['str']}</p>";

			echo "<h4>Context</h4>";
			echo "<pre style = 'max-height: 100px;'>"; print_r($log['context']); echo "</pre>";

			echo "</li>";
		}
		echo "</ul>";

		echo "</div></div>";
	}

	/**
	 * Log all error based data.
	 * @access public
	 */
	public function log( $errno, $errstr, $errfile, $errline, $errcontext ) {
		$this->logged[] = Array(
			'no'		=> $errno,
			'str'		=> $errstr,
			'file'		=> $errfile,
			'line'		=> $errline,
			'context'	=> $errcontext
		);

		return 1;
	}

}

/**
 * Logs all hook information including arguments passed and functions run on each hook.
 * @package KB_Debug
 */
class KB_Debug_Hooks {

	/**
	 * Store any Hook information
	 * @access private
	 * @var Array
	 */
	private $logged;

	/**
	 * Counters for filters available, filters used and gettexts.
	 * @access private
	 * @var Array
	 */
	private $counters;

	/**
	 * Constructor. Initializes $counters and $logged.
	 */
	public function __construct() {
		$this->logged = Array();
		
		$this->counters = new STDClass();
		$this->counters->actions  = 0;
		$this->counters->used     = 0;
		$this->counters->gettexts = 0;

		add_action( 'all', Array( &$this, 'log' ) );
		add_action( 'shutdown', Array( &$this, 'display' ), 100 );

		wp_enqueue_style( 'kb-debug-css', KB_DEBUG_RELURL . "/kb-debug.css" ); 
	}

	/**
	 * Displays the logged data, if any.
	 * @access public
	 */
	public function display() {
		echo "<div class = 'kb-debug-box'><div class = 'effect-border'>";
		echo "<h2>Hooks List (Total {$this->counters->actions}, {$this->counters->gettexts} gettext calls omitted)</h2>";
		echo "<ul>";
		foreach( $this->logged as $log ) {
			echo "<li>";
			echo "<h3>{$log['name']}</h3>";
		
			echo "<h4>Arguments</h4>";
			if ( !empty( $log['vars'] ) ) {
				echo "<pre>";print_r( $log['vars'] );echo "</pre>";	
			} else echo "<pre>None.</pre>";	
			
			echo "<h4>Functions</h4>";
			echo "<pre>";print_r( $log['funcs'] );echo "</pre>";	
			
			echo "</li>";
		}
		echo "</ul>";

		echo "</div></div>";
	}

	/**
	 * Log all hook based data.
	 * @access public
	 */
	public function log( $args, $vars = '' ) {
		global $wp_filter, $wp_query;

		if ( $args != 'gettext' && $args != 'gettext_with_context' ) {
			if ( array_key_exists( $args, $wp_filter ) ) {
				$funcs = $wp_filter[$args];
				ksort($funcs);
				$this->counters->used++;

				$this->logged[] = Array( 'vars' => $vars, 'funcs' => $funcs, 'name' => $args );
			}

			$this->counters->actions++;
		} else $this->counters->gettexts++;
	}

}

/** Enqueue the styling for kb_debug. */
wp_enqueue_style( 'kb-debug-css', KB_DEBUG_RELURL . "/kb-debug.css" ); 

/**
 * Call this function to save any data. Records calling information via a stack trace.
 */
function kb_debug() {
	static $kdb;
	if ( !isset( $kdb ) ) $kdb = new KB_Debug();

	$kdb->log( func_get_args() );
}

/** 
 * Check the $_GET variable and initialize classes accordingly.
 */
if (defined ('WP_DEBUG') && WP_DEBUG) {

	if( isset( $_GET['KB_Debug_Errors'] ) || ( defined( 'KB_DEBUG' ) && KB_DEBUG ) )
		new KB_Debug_Errors();
	if( isset( $_GET['KB_Debug_Hooks'] ) || ( defined( 'KB_DEBUG' ) && KB_DEBUG ) )
		new KB_Debug_Hooks();
}
