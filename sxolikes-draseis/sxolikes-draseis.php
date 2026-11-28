<?php
/**
 * Plugin Name:       Σχολικές Δράσεις
 * Description:       Σχεδιασμός Εργαστηρίων Δεξιοτήτων και Δράσεων Ενεργού Πολίτη, συνδεδεμένων με τα Σχέδια Δράσης της εσωτερικής αξιολόγησης.
 * Version:           0.24.1
 * Update URI:        https://github.com/stam1966/sxolikes-draseis
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            22ο Δημοτικό Σχολείο Λαμίας – Ροδίτσας
 * Text Domain:       sxolikes-draseis
 * License:           GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SD_VERSION', '0.24.1' );
define( 'SD_DB_VERSION', '9' );
define( 'SD_FILE', __FILE__ );
define( 'SD_DIR', plugin_dir_path( __FILE__ ) );
define( 'SD_URL', plugin_dir_url( __FILE__ ) );

require_once SD_DIR . 'includes/class-sd-data.php';
require_once SD_DIR . 'includes/class-sd-eval.php';
require_once SD_DIR . 'includes/class-sd-plans.php';
require_once SD_DIR . 'includes/class-sd-seed.php';
require_once SD_DIR . 'includes/class-sd-workshops.php';
require_once SD_DIR . 'includes/class-sd-citizen.php';
require_once SD_DIR . 'includes/class-sd-ai.php';
require_once SD_DIR . 'includes/class-sd-settings.php';
require_once SD_DIR . 'includes/class-sd-map.php';
require_once SD_DIR . 'includes/class-sd-office.php';
require_once SD_DIR . 'includes/class-sd-requests.php';
require_once SD_DIR . 'includes/class-sd-f4.php';
require_once SD_DIR . 'includes/class-sd-exports.php';
require_once SD_DIR . 'includes/class-sd-docs.php';
require_once SD_DIR . 'includes/class-sd-wizard.php';
require_once SD_DIR . 'includes/class-sd-board.php';
require_once SD_DIR . 'includes/class-sd-staff.php';
require_once SD_DIR . 'includes/class-sd-planui.php';
require_once SD_DIR . 'includes/class-sd-users.php';
require_once SD_DIR . 'includes/class-sd-ergplan.php';
require_once SD_DIR . 'includes/class-sd-timeline.php';
require_once SD_DIR . 'includes/class-sd-aifield.php';
require_once SD_DIR . 'includes/class-sd-planimport.php';
require_once SD_DIR . 'includes/class-sd-archparse.php';
require_once SD_DIR . 'includes/class-sd-archive.php';
require_once SD_DIR . 'includes/class-sd-guide.php';
require_once SD_DIR . 'includes/class-sd-notify.php';
require_once SD_DIR . 'includes/class-sd-updater.php';

SD_Updater::init(); // ενημερώσεις από το GitHub (και στο cron, όχι μόνο στη διαχείριση)
require_once SD_DIR . 'includes/class-sd-roles.php';
require_once SD_DIR . 'includes/class-sd-install.php';
require_once SD_DIR . 'includes/class-sd-access.php';
require_once SD_DIR . 'includes/class-sd-frontend.php';

register_activation_hook( __FILE__, array( 'SD_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SD_Install', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'SD_Install', 'maybe_upgrade' ) );
add_action( 'init', array( 'SD_Access', 'init' ) );
add_action( 'init', array( 'SD_Frontend', 'init' ) );
add_action( 'init', array( 'SD_Map', 'init' ) );
add_action( 'init', array( 'SD_AiField', 'init' ) );
add_action( 'init', array( 'SD_Notify', 'init' ) );
add_action( 'init', array( 'SD_Wizard', 'init' ) );
add_action( 'init', array( 'SD_Exports', 'init' ) );
