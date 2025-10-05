<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'rzh;;UWpeU3mTvn!>#}h&ii(vv#*3q=}6T)a4rv<nn(TgV%=OEk0BO`SRd}8v=cn' );
define( 'SECURE_AUTH_KEY',  ':ZkV!/|!rPu8pmS-`7^Lu8D!@ii&q<Qw]4&XcgMbr:$J/z`K=H[oeNQ)RqwM^xed' );
define( 'LOGGED_IN_KEY',    '|@,oSM4W9<LS:iB~2[R-K%*Jz|X4UAVe4wYY)9Haf$M-LoIva3kFCUq[[C(TF~?b' );
define( 'NONCE_KEY',        '<h#[J?^&}TIpjzXSo)>AGb+B_n=r5IESS-y3a=K`tMoBm?5P45HhD9&+]T ea$X+' );
define( 'AUTH_SALT',        'd~?U26-J-~dJRT0Wd8WB3>46J]ls9de<bl3~ij|do~VX9f-7]wxGI~B336<cmqKK' );
define( 'SECURE_AUTH_SALT', 'lNbrUU82>/YHV#mgWnh8Z%}:CzXloTBZ89bNWCHOny#&{N1H@D^Xrib-&5@4g?3H' );
define( 'LOGGED_IN_SALT',   '4p-N:5XYZDKv17UhF;4k~^,Xyc)1y$)Owo?W[w187m/MONIO$/KyUN`luy.GKrW>' );
define( 'NONCE_SALT',       ',50R6YN*CXNi}a7Ah2{h<13J Frg8GCo.2@tGZ$!IO}^3>qQ5c^UpqCh{_z+wGT^' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
