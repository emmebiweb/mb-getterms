<?php
/**
 * Pagina impostazioni, sincronizzazione col token GetTerms, link azione.
 *
 * @package mb-getterms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Voce di menu in Impostazioni.
 */
add_action(
	'admin_menu',
	function () {
		add_options_page(
			'mB GetTerms Consent',
			'GetTerms (mB)',
			'manage_options',
			MB_GT_SLUG,
			'mb_gt_render_settings_page'
		);
	}
);

/**
 * Link "Impostazioni" nella lista plugin.
 */
add_filter(
	'plugin_action_links_' . MB_GT_BASENAME,
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . MB_GT_SLUG ) ) . '">Impostazioni</a>' );
		return $links;
	}
);

/**
 * Registrazione opzione.
 */
add_action(
	'admin_init',
	function () {
		register_setting(
			'mb_gt_group',
			MB_GT_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => 'mb_gt_sanitize_settings',
				'default'           => mb_gt_defaults(),
			)
		);
	}
);

/**
 * Estrae UUID, lingua e auto-detect da un embed code GetTerms.
 *
 * @param string $code Embed code incollato.
 * @return array|null { slug, lang, auto }
 */
function mb_gt_parse_embed_code( $code ) {
	if ( preg_match( '#gettermscmp\.com/cookie-consent/(?:blocker|widget)/([a-f0-9-]{36})/([a-z]{2,3}(?:[-_][a-z0-9]{2,4})?)(\?auto=true)?#i', (string) $code, $m ) ) {
		return array(
			'slug' => strtolower( $m[1] ),
			'lang' => mb_gt_norm_lang( $m[2] ),
			'auto' => ! empty( $m[3] ),
		);
	}
	return null;
}

/**
 * Sanitizza il form, preservando i dati sincronizzati che non sono nel form.
 *
 * @param mixed $input Dati inviati.
 * @return array
 */
