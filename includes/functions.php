<?php
namespace DisplaceTech\DGXPCO;

function preflight()
{

}

/**
 * Default setup routine
 *
 * @uses add_action()
 * @uses do_action()
 *
 * @return void
 */
function setup() {
	$n = function( $function ) {
		return __NAMESPACE__ . "\\$function";
	};

	add_action( 'init', $n( 'i18n' ) );

	do_action( 'dgxpco_loaded' );
}

/**
 * Registers the default textdomain.
 *
 * @uses apply_filters()
 * @uses get_locale()
 * @uses load_textdomain()
 * @uses load_plugin_textdomain()
 * @uses plugin_basename()
 *
 * @return void
 */
function i18n() {
	$locale = apply_filters( 'plugin_locale', get_locale(), 'dgxpco' );
	load_textdomain( 'dgxpco', WP_LANG_DIR . '/dgxpco/dgxpco-' . $locale . '.mo' );
	load_plugin_textdomain( 'dgxpco', false, plugin_basename( DGXPCO_PATH ) . '/languages/' );
}
