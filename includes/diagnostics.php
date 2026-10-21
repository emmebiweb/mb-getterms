<?php
/**
 * Avvisi di conflitto in bacheca e verifica della homepage.
 *
 * @package mb-getterms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin attivi che entrano in conflitto (altri CMP o altri inseritori di GTM).
 *
 * @return array Elenco di messaggi.
 */
function mb_gt_detect_conflicts() {
	$active = (array) get_option( 'active_plugins', array() );
	if ( is_multisite() ) {
		$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
	}
	$folders = array();
	foreach ( $active as $plugin ) {
		$folders[] = strtolower( strtok( $plugin, '/' ) );
	}

	$cmps = array(
		'complianz-gdpr'                    => 'Complianz',
		'complianz-gdpr-premium'            => 'Complianz Premium',
		'cookiebot'                         => 'Cookiebot',
		'cookie-law-info'                   => 'CookieYes',
		'cookieyes'                         => 'CookieYes',
		'iubenda-cookie-law-solution'       => 'iubenda',
		'borlabs-cookie'                    => 'Borlabs Cookie',
		'real-cookie-banner'                => 'Real Cookie Banner',
		'real-cookie-banner-pro'            => 'Real Cookie Banner Pro',
		'gdpr-cookie-compliance'            => 'GDPR Cookie Compliance',
		'cookie-notice'                     => 'Cookie Notice',
		'getterms-cookie-consent-policies'  => 'GetTerms (plugin ufficiale)',
	);
	$code_plugins = array(
		'insert-headers-and-footers' => 'WPCode / Insert Headers and Footers',
		'header-footer-code-manager' => 'Header Footer Code Manager',
	);

	$messages = array();
	foreach ( $cmps as $folder => $label ) {
		if ( in_array( $folder, $folders, true ) ) {
			$messages[] = $label . ' è attivo: con il banner GetTerms avresti due CMP. Disattivalo.';
		}
	}
	if ( mb_gt_get( 'gtm_enabled' ) ) {
		if ( in_array( 'duracelltomi-google-tag-manager', $folders, true ) && mb_gt_gtm4wp_prints_container() ) {
			$messages[] = 'GTM4WP inserisce anche lui il container GTM (doppio caricamento). In Impostazioni → Google Tag Manager imposta "Container code placement" su "Off": i dati e-commerce nel dataLayer continuano a funzionare.';
		}
		if ( in_array( 'google-site-kit', $folders, true ) ) {
			$tags = mb_gt_sitekit_snippets();
			if ( $tags ) {
				$messages[] = 'Site Kit by Google inserisce ' . implode( ' e ', $tags ) . ' (doppio caricamento con GTM). In Site Kit → Impostazioni disattiva l\'inserimento dello snippet per questi servizi.';
			}
		}
		foreach ( $code_plugins as $folder => $label ) {
			if ( in_array( $folder, $folders, true ) ) {
				$messages[] = $label . ' è attivo: controlla che i suoi snippet non contengano GTM o gtag (doppio caricamento).';
			}
		}
	}
	return $messages;
}

/**
 * GTM4WP stampa il container? Solo se ha un ID e il posizionamento non è "Off".
 *
 * @return bool
 */
function mb_gt_gtm4wp_prints_container() {
	$opt = get_option( 'gtm4wp-options', array() );
	if ( ! is_array( $opt ) || empty( $opt['gtm-code'] ) ) {
		return false;
	}
	$off = defined( 'GTM4WP_PLACEMENT_OFF' ) ? (int) GTM4WP_PLACEMENT_OFF : 3;
	return ! isset( $opt['gtm-code-placement'] ) || (int) $opt['gtm-code-placement'] !== $off;
}

/**
 * Servizi per cui Site Kit inserisce il proprio snippet (GA4, Tag Manager, Google Ads).
 *
 * @return array Etichette dei servizi.
 */
