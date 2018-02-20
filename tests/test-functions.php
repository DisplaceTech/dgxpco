<?php
/**
 * Tests for some file or class.
 *
 * @package DisplaceTech\DGXPCO
 */

namespace DisplaceTech\DGXPCO\Tests;

use Automatic_Upgrader_Skin;
use DisplaceTech\DGXPCO as Functions;
use ParagonIE_Sodium_Compat;
use ParagonIE_Sodium_File;
use WP_Error;
use WP_UnitTestCase;
use WP_Upgrader;

/**
 * Sample test case.
 */
class FunctionsTest extends WP_UnitTestCase {

	/**
	 * A cached signature for TEST_ARCHIVE using PRIVATE_KEY.
	 *
	 * @var string
	 */
	protected static $signature;

	/**
	 * Private key to use for testing.
	 *
	 * @link https://github.com/paragonie/sodium_compat/blob/master/tests/unit/Ed25519Test.php
	 *
	 * @var string
	 */
	const PRIVATE_KEY = '9d61b19deffd5a60ba844af492ec2cc44449c5697b326919703bac031cae7f60' .
						'd75a980182b10ab7d54bfed3c964073a0ee172f3daa62325af021a68f707511a';

	/**
	 * Public key to use for testing.
	 *
	 * @link https://github.com/paragonie/sodium_compat/blob/master/tests/unit/Ed25519Test.php
	 *
	 * @var string
	 */
	const PUBLIC_KEY = 'd75a980182b10ab7d54bfed3c964073a0ee172f3daa62325af021a68f707511a';

	/**
	 * Filepath to a dummy archive.
	 *
	 * @var string
	 */
	const TEST_ARCHIVE = __DIR__ . '/test-tools/test-download.zip';

	/**
	 * Example URL for a WordPress core download.
	 *
	 * @var string
	 */
	const DOWNLOAD_URL = 'https://downloads.wordpress.org/release/wordpress-4.9.4.zip';

	/**
	 * To save having to sign the file multiple times, generate it once at the beginning of the run
	 * and cache it.
	 *
	 * @beforeClass
	 */
	public static function generate_signature() {
		if ( empty( self::$signature ) ) {
			self::$signature = ParagonIE_Sodium_File::sign( self::TEST_ARCHIVE, ParagonIE_Sodium_Compat::hex2bin( self::PRIVATE_KEY ) );
		}

		return self::$signature;
	}

	/**
	 * If SSL is unsupported, the plugin should deactivate itself.
	 *
	 * Cryptographic signatures don't mean a thing if you're vulnerable to simple MitM attacks.
	 */
	public function test_activate_deactivates_plugin_if_ssl_is_unsupported() {
		$this->markTestIncomplete( 'We don\'t actually want to exit the process.' );

		$this->assertTrue(
			is_plugin_active( DGXPCO_BASENAME ),
			'Expected plugin to start in an active state.'
		);

		Functions\activate();

		$this->assertFalse(
			is_plugin_active( DGXPCO_BASENAME ),
			'Expected plugin to start in an active state.'
		);
	}

	/**
	 * Test the bootstrapping of the plugin.
	 */
	public function test_setup() {
		$this->assertEquals( 1, did_action( 'dgxpco_loaded' ), 'Expected to see the "dgxpco_loaded" action upon plugin instantiation.' );
	}

	/**
	 * Ensure we're retrieving *at least* one trusted public key.
	 */
	public function test_get_public_keys() {
		$public_keys = Functions\get_public_keys();

		$this->assertGreaterThanOrEqual(1, $public_keys, 'Expected at least one trusted public key.' );
		$this->assertContains(
			ParagonIE_Sodium_Compat::hex2bin('5d4c696e571307b4a47626ae0bf9a7a229403c46657b4a9e832fee47e253bc5b'),
			$public_keys,
			'Expected to see Eric Mann\'s public key.'
		);
	}

	/**
	 * Additional trusted keys can be injected via the 'dgxpco_trusted_keys' filter.
	 */
	public function test_get_public_keys_enables_keys_to_be_filtered() {
		$key = uniqid();

		add_filter( 'dgxpco_trusted_keys', function ( $keys ) use ( $key ) {
			$keys[] = $key;

			return $keys;
		} );

		$public_keys = Functions\get_public_keys();

		$this->assertContains( $key,  $public_keys, 'Expected to see the key injected via filter.' );
	}

	/**
	 * Ensure we can verify signatures.
	 *
	 * @dataProvider hex_and_bin_provider()
	 */
	public function test_verify_file_ed25519( $signature, $public_key ) {
		$this->assertTrue(
			Functions\verify_file_ed25519( self::TEST_ARCHIVE, [ $public_key ], self::$signature ),
			'Expected the signature to be verified.'
		);
	}

