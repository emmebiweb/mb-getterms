<?php
/**
 * Link «Preferenze cookie» che riapre il banner da qualsiasi punto del sito.
 *
 * Utile quando il pulsante preferenze è visibile solo in home (opzione «Solo in home»)
 * o quando si preferisce un link nel footer. Funziona con:
 * - una voce di menu personalizzata con URL "#preferenze-cookie" (o "#cookie-preferences");
 * - lo shortcode [mb_getterms_preferences_link testo="..."];
 * - qualsiasi link il cui href finisce con "#preferenze-cookie".
 *
 * @package mb-getterms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Il link punta a un'ancora di riapertura del banner?
 *
 * @param string $href URL del link.
 * @return bool
 */
function mb_gt_is_preferences_href( $href ) {
	return (bool) preg_match( '/#(preferenze-cookie|cookie-preferences)$/i', (string) $href );
}

/**
 * Voci di menu: aggiunge l'attributo che GetTerms usa per aprire le preferenze.
 */
add_filter(
	'nav_menu_link_attributes',
	function ( $atts ) {
		if ( isset( $atts['href'] ) && mb_gt_is_preferences_href( $atts['href'] ) ) {
			$atts['data-gt-cookie-widget-show'] = 'true';
			$atts['role']                       = 'button';
		}
		return $atts;
	}
);

/**
 * Testo predefinito del link nella lingua del banner.
 *
 * @param string $lang Codice lingua.
 * @return string
 */
function mb_gt_preferences_link_text( $lang ) {
	$texts = array(
		'it' => 'Preferenze cookie',
		'en' => 'Cookie preferences',
		'de' => 'Cookie-Einstellungen',
		'fr' => 'Préférences cookies',
		'es' => 'Preferencias de cookies',
	);
	$short = substr( strtolower( (string) $lang ), 0, 2 );
	return isset( $texts[ $short ] ) ? $texts[ $short ] : $texts['en'];
}

add_shortcode(
	'mb_getterms_preferences_link',
	function ( $atts ) {
		$atts = shortcode_atts(
			array(
				'testo' => '',
				'text'  => '',
				'class' => '',
			),
			$atts,
			'mb_getterms_preferences_link'
		);
		$text = $atts['testo'] ? $atts['testo'] : $atts['text'];
		if ( '' === $text ) {
			$text = mb_gt_preferences_link_text( function_exists( 'mb_gt_resolve_lang' ) ? mb_gt_resolve_lang() : '' );
		}
		$class = trim( 'mb-gt-preferences-link ' . $atts['class'] );
		return '<a href="#preferenze-cookie" class="' . esc_attr( $class ) . '" role="button" data-gt-cookie-widget-show="true">' . esc_html( $text ) . '</a>';
	}
);

/**
 * Riserva per i link inseriti a mano (senza l'attributo): apre le preferenze via API GetTerms.
 */
function mb_gt_preferences_link_script() {
	if ( ! function_exists( 'mb_gt_should_output' ) || ! mb_gt_should_output() ) {
		return;
	}
	echo '<script id="mb-gt-preferences-link" data-cfasync="false" data-no-optimize="1">document.addEventListener("click",function(e){var a=e.target&&e.target.closest?e.target.closest("a[href]"):null;if(!a||!/#(preferenze-cookie|cookie-preferences)$/i.test(a.getAttribute("href")))return;if(a.hasAttribute("data-gt-cookie-widget-show"))return;e.preventDefault();if(window.GetTermsCMP&&typeof window.GetTermsCMP.showPreferences==="function"){window.GetTermsCMP.showPreferences();}},true);</script>' . "\n";
}
add_action( 'wp_footer', 'mb_gt_preferences_link_script', 30 );