function mb_gt_sitekit_snippets() {
	$active  = (array) get_option( 'googlesitekit_active_modules', array() );
	$modules = array(
		'analytics-4' => array( 'googlesitekit_analytics-4_settings', 'measurementID', 'il tag GA4' ),
		'tagmanager'  => array( 'googlesitekit_tagmanager_settings', 'containerID', 'il container GTM' ),
		'ads'         => array( 'googlesitekit_ads_settings', 'conversionID', 'il tag Google Ads' ),
	);
	$tags = array();
	foreach ( $modules as $slug => $m ) {
		if ( ! in_array( $slug, $active, true ) ) {
			continue;
		}
		$s = get_option( $m[0], array() );
		if ( ! is_array( $s ) || empty( $s[ $m[1] ] ) ) {
			continue;
		}
		if ( ! isset( $s['useSnippet'] ) || $s['useSnippet'] ) {
			$tags[] = $m[2];
		}
	}
	return $tags;
}

/**
 * Avviso in bacheca quando il banner è attivo e ci sono conflitti.
 */
add_action(
	'admin_notices',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! mb_gt_get( 'enabled' ) ) {
			return;
		}
		// Solo in Bacheca, nella pagina Plugin e nelle impostazioni del plugin.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins', 'settings_page_' . MB_GT_SLUG ), true ) ) {
			return;
		}
		$conflicts = mb_gt_detect_conflicts();
		if ( ! $conflicts ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>mB GetTerms Consent:</strong></p><ul style="list-style:disc;margin-left:20px">';
		foreach ( $conflicts as $msg ) {
			echo '<li>' . esc_html( $msg ) . '</li>';
		}
		echo '</ul></div>';
	}
);

/**
 * AJAX: scarica la homepage e la analizza.
 */
