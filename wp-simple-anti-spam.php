<?php
/**
 * Plugin Name: WP Simple Anti Spam
 * Description: No config, no big complicated 3rd party spam check. Just simple local, maybe not 100% (but enough) anti-spam solution :)
 * Version: 1.1.0
 * Plugin URI: https://jaimemartinez.nl/open-source/simple-anti-spam
 * Author: Jaime Martinez
 * Author URI: https://jaimemartinez.nl
 * License: GPL v3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * Props URI: Danny van Kooten > https://gist.github.com/dannyvankooten/c6b3e08e0bd4178eca6acd05df18a67a
 *
 * @package WPSimpleAntiSpam
 */

declare( strict_types=1 );

require_once __DIR__ . '/app/Check.php';
require_once __DIR__ . '/app/Comment.php';
require_once __DIR__ . '/app/GravityForms.php';

add_action(
	'plugins_loaded',
	function () {
		new WPSimpleAntiSpam\Comment();
		new WPSimpleAntiSpam\GravityForms();
	}
);