function mb_gt_sanitize_settings( $input ) {
	$current = mb_gt_get();
	$input   = is_array( $input ) ? $input : array();

	// Chiamate interne (mb_gt_update) passano l'array completo: lo accettiamo così com'è.
	if ( isset( $input['widget_slug'] ) && ! isset( $input['_form'] ) ) {
		return array_merge( mb_gt_defaults(), $input );
	}

	$out = $current;

	$out['enabled']      = empty( $input['enabled'] ) ? 0 : 1;
	$out['auto_detect']  = empty( $input['auto_detect'] ) ? 0 : 1;
	$out['lang_mode']    = ( isset( $input['lang_mode'] ) && 'site' === $input['lang_mode'] ) ? 'site' : 'fixed';
	$out['lang']         = isset( $input['lang'] ) ? mb_gt_norm_lang( sanitize_text_field( $input['lang'] ) ) : $current['lang'];
	$out['gtm_enabled']  = empty( $input['gtm_enabled'] ) ? 0 : 1;
	$out['gtm_noscript'] = empty( $input['gtm_noscript'] ) ? 0 : 1;

	// Pulsante preferenze cookie.
	$out['btn_style'] = ( isset( $input['btn_style'] ) && 'custom' === $input['btn_style'] ) ? 'custom' : 'original';
	$out['btn_shape'] = ( isset( $input['btn_shape'] ) && 'round' === $input['btn_shape'] ) ? 'round' : 'square';
	$out['btn_side']  = ( isset( $input['btn_side'] ) && 'right' === $input['btn_side'] ) ? 'right' : 'left';
	foreach ( array( 'btn_bg', 'btn_bg_hover', 'btn_icon' ) as $key ) {
		$color       = isset( $input[ $key ] ) ? sanitize_hex_color( $input[ $key ] ) : '';
		$out[ $key ] = $color ? $color : $current[ $key ];
	}
	$ranges = array(
		'btn_width'    => array( 20, 120 ),
		'btn_height'   => array( 20, 120 ),
		'btn_offset_x' => array( 0, 500 ),
		'btn_offset_y' => array( 0, 1000 ),
	);
	foreach ( array( 'policy_cookie_page', 'policy_privacy_page' ) as $key ) {
		$out[ $key ] = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : (int) $current[ $key ];
	}

	$radius             = isset( $input['btns_radius'] ) ? trim( (string) $input['btns_radius'] ) : (string) $current['btns_radius'];
	$out['btns_radius'] = ( '' === $radius ) ? '' : max( 0, min( 100, (int) $radius ) );

	foreach ( $ranges as $key => $range ) {
		$val         = isset( $input[ $key ] ) ? (int) $input[ $key ] : (int) $current[ $key ];
		$out[ $key ] = max( $range[0], min( $range[1], $val ) );
	}

	$gtm_id = isset( $input['gtm_id'] ) ? strtoupper( trim( sanitize_text_field( $input['gtm_id'] ) ) ) : '';
	if ( '' !== $gtm_id && ! preg_match( '/^GTM-[A-Z0-9]+$/', $gtm_id ) ) {
		add_settings_error( MB_GT_OPTION, 'gtm', 'ID GTM non valido: deve avere il formato GTM-XXXXXXX.' );
		$gtm_id = $current['gtm_id'];
	}
	$out['gtm_id'] = $gtm_id;
	if ( $out['gtm_enabled'] && '' === $gtm_id ) {
		add_settings_error( MB_GT_OPTION, 'gtm_empty', 'GTM attivo ma senza ID: GTM non verrà stampato.', 'warning' );
	}

	// Token: se cambia, i dati sincronizzati vanno rifatti.
	$token = isset( $input['token'] ) ? preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $input['token'] ) : $current['token'];
	if ( $token !== $current['token'] ) {
		$out['token'] = $token;
		if ( 'token' === $current['source'] ) {
			$out['last_sync'] = 0;
		}
	}

	// Embed code incollato: ha la precedenza per UUID / lingua / auto-detect.
	if ( ! empty( $input['embed_code'] ) ) {
		$parsed = mb_gt_parse_embed_code( wp_unslash( $input['embed_code'] ) );
		if ( $parsed ) {
			$out['widget_slug'] = $parsed['slug'];
			$out['lang']        = $parsed['lang'];
			$out['auto_detect'] = $parsed['auto'] ? 1 : 0;
			$out['source']      = 'embed';
			if ( empty( $out['default_language'] ) ) {
				$out['default_language'] = $parsed['lang'];
			}
			add_settings_error( MB_GT_OPTION, 'embed_ok', 'Embed code letto: banner ' . $parsed['slug'] . ', lingua ' . $parsed['lang'] . '.', 'success' );
		} else {
			add_settings_error( MB_GT_OPTION, 'embed_ko', 'Embed code non riconosciuto: nessun URL gettermscmp.com/cookie-consent/... trovato.' );
		}
	}

	if ( $out['enabled'] && empty( $out['widget_slug'] ) ) {
		$out['enabled'] = 0;
		add_settings_error( MB_GT_OPTION, 'no_slug', 'Banner non attivato: prima collega il token GetTerms o incolla l\'embed code.' );
	}

	return $out;
}

/**
 * Normalizza la risposta dell'API GetTerms (a volte è JSON codificato più volte).
 *
 * @param mixed $data Risposta.
 * @return array|null
 */
function mb_gt_decode_api_response( $data ) {
	for ( $i = 0; $i < 3 && is_string( $data ); $i++ ) {
		$decoded = json_decode( $data, true );
		if ( null === $decoded ) {
			return null;
		}
		$data = $decoded;
	}
	return ( is_array( $data ) && ! empty( $data['token'] ) && ! empty( $data['widget_slug'] ) ) ? $data : null;
}

/**
 * Salva i dati restituiti dall'API GetTerms.
 *
 * @param array $data Dati API decodificati.
 * @return array Impostazioni aggiornate.
 */
