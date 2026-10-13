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
	$gtm_plugins = array(
		'duracelltomi-google-tag-manager' => 'GTM4WP',
		'google-site-kit'                 => 'Site Kit by Google',
		'insert-headers-and-footers'      => 'WPCode / Insert Headers and Footers',
		'header-footer-code-manager'      => 'Header Footer Code Manager',
	);

	$messages = array();
	foreach ( $cmps as $folder => $label ) {
		if ( in_array( $folder, $folders, true ) ) {
			$messages[] = $label . ' è attivo: con il banner GetTerms avresti due CMP. Disattivalo.';
		}
	}
	if ( mb_gt_get( 'gtm_enabled' ) ) {
		foreach ( $gtm_plugins as $folder => $label ) {
			if ( in_array( $folder, $folders, true ) ) {
				$messages[] = $label . ' è attivo: verifica che non inserisca anche GTM / gtag (rischio di doppio caricamento).';
			}
		}
	}
	return $messages;
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
		if ( $blockers && preg_match( '#<script\b[^>]*>#i', $head, $first, PREG_OFFSET_CAPTURE ) ) {
			if ( false !== stripos( $first[0][0], 'gettermscmp.com/cookie-consent/blocker/' ) ) {
				$add( 'ok', 'Il blocker GetTerms è il primo script dell\'head.' );
			} else {
				$add( 'error', 'Il blocker NON è il primo script. Primo script trovato: ' . trim( preg_replace( '/\s+/', ' ', substr( $head, $first[0][1], 160 ) ) ) );
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

		// Altri CMP nell'HTML.
		$signatures = array(
			'complianz'        => 'Complianz',
			'cmplz'            => 'Complianz',
			'cookiebot'        => 'Cookiebot',
			'cookieyes'        => 'CookieYes',
			'iubenda'          => 'iubenda',
			'borlabs'          => 'Borlabs',
			'cookie-law-info'  => 'CookieYes (legacy)',
			'realcookiebanner' => 'Real Cookie Banner',
		);
		$found = array();
		foreach ( $signatures as $needle => $label ) {
			if ( false !== stripos( $html, $needle ) ) {
				$found[ $label ] = $label;
			}
		}
		$add( $found ? 'error' : 'ok', $found ? 'Tracce di altri CMP nella pagina: ' . implode( ', ', $found ) . '.' : 'Nessun altro CMP rilevato nella pagina.' );

		foreach ( mb_gt_detect_conflicts() as $msg ) {
			$add( 'warn', $msg );
		}

		wp_send_json_success( $checks );
	}
);
