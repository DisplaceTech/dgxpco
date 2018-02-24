<?php
/**
 * Plugin Name: DGXPCO
 * Plugin URI:  https://github.com/displacetech/dgxpco
 * Description: Secure updates for WordPress.
 * Version:     1.0.0
 * Author:      Eric Mann
 * Author URI:  https://eamann.com
 * License:     MIT
 * Text Domain: dgxpco
 * Domain Path: /languages
 *
 * @package DisplaceTech\DGXPCO
 */

/**
 * Copyright (c) 2018 Displace Technologies, LLC
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

define( 'DGXPCO_PATH', dirname( __FILE__ ) . '/' );
define( 'DGXPCO_BASENAME', plugin_basename( __FILE__ ) );

require_once __DIR__ . '/vendor/autoload.php';

// Activation.
register_activation_hook( __FILE__, '\DisplaceTech\DGXPCO\activate' );

// Bootstrap.
DisplaceTech\DGXPCO\setup();

// Initialization.
add_filter( 'upgrader_pre_download', 'DisplaceTech\\DGXPCO\\pre_download', 10, 3 );