add_action(
	'wp_ajax_mb_gt_check',
	function () {
		check_ajax_referer( 'mb_gt_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permessi insufficienti.' );
		}

		$url      = add_query_arg( 'mb_gt_check', time(), home_url( '/' ) );
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 25,
				'user-agent' => 'Mozilla/5.0 (mB GetTerms check)',
			)
		);
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( 'Impossibile scaricare la homepage: ' . $response->get_error_message() );
		}
		$html = (string) wp_remote_retrieve_body( $response );
		$code = (int) wp_remote_retrieve_response_code( $response );

		$checks = array();
		$add    = function ( $status, $text ) use ( &$checks ) {
			$checks[] = array(
				'status' => $status,
				'text'   => $text,
			);
		};

		$add( 200 === $code ? 'ok' : 'warn', 'Homepage: HTTP ' . $code . ', ' . size_format( strlen( $html ) ) . '.' );

		// Banner GetTerms.
		$blockers = preg_match_all( '#<script[^>]+src=["\'][^"\']*gettermscmp\.com/cookie-consent/blocker/#i', $html );
		$widgets  = preg_match_all( '#<script[^>]+src=["\'][^"\']*gettermscmp\.com/cookie-consent/widget/#i', $html );
		if ( 1 === $blockers && 1 === $widgets ) {
			$add( 'ok', 'Banner GetTerms presente una volta (blocker + widget).' );
		} elseif ( 0 === $blockers ) {
			$add( mb_gt_get( 'enabled' ) ? 'error' : 'info', 'Banner GetTerms non trovato' . ( mb_gt_get( 'enabled' ) ? ' (attivo nelle impostazioni: svuota la cache).' : ' (plugin non attivo).' ) );
		} else {
			$add( 'error', 'Banner GetTerms duplicato: ' . $blockers . ' blocker e ' . $widgets . ' widget. Controlla plugin ufficiale / tema.' );
		}

		// Il blocker è il primo script dell'head?
		$head_end = stripos( $html, '</head>' );
		$head     = false === $head_end ? $html : substr( $html, 0, $head_end );
		$pos_b    = stripos( $head, 'gettermscmp.com/cookie-consent/blocker/' );
		if ( $blockers && false !== $pos_b ) {
			// Tutti i tag <script> che aprono prima del blocker.
			preg_match_all( '#<script\b[^>]*>#i', substr( $head, 0, $pos_b ), $before, PREG_OFFSET_CAPTURE );
			$before   = $before[0];
			// L'ultimo è il tag del blocker stesso se l'attributo src è dentro quel tag.
			if ( $before ) {
				$last = end( $before );
				if ( false === strpos( substr( $head, $last[1], $pos_b - $last[1] ), '>' ) ) {
					array_pop( $before );
				}
			}
			$external = array();
			$inline   = array();
			foreach ( $before as $tag ) {
				$snippet = trim( preg_replace( '/\s+/', ' ', substr( $head, $tag[1], 140 ) ) );
				if ( preg_match( '#\ssrc\s*=#i', $tag[0] ) ) {
					$external[] = $snippet;
				} elseif ( ! preg_match( '#type\s*=\s*["\']?(application/(ld\+)?json|text/template|text/x-template)#i', $tag[0] ) ) {
					$inline[] = $snippet;
				}
			}
			if ( $external ) {
				$add( 'error', 'Il blocker NON è il primo script: prima ci sono ' . count( $external ) . ' script esterni. Primo: ' . $external[0] );
			} elseif ( $inline ) {
				$add( 'warn', 'Prima del blocker ci sono ' . count( $inline ) . ' script inline (di solito innocui: non caricano risorse esterne). Primo: ' . $inline[0] );
			} else {
				$add( 'ok', 'Il blocker GetTerms è il primo script dell\'head.' );
			}
		}

		// GTM.
		preg_match_all( '#GTM-[A-Z0-9]{4,10}#', $html, $ids );
		$ids     = array_values( array_unique( $ids[0] ) );
		$loaders = preg_match_all( '#googletagmanager\.com/gtm\.js#', $html );
		if ( ! $ids && ! $loaders ) {
			$add( mb_gt_gtm_id() ? 'error' : 'info', 'Nessun Google Tag Manager trovato.' );
		} else {
			$add( 1 === $loaders && count( $ids ) === 1 ? 'ok' : 'warn', 'GTM trovati: ' . ( $ids ? implode( ', ', $ids ) : 'ID non leggibile' ) . ' — loader gtm.js: ' . $loaders . '.' );
			if ( $blockers ) {
				$pos_blocker = stripos( $html, 'gettermscmp.com/cookie-consent/blocker/' );
				$pos_gtm     = stripos( $html, 'googletagmanager.com/gtm.js' );
				if ( false !== $pos_gtm ) {
					$add( $pos_gtm > $pos_blocker ? 'ok' : 'error', $pos_gtm > $pos_blocker ? 'GTM viene caricato dopo il banner.' : 'GTM viene caricato PRIMA del banner GetTerms.' );
				}
			}
		}
		if ( preg_match( '#googletagmanager\.com/gtag/js\?id=(G-|AW-|UA-)#', $html ) ) {
			$add( 'warn', 'Trovato un gtag.js diretto (GA4/Ads fuori da GTM): va spostato in GTM o lasciato al Consent Mode.' );
		}

		// Altri CMP attivi: cerca solo dentro i tag <script> (src o contenuto),
		// così testi, immagini o CSS che citano il nome di un CMP non contano.
		$signatures = array(
			'#complianz[^"\'\s]*\.js|var\s+complianz\s*=|cmplz-cookiebanner#i' => 'Complianz',
			'#consent(cdn)?\.cookiebot\.(com|eu)#i'                                => 'Cookiebot',
			'#cdn-cookieyes\.com|cookie-law-info[^"\'\s]*\.js#i'                     => 'CookieYes',
			'#(cdn|cs)\.iubenda\.com|_iub\s*=#i'                                      => 'iubenda',
			'#borlabs-cookie[^"\'\s]*\.js|BorlabsCookie#i'                            => 'Borlabs Cookie',
			'#real-cookie-banner[^"\'\s]*\.js|realCookieBanner#i'                     => 'Real Cookie Banner',
		);
		preg_match_all( '#<script\b[^>]*>.*?</script>#is', $html, $scripts );
		$scripts = implode( "\n", $scripts[0] );
		$found   = array();
		foreach ( $signatures as $pattern => $label ) {
			if ( preg_match( $pattern, $scripts ) ) {
				$found[ $label ] = $label;
			}
		}
		$add( $found ? 'error' : 'ok', $found ? 'Script di altri CMP attivi nella pagina: ' . implode( ', ', $found ) . '.' : 'Nessun altro CMP attivo nella pagina.' );

		foreach ( mb_gt_detect_conflicts() as $msg ) {
			$add( 'warn', $msg );
		}

		wp_send_json_success( $checks );
	}
);
