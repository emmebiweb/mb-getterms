<?php
/**
 * Plugin Name:       mB GetTerms Consent
 * Plugin URI:        https://github.com/emmebiweb/mb-getterms
 * Description:       Installa il CMP GetTerms (blocker + banner) come primo script dell'head, seguito da Google Tag Manager, più le policy GetTerms via shortcode. Alternativa leggera al plugin ufficiale, pensata per Google Consent Mode gestito in GTM.
 * Version:           1.4.7
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            mB
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mb-getterms
 * Update URI:        https://github.com/emmebiweb/mb-getterms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MB_GT_VERSION', '1.4.7' );
define( 'MB_GT_FILE', __FILE__ );
define( 'MB_GT_DIR', plugin_dir_path( __FILE__ ) );
define( 'MB_GT_URL', plugin_dir_url( __FILE__ ) );
define( 'MB_GT_BASENAME', plugin_basename( __FILE__ ) );
define( 'MB_GT_SLUG', 'mb-getterms' );
define( 'MB_GT_OPTION', 'mb_gt_settings' );

/*
 * Repository GitHub per gli aggiornamenti automatici (formato "owner/repo").
 * Va tenuto allineato con l'header "Update URI" qui sopra.
 * Sovrascrivibile in wp-config.php con define( 'MB_GT_GITHUB_REPO', 'owner/repo' ).
 */
if ( ! defined( 'MB_GT_GITHUB_REPO' ) ) {
	define( 'MB_GT_GITHUB_REPO', 'emmebiweb/mb-getterms' );
}

/**
 * Impostazioni di default.
 *
 * @return array
 */
function mb_gt_defaults() {
	return array(
		// Stato generale: finché è 0 il plugin non stampa nulla sul sito.
		'enabled'          => 0,
		// Dati GetTerms (da token o da embed code).
		'source'           => '',
		'token'            => '',
		'widget_slug'      => '',
		'languages'        => array(),
		'policies'         => array(),
		'default_language' => '',
		'last_sync'        => 0,
		// Lingua del banner.
		'lang_mode'        => 'fixed', // fixed | site.
		'lang'             => '',
		'auto_detect'      => 1,
		// Google Tag Manager.
		'gtm_enabled'      => 0,
		'gtm_id'           => '',
		'gtm_noscript'     => 1,
		// Pulsante "preferenze cookie" (riapertura banner).
		'btn_style'        => 'original', // original | custom.
		'btn_shape'        => 'square',   // square | round.
		'btn_bg'           => '#003a70',
		'btn_bg_hover'     => '#0a1f3d',
		'btn_icon'         => '#ffffff',
		'btn_width'        => 40,
		'btn_height'       => 40,
		'btn_side'         => 'left',     // left | right.
		'btn_offset_x'     => 0,
		'btn_offset_y'     => 20,
		// Mostra il pulsante preferenze solo in home (altrove si usa il link "#preferenze-cookie").
		'btn_home_only'    => 0,
		// Raggio angoli dei pulsanti nel banner e nella finestra preferenze ('' = originale).
		'btns_radius'      => '',
		// Distanza del banner (card/pill/popup) dal fondo della pagina, in px ('' = originale).
		'banner_offset_y'  => '',
		// Raggio degli angoli del banner (card/pill/popup) e della finestra preferenze ('' = originale).
		'banner_radius'    => '',
		// Pagine del sito a cui indirizzare i link delle policy nel banner (0 = pagina GetTerms).
		'policy_cookie_page'  => 0,
		'policy_privacy_page' => 0,
		// Completa view_item_list di GTM4WP per le griglie prodotti dei temi.
		'gtm4wp_lists'        => 0,
		// Elenco dei cookie (dal banner) e pulsante preferenze sotto la Cookie Policy.
		'cookie_list'         => 1,
	);
}

/**
 * Impostazioni salvate unite ai default.
 *
 * @param string|null $key Chiave singola opzionale.
 * @return mixed
 */
function mb_gt_get( $key = null ) {
	$saved = get_option( MB_GT_OPTION, array() );
	$all   = array_merge( mb_gt_defaults(), is_array( $saved ) ? $saved : array() );
	if ( null === $key ) {
		return $all;
	}
	return array_key_exists( $key, $all ) ? $all[ $key ] : null;
}

/**
 * Aggiorna una o più chiavi delle impostazioni.
 *
 * @param array $values Valori da unire.
 */
function mb_gt_update( array $values ) {
	update_option( MB_GT_OPTION, array_merge( mb_gt_get(), $values ), true );
}

/**
 * Normalizza un codice lingua GetTerms: minuscolo, trattino al posto dell'underscore.
 *
 * @param string $lang Codice lingua.
 * @return string
 */
function mb_gt_norm_lang( $lang ) {
	return strtolower( str_replace( '_', '-', trim( (string) $lang ) ) );
}

require_once MB_GT_DIR . 'includes/frontend.php';
require_once MB_GT_DIR . 'includes/updater.php';
require_once MB_GT_DIR . 'includes/gtm4wp-lists.php';
require_once MB_GT_DIR . 'includes/cookie-list.php';
require_once MB_GT_DIR . 'includes/preferences-link.php';

if ( is_admin() ) {
	require_once MB_GT_DIR . 'includes/admin.php';
	require_once MB_GT_DIR . 'includes/diagnostics.php';
}
