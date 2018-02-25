<?php
/**
 * Core functionality for secure WordPress updates.
 *
 * @package DisplaceTech\DGXPCO
 */

namespace DisplaceTech\DGXPCO;

use ParagonIE_Sodium_Compat as Compat;
use ParagonIE_Sodium_Core_Util as Util;
use ParagonIE_Sodium_File as File;
use WP_Error;
use WP_Upgrader;

/**
 * Make sure this installation is safe for our purposes.
 *
 * We will _never_ download core updates over unencrypted connections. If SSL support is missing, abort!
 */
function activate() {
	if ( ! wp_http_supports( [ 'ssl' ] ) ) {
		deactivate_plugins( DGXPCO_BASENAME );
		exit( esc_html__( 'Serverside SSL support is not available. DGXPCO has been deactivated.', 'dgxpco' ) );
	}
}

/**
 * Default setup routine.
 */
function setup() {
	$n = function ( $function ) {
		return __NAMESPACE__ . "\\$function";
	};

	add_action( 'init', $n( 'i18n' ) );

	do_action( 'dgxpco_loaded' );
}

/**
 * Registers the default textdomain.
 */
function i18n() {
	$locale = apply_filters( 'plugin_locale', get_locale(), 'dgxpco' );
	load_textdomain( 'dgxpco', WP_LANG_DIR . '/dgxpco/dgxpco-' . $locale . '.mo' );
	load_plugin_textdomain( 'dgxpco', false, plugin_basename( DGXPCO_PATH ) . '/languages/' );
}

/**
 * Return an array of trusted Ed25519 public keys.
 *
 * @return array An array of trusted Ed25519 public keys.
 */
function get_public_keys() {
	$known_keys = [
		Compat::hex2bin( '5d4c696e571307b4a47626ae0bf9a7a229403c46657b4a9e832fee47e253bc5b' ),
	];

	/**
	 * Filter the array of known, trusted Ed25519 signing keys.
	 *
	 * @param array $known_keys An array of trusted Ed25519 public keys.
	 */
	return apply_filters( 'dgxpco_trusted_keys', $known_keys );
}

/**
 * Verifies the Ed25519 signature of a file for a given set of public keys.
 *
 * @param string $filename    The system path to the file being verified.
 * @param array  $public_keys An array of trusted Ed25519 public keys.
 * @param string $signature   The file signature.
 *
 * @return bool|object WP_Error on failure, true on success.
 */
function verify_file_ed25519( $filename, $public_keys, $signature ) {
	if ( Util::strlen( $signature ) === Compat::CRYPTO_SIGN_BYTES * 2 ) {
		$signature = Compat::hex2bin( $signature );
	}

	foreach ( $public_keys as $public_key ) {
		if ( Util::strlen( $public_key ) === Compat::CRYPTO_SIGN_PUBLICKEYBYTES * 2 ) {
			$public_key = Compat::hex2bin( $public_key );
		}
		if ( File::verify( $signature, $filename, $public_key ) ) {
			return true;
		}
	}

	return new WP_Error( 'ed25519_mismatch', sprintf(
		/* Translators: %1$s is the file signature. */
		__( 'The signature of the file (%1$s) is not valid for any of the trusted public keys.', 'dxgpco' ),
		bin2hex( $signature )
	) );
}

/**
 * Determine whether or not the package is to download.
 *
 * If the package is a known core file and a signature check exists, download the package and return `false`.
 * If the package is a known core file and the check fails, return an error and abort.
 * If the package is a core file and no signature exists, return an error and abort.
 *
 * If the package is unknown file, passthru for standard operations.
 *
 * @param bool        $reply    Whether to bail without returning the package.
 * @param string      $package  The package file name.
 * @param WP_Upgrader $upgrader The WP_Upgrader instance.
 *
 * @return bool|WP_Error
 */
function pre_download( $reply, $package, $upgrader ) {
	// If we're already aborting, abort.
	if ( false !== $reply ) {
		return $reply;
	}

	// If this isn't a core update, abort.
	if ( ! preg_match( '!^https://downloads\.wordpress\.org/release/wordpress-\d\.\d\.\d.*\.zip!i', $package ) ) {
		return $reply;
	}

	if ( empty( $package ) ) {
		return new WP_Error( 'no_package', $upgrader->strings['no_package'] );
	}

	// Get the signature for this file first.
	$message = sprintf(
		/* Translators: %1$s is the URL for the signature. */
		__( 'Downloading package signature from %1$s&#8230;', 'dgxpco' ),
		'<span class="code">%s</span>'
	);
	$signature_path = 'https://releasesignatures.displace.tech/wordpress/' . basename( $package ) . '.sig';
	$upgrader->skin->feedback( sprintf( $message, $signature_path ) );
	$signature_file = download_url( $signature_path );

	// No signature.
	if ( is_wp_error( $signature_file ) ) {
		/**
		 * Signatures are required for all core updates by default. If, for some reason, you wish to allow
		 * updates without checking the signature, use this filter to bypass the signature check.
		 *
		 * Or, you know, just disable the plugin because you'll be operating in the wild wild west anyway.
		 *
		 * Your decision.
		 *
		 * @param bool $require_signatures Whether or not to permit packages to be installed
		 *                                 without valid signatures.
		 */
		$require_signatures = apply_filters( 'dgxpco_require_signatures', true );

		if ( $require_signatures ) {
			return new WP_Error( 'missing_signature', sprintf(
				/* Translators: %1$s is the package name. */
				__( 'No signature available for package: %1$s', 'dgxpco' ),
				$package
			) );
		}

		$upgrader->skin->feedback( __( 'No signature available for package. Skipping check as configured&#8230;', 'dgxpco' ) );
		$signature = false;
	} else {
		// phpcs:disable WordPress.WP.AlternativeFunctions
		$signature_json = file_get_contents( $signature_file );
		// phpcs:enable
		$signature_obj = json_decode( $signature_json );
		unlink( $signature_file );

		$signature = $signature_obj->signature;
	}

	$upgrader->skin->feedback( 'downloading_package', $package );

	$download_file = download_url( $package );

	if ( is_wp_error( $download_file ) ) {
		return new WP_Error( 'download_failed', $upgrader->strings['download_failed'], $download_file->get_error_message() );
	}

	if ( $signature ) {
		$upgrader->skin->feedback( __( 'Verifying package signature&#8230;', 'dgxpco' ) );

		$public_keys   = get_public_keys();
		$ed25519_check = verify_file_ed25519( $download_file, $public_keys, $signature );

		if ( is_wp_error( $ed25519_check ) ) {
			unlink( $download_file );
			return $ed25519_check;
		}
	}

	return $download_file;
}
