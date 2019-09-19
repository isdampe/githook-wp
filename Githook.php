<?php
/**
 * Plugin Name: Git Hook
 * Plugin URI: https://www.githook.io
 * Description: Integrate, program and customize WordPress themes and plugins with GitHub and GitLab events.
 * Version: 0.0.1
 * Requires at least: 4.7
 * Requires PHP: 7.0
 * Author: Richard Denton
 * Author URI: https://www.githook.io
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: githook
 */

define("GITHOOK_VERSION", "0.0.1");
define("GITHOOK_BASE_PATH", dirname(__FILE__));

require_once "lib/GithookIntercept.php";
require_once "lib/GithookPost.php";

$githook_post = new GithookPost();
