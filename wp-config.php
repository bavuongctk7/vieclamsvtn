<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'u299971761_vlsv');

/** MySQL database username */
define('DB_USER', 'u299971761_vlsv');

/** MySQL database password */
define('DB_PASSWORD', 'vertrigo');

/** MySQL hostname */
define('DB_HOST', 'mysql.hostinger.vn');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         ',dsl,QRy*MZrm$MnqhgWh(#}VErM1k@D57=j 0&*(.25UYO!1K!CBxCiIFJt$W7~');
define('SECURE_AUTH_KEY',  'SiLRX>9IWHF+&OwY9[W@ ,,Puq Ek*b?U4n8JOR9MCu#^G_W*zl.@(m~R.vo4D+P');
define('LOGGED_IN_KEY',    'IRc_KIil}F,1l4S2Za[XU*GL.q1AgtX$#XI|+@kfQ/)4X_m]0Z.wgU9ejF7_LkB~');
define('NONCE_KEY',        'r]~NLw/V`|`[[%be81>-[_4I${gV/: b QnwnoReTg-{OHhhPp>a|??M8g3U<hxc');
define('AUTH_SALT',        't@p#F_6_;73j=JP1a:u8S]LK8@3<{#D1#$fGa?$wt+>tie,V1^Vu?`+a[HGB^m=,');
define('SECURE_AUTH_SALT', '3)(`rA6f`#`,`*nw:X-L%G4pmR8i|[c>XE*qhW/0F[_c;Q1k5}6SGyV>Uv`P1JF,');
define('LOGGED_IN_SALT',   '3Ic[M>IH.N<bB K&GV?w/1*d&@zq]T 3Q3J{:v-ov~Vs 3BLCX|;AJ}3v;8Um4fH');
define('NONCE_SALT',       'y n_N(ub?UBS>Cs63>$Rm/$cOLr0mNZ-{?#Fh|(Wx/(g$IX,Npa]l7)_#3,YnZ@<');

define( 'WP_MEMORY_LIMIT', '256M' );

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