	/**
	 * Provide different combinations of hexadecimal and binary values, since verify_file_ed25519()
	 * will try to guess automatically.
	 */
	public function hex_and_bin_provider() {
		$signature = self::generate_signature();

		return [
			'Hex signature, hex key' => [ ParagonIE_Sodium_Compat::bin2hex( $signature ), self::PUBLIC_KEY ],
			'Bin signature, hex key' => [ $signature, self::PUBLIC_KEY ],
		];
	}

	/**
	 * Without a public key provided, there's no way to verify. */
	public function test_verify_file_ed25519_returns_wp_error_on_failure() {
		$output = Functions\verify_file_ed25519( self::TEST_ARCHIVE, [], 'bad signature' );

		$this->assertTrue( is_wp_error( $output ), 'If a signature fails to validate, return a WP_Error.' );
	}

	/**
	 * Ensure the plugin is able to download and verify signatures.
	 */
	public function test_pre_download() {
		$tmpfiles = [];

		add_filter( 'pre_http_request', function ( $return, $args, $url ) use ( &$tmpfiles ) {
			if ( false !== strpos( $url, 'releasesignatures.displace.tech' ) ) {
				$tmpfiles['signature'] = $args['filename'];

				file_put_contents( $args['filename'], wp_json_encode( [
					'signature' => ParagonIE_Sodium_Compat::bin2hex( self::$signature ),
				] ) );

			} else {
				$tmpfiles['archive'] = $args['filename'];
				copy( self::TEST_ARCHIVE, $args['filename'] );
			}

			return [
				'headers'  => [],
				'body'     => [],
				'response' => [
					'code' => 200,
				],
				'cookies'  => [],
			];
		}, 10, 3 );

		add_filter( 'dgxpco_trusted_keys', function () {
			return [ self::PUBLIC_KEY ];
		} );

		$output = Functions\pre_download( false, self::DOWNLOAD_URL, new WP_Upgrader( new Automatic_Upgrader_Skin() ) );

		$this->assertEquals( $tmpfiles['archive'], $output );
		$this->assertFalse( file_exists( $tmpfiles['signature'] ), 'The signature tmpfile should have been deleted.' );
	}

	/**
	 * If pre_download is already receiving a non-false $reply value, let it continue.
	 */
	public function test_pre_download_doesnt_interrupt_an_interruption() {
		$reply = uniqid();

		$this->assertSame(
			$reply,
			Functions\pre_download( $reply, self::DOWNLOAD_URL, new WP_Upgrader( new Automatic_Upgrader_Skin() ) ),
			'If the function was already aborting, don\'t interrupt.'
		);
	}

	/**
	 * At this point, we're only verifying signatures for WordPress core updates.
	 *
	 * @dataProvider download_url_provider()
	 */
	public function test_pre_download_only_downloads_core_packages( $url ) {
		$this->assertFalse(
			Functions\pre_download( false, $url, new WP_Upgrader( new Automatic_Upgrader_Skin() ) ),
			'Updates should abort if given an invalid package string.'
		);
	}

	/**
	 * Provide various non-WordPress core download URLs.
	 */
	public function download_url_provider() {
		return [
			'Plugin' => [ 'https://downloads.wordpress.org/plugin/son-of-clippy.0.1.0.zip' ],
			'Theme'  => [ 'https://downloads.wordpress.org/theme/twentyseventeen.1.4.zip' ],
		];
	}

	/**
	 * If we're unable to download a signature, return a WP_Error object.
	 */
	public function test_pre_download_returns_wp_error_if_unable_to_download_signature() {
		add_filter( 'pre_http_request', function () {
			return new WP_Error();
		} );

		$output = Functions\pre_download( false, self::DOWNLOAD_URL, new WP_Upgrader( new Automatic_Upgrader_Skin() ) );

		$this->assertTrue( is_wp_error( $output ), 'Should receive a WP_Error if unable to download signatures.' );
	}

	/**
	 * Using the 'dgxpco_require_signatures' filter, invalid signatures can be bypassed.
	 */
	public function test_pre_download_can_bypass_failed_signature_via_filter() {
		add_filter( 'pre_http_request', function () {
			return new WP_Error();
		} );

		add_filter( 'dgxpco_require_signatures', '__return_false' );

		$output = Functions\pre_download( false, self::DOWNLOAD_URL, new WP_Upgrader( new Automatic_Upgrader_Skin() ) );

		$this->assertTrue( is_wp_error( $output ), 'Should receive a WP_Error if unable to download signatures.' );
	}
}