function mb_gt_store_api_data( array $data ) {
	$languages = array();
	if ( ! empty( $data['available_languages'] ) && is_array( $data['available_languages'] ) ) {
		foreach ( $data['available_languages'] as $code => $name ) {
			$languages[ sanitize_key( $code ) ] = sanitize_text_field( is_string( $name ) ? $name : $code );
		}
	}
	$policies = array();
	if ( ! empty( $data['available_policies'] ) && is_array( $data['available_policies'] ) ) {
		foreach ( $data['available_policies'] as $p ) {
			$policies[] = sanitize_key( $p );
		}
	}
	$current = mb_gt_get();
	$default = mb_gt_norm_lang( isset( $data['default_language'] ) ? sanitize_text_field( $data['default_language'] ) : '' );

	$values = array(
		'source'           => 'token',
		'token'            => preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $data['token'] ),
		'widget_slug'      => sanitize_text_field( $data['widget_slug'] ),
		'languages'        => $languages,
		'policies'         => array_values( array_unique( $policies ) ),
		'default_language' => $default,
		'last_sync'        => time(),
	);
	if ( empty( $current['lang'] ) ) {
		$values['lang'] = $default;
	}
	mb_gt_update( $values );
	return mb_gt_get();
}

/**
 * AJAX: salva i dati ottenuti dal browser.
 */
add_action(
	'wp_ajax_mb_gt_save_sync',
	function () {
		check_ajax_referer( 'mb_gt_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permessi insufficienti.' );
		}
		$raw  = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$data = mb_gt_decode_api_response( $raw );
		if ( ! $data ) {
			wp_send_json_error( 'Risposta GetTerms non valida.' );
		}
		mb_gt_store_api_data( $data );
		wp_send_json_success( 'Collegato a GetTerms.' );
	}
);

/**
 * AJAX: sincronizzazione lato server (se la chiamata dal browser fallisce).
 */
