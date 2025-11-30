<?php
/**
 * The base configuration for WordPress
 *
 * NexMart E-commerce WordPress Configuration
 *
 * @package WordPress
 */

// ** Database settings ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'nexmart_db' );

/** Database username */
define( 'DB_USER', 'nexmart_user' );

/** Database password */
define( 'DB_PASSWORD', 'nexmart_pass123' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 */
define( 'AUTH_KEY',         'xK7!pQ#mN9$vL2@wR5^tY8&uI1*oP4(aS6)dF3_gH0+jZ' );
define( 'SECURE_AUTH_KEY',  'bC9!eV2#nM5$xL8@kJ1^qW4&rT7*yU0(iO3)pA6_sD+fG' );
define( 'LOGGED_IN_KEY',    'hZ5!lK8#oI1$uY4@tR7^eW0&qS3*aD6(fG9)jH2_mN+xC' );
define( 'NONCE_KEY',        'vB3!nM6#xC9$zL2@kJ5^qW8&eR1*tY4(uI7)oP0_aS+dF' );
define( 'AUTH_SALT',        'gH1!jK4#lM7$nB0@vC3^xZ6&aS9*dF2(qW5)eR8_tY+uI' );
define( 'SECURE_AUTH_SALT', 'oP7!iU0#yT3$rE6@wQ9^sA2&dF5*gH8(jK1)lM4_nB+vC' );
define( 'LOGGED_IN_SALT',   'xZ4!cV7#bN0$mL3@kJ6^qW9&eR2*tY5(uI8)oP1_aS+dF' );
define( 'NONCE_SALT',       'gH8!jK1#lM4$nB7@vC0^xZ3&aS6*dF9(qW2)eR5_tY+uI' );

/**#@-*/

/**
 * WordPress database table prefix.
 */
$table_prefix = 'nxm_';

/**
 * For developers: WordPress debugging mode.
 */
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', true );

/** File system method */
define( 'FS_METHOD', 'direct' );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