add_action(
	'wp_ajax_mb_gt_sync_server',
	function () {
		check_ajax_referer( 'mb_gt_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permessi insufficienti.' );
		}
		$token = isset( $_POST['token'] ) ? preg_replace( '/[^A-Za-z0-9_-]/', '', wp_unslash( $_POST['token'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! $token ) {
			wp_send_json_error( 'Token mancante.' );
		}
		$host     = wp_parse_url( home_url(), PHP_URL_HOST );
		$origin   = 'https://' . $host;
		$response = wp_remote_get(
			'https://app.getterms.io/api/wp-plugin/v1/' . rawurlencode( $token ) . '?dm=' . rawurlencode( base64_encode( $origin ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			array(
				'timeout' => 15,
				'headers' => array(
					'X-Site-Domain' => $host,
					'Origin'        => $origin,
					'Referer'       => admin_url(),
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( 'Errore di rete: ' . $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = mb_gt_decode_api_response( $body );
		if ( 200 !== $code || ! $data ) {
			$msg  = json_decode( $body, true );
			$text = ( is_array( $msg ) && ! empty( $msg['message'] ) ) ? $msg['message'] : 'risposta non valida (HTTP ' . $code . ')';
			wp_send_json_error( 'GetTerms: ' . sanitize_text_field( $text ) );
		}
		mb_gt_store_api_data( $data );
		wp_send_json_success( 'Collegato a GetTerms (lato server).' );
	}
);

/**
 * Asset admin solo sulla nostra pagina.
 */
add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'settings_page_' . MB_GT_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'mb-gt-admin', MB_GT_URL . 'assets/admin.css', array( 'wp-color-picker' ), MB_GT_VERSION );
		wp_enqueue_script( 'mb-gt-admin', MB_GT_URL . 'assets/admin.js', array( 'jquery', 'wp-color-picker' ), MB_GT_VERSION, true );
		wp_localize_script(
			'mb-gt-admin',
			'mbGtAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mb_gt_admin' ),
				'host'    => wp_parse_url( home_url(), PHP_URL_HOST ),
			)
		);
	}
);

/**
 * Riga di impostazione: etichetta a sinistra, controllo a destra.
 *
 * @param string $label   Etichetta (HTML già sicuro).
 * @param string $control HTML del controllo.
 * @param string $help    Testo di aiuto (HTML già sicuro).
 * @param string $class   Classe extra della riga.
 */
function mb_gt_row( $label, $control, $help = '', $class = '' ) {
	echo '<div class="mb-gt-row ' . esc_attr( $class ) . '"><div class="mb-gt-row__label">' . $label . '</div><div class="mb-gt-row__control">' . $control; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	if ( $help ) {
		echo '<p class="mb-gt-help">' . $help . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo '</div></div>';
}

/**
 * Interruttore on/off.
 *
 * @param string $field  Nome campo.
 * @param mixed  $value  Valore attuale.
 * @param string $text   Testo accanto.
 * @param bool   $locked Disabilitato.
 * @return string
 */
function mb_gt_toggle( $field, $value, $text, $locked = false ) {
	return '<label class="mb-gt-switch"><input type="checkbox" name="' . esc_attr( MB_GT_OPTION . '[' . $field . ']' ) . '" value="1"' . checked( (bool) $value, true, false ) . disabled( $locked, true, false ) . '><span class="mb-gt-switch__track" aria-hidden="true"></span><span class="mb-gt-switch__text">' . esc_html( $text ) . '</span></label>';
}

/**
 * Gruppo di scelte a "pillole" (radio).
 *
 * @param string $field   Nome campo.
 * @param string $value   Valore attuale.
 * @param array  $choices valore => etichetta.
 * @return string
 */
function mb_gt_segmented( $field, $value, array $choices ) {
	$html = '<div class="mb-gt-seg" role="radiogroup">';
	foreach ( $choices as $val => $label ) {
		$html .= '<label><input type="radio" name="' . esc_attr( MB_GT_OPTION . '[' . $field . ']' ) . '" value="' . esc_attr( $val ) . '"' . checked( (string) $value, (string) $val, false ) . '><span>' . esc_html( $label ) . '</span></label>';
	}
	return $html . '</div>';
}

/**
 * Pagina impostazioni.
 */
function mb_gt_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s         = mb_gt_get();
	$name      = MB_GT_OPTION;
	$f         = function ( $key ) use ( $name ) {
		return esc_attr( $name . '[' . $key . ']' );
	};
	$languages = mb_gt_available_languages();
	$policies  = is_array( $s['policies'] ) ? $s['policies'] : array();
	$connected = ! empty( $s['widget_slug'] );
	$gtm       = mb_gt_gtm_id();
	$lang_now  = mb_gt_norm_lang( $s['lang'] ? $s['lang'] : $s['default_language'] );
	?>
	<div class="wrap mb-gt">
		<div class="mb-gt-header">
			<div>
				<h1>GetTerms <span>by mB</span></h1>
				<p class="mb-gt-header__sub">Banner cookie GetTerms, Google Tag Manager e policy &middot; v<?php echo esc_html( MB_GT_VERSION ); ?></p>
			</div>
			<div class="mb-gt-chips">
				<span class="mb-gt-chip <?php echo $s['enabled'] ? 'is-on' : 'is-off'; ?>"><?php echo $s['enabled'] ? 'Attivo sul sito' : 'Non attivo'; ?></span>
				<span class="mb-gt-chip <?php echo $connected ? 'is-ok' : 'is-warn'; ?>"><?php echo $connected ? 'GetTerms collegato' : 'GetTerms non collegato'; ?></span>
				<span class="mb-gt-chip <?php echo $gtm ? 'is-ok' : ''; ?>"><?php echo $gtm ? esc_html( $gtm ) : 'GTM non inserito'; ?></span>
				<?php if ( $lang_now ) : ?><span class="mb-gt-chip"><?php echo esc_html( strtoupper( $lang_now ) ); ?></span><?php endif; ?>
			</div>
		</div>

		<?php settings_errors( MB_GT_OPTION ); ?>

		<nav class="mb-gt-tabs" role="tablist">
			<button type="button" role="tab" data-tab="generale">Generale</button>
			<button type="button" role="tab" data-tab="gtm">Google Tag Manager</button>
			<button type="button" role="tab" data-tab="aspetto">Aspetto</button>
			<button type="button" role="tab" data-tab="policy">Policy</button>
			<button type="button" role="tab" data-tab="verifica">Verifica</button>
		</nav>

		<form method="post" action="options.php" class="mb-gt-form">
			<?php settings_fields( 'mb_gt_group' ); ?>
			<input type="hidden" name="<?php echo $f( '_form' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" value="1">

			<!-- GENERALE -->
			<section class="mb-gt-panel" data-panel="generale">
				<div class="mb-gt-card">
					<h2>Collegamento a GetTerms</h2>
					<?php
					$token_html  = '<div class="mb-gt-inline"><input type="text" id="mb-gt-token" name="' . $f( 'token' ) . '" value="' . esc_attr( $s['token'] ) . '" class="regular-text code" autocomplete="off" placeholder="es. o7upf">';
					$token_html .= '<button type="button" class="button" id="mb-gt-sync">Collega / Sincronizza</button></div><p id="mb-gt-sync-msg" class="mb-gt-msg" hidden></p>';
					if ( $connected ) {
						$token_html .= '<p class="mb-gt-meta">Banner <code>' . esc_html( $s['widget_slug'] ) . '</code>';
						if ( $s['last_sync'] ) {
							$token_html .= ' &middot; sincronizzato il ' . esc_html( wp_date( 'd/m/Y H:i', (int) $s['last_sync'] ) );
						}
						$token_html .= '</p>';
					}
					mb_gt_row( '<label for="mb-gt-token">Token GetTerms</label>', $token_html, 'In GetTerms: <em>Installation → WordPress plugin</em>. Importa banner, lingue e policy.' );
					?>
					<details class="mb-gt-details">
						<summary>Configurazione alternativa con embed code</summary>
						<?php mb_gt_row( '<label for="mb-gt-embed">Embed code</label>', '<textarea id="mb-gt-embed" name="' . $f( 'embed_code' ) . '" rows="3" class="large-text code" placeholder="&lt;!-- GetTerms CMP --&gt; ..."></textarea>', 'Solo se il token non funziona: il plugin ricava UUID e lingua dallo snippet. Le policy richiedono comunque il token.' ); ?>
					</details>
				</div>

				<div class="mb-gt-card">
					<h2>Banner</h2>
					<?php
					mb_gt_row( 'Attivazione', mb_gt_toggle( 'enabled', $s['enabled'], 'Stampa il banner (e GTM) nell\'head del sito', ! $connected ), 'Prima di attivare: disattiva l\'altro CMP e togli GTM da altri plugin o dal tema.' );

					if ( $languages ) {
						$sel = '<select name="' . $f( 'lang' ) . '">';
						foreach ( $languages as $code => $label ) {
							$sel .= '<option value="' . esc_attr( $code ) . '"' . selected( $lang_now, $code, false ) . '>' . esc_html( $label . ' (' . $code . ')' ) . '</option>';
						}
						$sel .= '</select>';
					} else {
						$sel = '<input type="text" name="' . $f( 'lang' ) . '" value="' . esc_attr( $s['lang'] ) . '" class="small-text code" placeholder="it">';
					}
					mb_gt_row(
						'Lingua',
						mb_gt_segmented( 'lang_mode', $s['lang_mode'], array( 'fixed' => 'Lingua fissa', 'site' => 'Segui la lingua della pagina' ) ) . '<div class="mb-gt-sub" data-show-if="lang_mode=fixed">' . $sel . '</div>' . mb_gt_toggle( 'auto_detect', $s['auto_detect'], 'Rileva la lingua del browser del visitatore' ),
						'"Segui la lingua della pagina" per siti multilingua (WPML, Polylang, TranslatePress).'
					);
					?>
				</div>
			</section>

			<!-- GTM -->
			<section class="mb-gt-panel" data-panel="gtm">
				<div class="mb-gt-card">
					<h2>Google Tag Manager</h2>
					<?php
					mb_gt_row( 'Inserimento', mb_gt_toggle( 'gtm_enabled', $s['gtm_enabled'], 'Stampa GTM subito dopo il banner' ), 'Lascia spento se GTM è già inserito dal tema o da un altro plugin (e verifica che sia caricato dopo il banner).' );
					mb_gt_row( '<label for="mb-gt-gtm">ID container</label>', '<input type="text" id="mb-gt-gtm" name="' . $f( 'gtm_id' ) . '" value="' . esc_attr( $s['gtm_id'] ) . '" class="regular-text code" placeholder="GTM-XXXXXXX">', '', 'mb-gt-dep-gtm' );
					mb_gt_row( 'Noscript', mb_gt_toggle( 'gtm_noscript', $s['gtm_noscript'], 'Aggiungi l\'iframe noscript dopo <body>' ), 'Richiede un tema con <code>wp_body_open</code>.', 'mb-gt-dep-gtm' );
					?>
				</div>
				<div class="mb-gt-card mb-gt-card--note">
					<h2>Configurazione del container</h2>
					<ul class="mb-gt-list">
						<li>Tag <strong>GetTerms CMP</strong> (Community Template Gallery) su <em>Consent Initialization – All Pages</em>, senza GetTerms ID.</li>
						<li>Google tag / GA4: <em>No additional consent required</em>, trigger <em>Initialization – All Pages</em>.</li>
						<li>Tag non Google (Clarity, Meta, Hotjar…): <em>Require additional consent</em>.</li>
					</ul>
				</div>
			</section>

			<!-- ASPETTO -->
			<section class="mb-gt-panel" data-panel="aspetto">
				<div class="mb-gt-card">
					<h2>Pulsante preferenze cookie</h2>
					<p class="mb-gt-intro">Il pulsante fisso che riapre il banner per cambiare le scelte.</p>
					<div class="mb-gt-split">
						<div class="mb-gt-split__main">
							<?php
							mb_gt_row( 'Stile', mb_gt_segmented( 'btn_style', $s['btn_style'], array( 'original' => 'Originale GetTerms', 'custom' => 'Personalizzato' ) ) );
							mb_gt_row( 'Forma', mb_gt_segmented( 'btn_shape', $s['btn_shape'], array( 'square' => 'Quadrata', 'round' => 'Tonda' ) ), '', 'mb-gt-dep-custom' );
							$colors = '';
							foreach ( array( 'btn_bg' => 'Sfondo', 'btn_bg_hover' => 'Al passaggio', 'btn_icon' => 'Icona' ) as $key => $label ) {
								$colors .= '<div class="mb-gt-color"><span>' . esc_html( $label ) . '</span><input type="text" class="mb-gt-colorpicker" data-key="' . esc_attr( $key ) . '" name="' . $f( $key ) . '" value="' . esc_attr( $s[ $key ] ) . '" data-default-color="' . esc_attr( mb_gt_defaults()[ $key ] ) . '"></div>';
							}
							mb_gt_row( 'Colori', '<div class="mb-gt-colors">' . $colors . '</div>', '', 'mb-gt-dep-custom' );
							mb_gt_row(
								'Dimensione',
								'<div class="mb-gt-inline"><label>L <input type="number" min="20" max="120" data-key="btn_width" name="' . $f( 'btn_width' ) . '" value="' . esc_attr( $s['btn_width'] ) . '" class="small-text"></label><span>&times;</span><label>A <input type="number" min="20" max="120" data-key="btn_height" name="' . $f( 'btn_height' ) . '" value="' . esc_attr( $s['btn_height'] ) . '" class="small-text"></label><span>px</span></div>',
								'Con la forma tonda viene usato il lato maggiore.',
								'mb-gt-dep-custom'
							);
							mb_gt_row(
								'Posizione',
								mb_gt_segmented( 'btn_side', $s['btn_side'], array( 'left' => 'Sinistra', 'right' => 'Destra' ) ) . '<div class="mb-gt-inline mb-gt-sub"><label>Dal bordo <input type="number" min="0" max="500" data-key="btn_offset_x" name="' . $f( 'btn_offset_x' ) . '" value="' . esc_attr( $s['btn_offset_x'] ) . '" class="small-text"> px</label><label>Dal basso <input type="number" min="0" max="1000" data-key="btn_offset_y" name="' . $f( 'btn_offset_y' ) . '" value="' . esc_attr( $s['btn_offset_y'] ) . '" class="small-text"> px</label></div>',
								'Per allinearlo a un altro pulsante fisso (es. accessibilità) usa le stesse misure e una distanza dal basso che lo metta sopra o sotto.',
								'mb-gt-dep-custom'
							);
							?>
						</div>
						<div class="mb-gt-split__side mb-gt-dep-custom">
							<div class="mb-gt-preview" aria-hidden="true">
								<span class="mb-gt-preview__label">Anteprima</span>
								<div class="mb-gt-preview__stage"><span class="mb-gt-preview__btn"></span></div>
							</div>
						</div>
					</div>
				</div>
				<div class="mb-gt-card">
					<h2>Pulsanti del banner</h2>
					<?php mb_gt_row( '<label for="mb-gt-btns-radius">Angoli</label>', '<div class="mb-gt-inline"><input type="number" min="0" max="100" class="small-text" id="mb-gt-btns-radius" name="' . $f( 'btns_radius' ) . '" value="' . esc_attr( $s['btns_radius'] ) . '" placeholder="—"><span>px</span></div>', 'Raggio dei pulsanti Accetta / Rifiuta / Preferenze / Salva, nel banner e nella finestra preferenze. Vuoto = come impostato in GetTerms; 0 = squadrati.' ); ?>
				</div>
			</section>

			<!-- POLICY -->
			<section class="mb-gt-panel" data-panel="policy">
				<div class="mb-gt-card">
					<h2>Link alle policy nel banner</h2>
					<p class="mb-gt-intro">Di default il banner apre le policy sul sito di GetTerms. Scegli le pagine del sito che contengono gli shortcode: i link del banner verranno indirizzati lì.</p>
					<?php
					foreach ( array(
						'policy_cookie_page'  => 'Cookie Policy',
						'policy_privacy_page' => 'Privacy Policy',
					) as $key => $label ) {
						$dropdown = wp_dropdown_pages(
							array(
								'name'              => $name . '[' . $key . ']',
								'id'                => 'mb-gt-' . $key,
								'selected'          => (int) $s[ $key ],
								'show_option_none'  => '— Pagina GetTerms (predefinito) —',
								'option_none_value' => '0',
								'echo'              => 0,
								'post_status'       => array( 'publish', 'private' ),
							)
						);
						mb_gt_row( '<label for="mb-gt-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>', $dropdown );
					}
					?>
				</div>
				<div class="mb-gt-card">
					<h2>Shortcode</h2>
					<?php if ( ! $s['token'] ) : ?>
						<p>Collega il token GetTerms per usare le policy.</p>
					<?php else : ?>
						<table class="mb-gt-codes">
							<tr><td>Privacy Policy</td><td><code class="mb-gt-copy">[mb_getterms_policy type="privacy"]</code></td></tr>
							<tr><td>Cookie Policy</td><td><code class="mb-gt-copy">[mb_getterms_policy type="cookie"]</code></td></tr>
							<tr><td>Termini e condizioni</td><td><code class="mb-gt-copy">[mb_getterms_policy type="terms"]</code></td></tr>
						</table>
						<p class="mb-gt-help">Senza <code>lang</code> usa la lingua del banner; per forzarla: <code>[mb_getterms_policy type="privacy" lang="en"]</code>. Clic sul codice per copiarlo.</p>
						<?php if ( $policies && $languages ) : ?>
							<details class="mb-gt-details">
								<summary>Shortcode compatibili con il plugin ufficiale GetTerms</summary>
								<table class="widefat striped mb-gt-table">
									<thead><tr><th>Policy</th><?php foreach ( $languages as $label ) : ?><th><?php echo esc_html( $label ); ?></th><?php endforeach; ?></tr></thead>
									<tbody>
										<?php foreach ( $policies as $policy ) : ?>
											<tr><td><?php echo esc_html( ucfirst( $policy ) ); ?></td>
												<?php foreach ( array_keys( (array) mb_gt_get( 'languages' ) ) as $lang_key ) : ?>
													<td><code>[getterms_<?php echo esc_html( $policy . '_' . $lang_key ); ?>]</code></td>
												<?php endforeach; ?>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</details>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</section>

			<div class="mb-gt-savebar">
				<?php submit_button( 'Salva impostazioni', 'primary', 'submit', false ); ?>
			</div>
		</form>

		<!-- VERIFICA (fuori dal form) -->
		<section class="mb-gt-panel" data-panel="verifica">
			<div class="mb-gt-card">
				<h2>Verifica installazione</h2>
				<p class="mb-gt-intro">Scarica la homepage e controlla: banner presente una volta e prima degli altri script, GTM presente una volta e dopo il banner, nessun altro CMP attivo.</p>
				<p><button type="button" class="button button-secondary" id="mb-gt-check">Verifica homepage</button></p>
				<div id="mb-gt-check-result"></div>
				<p class="mb-gt-help">Il controllo vede la pagina come la serve il server, cache comprese: dopo una modifica svuota la cache del sito.</p>
			</div>
		</section>
	</div>
	<?php
}
