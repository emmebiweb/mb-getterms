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
		wp_enqueue_style( 'mb-gt-admin', MB_GT_URL . 'assets/admin.css', array(), MB_GT_VERSION );
		wp_enqueue_script( 'mb-gt-admin', MB_GT_URL . 'assets/admin.js', array(), MB_GT_VERSION, true );
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
 * Pagina impostazioni.
 */
function mb_gt_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s         = mb_gt_get();
	$name      = MB_GT_OPTION;
	$languages = mb_gt_available_languages();
	$policies  = is_array( $s['policies'] ) ? $s['policies'] : array();
	$connected = ! empty( $s['widget_slug'] );
	?>
	<div class="wrap mb-gt">
		<h1>mB GetTerms Consent <span class="mb-gt-version">v<?php echo esc_html( MB_GT_VERSION ); ?></span></h1>

		<div class="mb-gt-status <?php echo $s['enabled'] ? 'is-on' : 'is-off'; ?>">
			<?php if ( $s['enabled'] ) : ?>
				<strong>Attivo sul sito.</strong> Banner GetTerms<?php echo mb_gt_gtm_id() ? ' + GTM ' . esc_html( mb_gt_gtm_id() ) : ''; ?> stampati in testa all'head.
			<?php else : ?>
				<strong>Non attivo.</strong> Il plugin non stampa nulla sul sito finché non attivi l'opzione qui sotto.
			<?php endif; ?>
		</div>

		<?php settings_errors( MB_GT_OPTION ); ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'mb_gt_group' ); ?>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[_form]" value="1">

			<h2>1. Collegamento GetTerms</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="mb-gt-token">Token GetTerms</label></th>
					<td>
						<input type="text" id="mb-gt-token" name="<?php echo esc_attr( $name ); ?>[token]" value="<?php echo esc_attr( $s['token'] ); ?>" class="regular-text code" autocomplete="off">
						<button type="button" class="button button-secondary" id="mb-gt-sync">Collega / Sincronizza</button>
						<p class="description">Lo trovi in GetTerms &rarr; Installation &rarr; WordPress plugin. Importa banner, lingue e policy disponibili.</p>
						<p id="mb-gt-sync-msg" class="mb-gt-msg" hidden></p>
						<?php if ( $connected ) : ?>
							<p class="mb-gt-ok">
								Banner: <code><?php echo esc_html( $s['widget_slug'] ); ?></code>
								&middot; origine: <?php echo esc_html( 'token' === $s['source'] ? 'token' : 'embed code' ); ?>
								<?php if ( $s['last_sync'] ) : ?>
									&middot; ultima sincronizzazione: <?php echo esc_html( wp_date( 'd/m/Y H:i', (int) $s['last_sync'] ) ); ?>
								<?php endif; ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mb-gt-embed">In alternativa: embed code</label></th>
					<td>
						<textarea id="mb-gt-embed" name="<?php echo esc_attr( $name ); ?>[embed_code]" rows="3" class="large-text code" placeholder="&lt;!-- GetTerms CMP --&gt; ..."></textarea>
						<p class="description">Se il token non funziona, incolla qui lo snippet di GetTerms &rarr; Installation: il plugin ne ricava UUID e lingua. Le policy via shortcode richiedono comunque il token.</p>
					</td>
				</tr>
			</table>

			<h2>2. Banner</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Attiva sul sito</th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( $s['enabled'] ); ?> <?php disabled( ! $connected ); ?>> Stampa il banner GetTerms (e GTM, se attivo) nell'head</label>
						<p class="description">Prima di attivare: disattiva l'altro CMP (Complianz, Cookiebot, iubenda, plugin ufficiale GetTerms...) e verifica che GTM non sia già inserito altrove.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Lingua del banner</th>
					<td>
						<fieldset>
							<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[lang_mode]" value="fixed" <?php checked( 'fixed', $s['lang_mode'] ); ?>> Lingua fissa</label>
							<?php if ( $languages ) : ?>
								<select name="<?php echo esc_attr( $name ); ?>[lang]">
									<?php foreach ( $languages as $code => $label ) : ?>
										<option value="<?php echo esc_attr( $code ); ?>" <?php selected( mb_gt_norm_lang( $s['lang'] ? $s['lang'] : $s['default_language'] ), $code ); ?>><?php echo esc_html( $label . ' (' . $code . ')' ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php else : ?>
								<input type="text" name="<?php echo esc_attr( $name ); ?>[lang]" value="<?php echo esc_attr( $s['lang'] ); ?>" class="small-text code" placeholder="it">
							<?php endif; ?>
							<br>
							<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[lang_mode]" value="site" <?php checked( 'site', $s['lang_mode'] ); ?>> Segui la lingua della pagina (siti multilingua: WPML, Polylang, TranslatePress)</label>
							<br><br>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[auto_detect]" value="1" <?php checked( $s['auto_detect'] ); ?>> Rilevamento automatico della lingua del visitatore (<code>?auto=true</code>)</label>
						</fieldset>
					</td>
				</tr>
			</table>

			<h2>3. Google Tag Manager</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Inserisci GTM</th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[gtm_enabled]" value="1" <?php checked( $s['gtm_enabled'] ); ?>> Stampa GTM subito dopo il banner GetTerms</label>
						<p class="description">Lascia disattivato se GTM è già inserito da tema o da un altro plugin (in quel caso verifica che venga caricato <em>dopo</em> il banner).</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mb-gt-gtm">ID container</label></th>
					<td><input type="text" id="mb-gt-gtm" name="<?php echo esc_attr( $name ); ?>[gtm_id]" value="<?php echo esc_attr( $s['gtm_id'] ); ?>" class="regular-text code" placeholder="GTM-XXXXXXX"></td>
				</tr>
				<tr>
					<th scope="row">Noscript</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[gtm_noscript]" value="1" <?php checked( $s['gtm_noscript'] ); ?>> Aggiungi l'iframe noscript dopo &lt;body&gt; (richiede un tema con <code>wp_body_open</code>)</label></td>
				</tr>
			</table>

			<h2>4. Aspetto del banner</h2>
			<p class="description">Il pulsante preferenze è quello fisso che riapre il banner per cambiare le scelte.</p>
			<table class="form-table" role="presentation" id="mb-gt-btn">
				<tr>
					<th scope="row">Pulsante preferenze</th>
					<td>
						<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[btn_style]" value="original" <?php checked( 'original', $s['btn_style'] ); ?>> Originale GetTerms</label><br>
						<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[btn_style]" value="custom" <?php checked( 'custom', $s['btn_style'] ); ?>> Personalizzato</label>
					</td>
				</tr>
				<tr class="mb-gt-btn-custom">
					<th scope="row">Forma</th>
					<td>
						<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[btn_shape]" value="square" <?php checked( 'square', $s['btn_shape'] ); ?>> Quadrata</label>
						&nbsp;&nbsp;
						<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[btn_shape]" value="round" <?php checked( 'round', $s['btn_shape'] ); ?>> Tonda</label>
					</td>
				</tr>
				<tr class="mb-gt-btn-custom">
					<th scope="row">Colori</th>
					<td>
						<label>Sfondo <input type="color" name="<?php echo esc_attr( $name ); ?>[btn_bg]" value="<?php echo esc_attr( $s['btn_bg'] ); ?>"></label>
						&nbsp; <label>Sfondo al passaggio <input type="color" name="<?php echo esc_attr( $name ); ?>[btn_bg_hover]" value="<?php echo esc_attr( $s['btn_bg_hover'] ); ?>"></label>
						&nbsp; <label>Icona <input type="color" name="<?php echo esc_attr( $name ); ?>[btn_icon]" value="<?php echo esc_attr( $s['btn_icon'] ); ?>"></label>
					</td>
				</tr>
				<tr class="mb-gt-btn-custom">
					<th scope="row">Dimensione (px)</th>
					<td>
						<label>Larghezza <input type="number" min="20" max="120" class="small-text" name="<?php echo esc_attr( $name ); ?>[btn_width]" value="<?php echo esc_attr( $s['btn_width'] ); ?>"></label>
						&nbsp; <label>Altezza <input type="number" min="20" max="120" class="small-text" name="<?php echo esc_attr( $name ); ?>[btn_height]" value="<?php echo esc_attr( $s['btn_height'] ); ?>"></label>
						<p class="description">Con la forma tonda viene usato il lato maggiore per entrambe.</p>
					</td>
				</tr>
				<tr class="mb-gt-btn-custom">
					<th scope="row">Posizione</th>
					<td>
						<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[btn_side]" value="left" <?php checked( 'left', $s['btn_side'] ); ?>> Sinistra</label>
						&nbsp;&nbsp;
						<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[btn_side]" value="right" <?php checked( 'right', $s['btn_side'] ); ?>> Destra</label>
						<br><br>
						<label>Distanza dal bordo laterale <input type="number" min="0" max="500" class="small-text" name="<?php echo esc_attr( $name ); ?>[btn_offset_x]" value="<?php echo esc_attr( $s['btn_offset_x'] ); ?>"> px</label>
						&nbsp; <label>Distanza dal basso <input type="number" min="0" max="1000" class="small-text" name="<?php echo esc_attr( $name ); ?>[btn_offset_y]" value="<?php echo esc_attr( $s['btn_offset_y'] ); ?>"> px</label>
						<p class="description">Per allinearlo a un altro pulsante fisso (es. accessibilità OneTap) usa le stesse misure e una distanza dal basso che lo metta sopra o sotto.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mb-gt-btns-radius">Angoli dei pulsanti del banner</label></th>
					<td>
						<input type="number" min="0" max="100" class="small-text" id="mb-gt-btns-radius" name="<?php echo esc_attr( $name ); ?>[btns_radius]" value="<?php echo esc_attr( $s['btns_radius'] ); ?>" placeholder="—"> px
						<p class="description">Raggio degli angoli dei pulsanti "Accetta / Rifiuta / Preferenze / Salva" nel banner e nella finestra preferenze. Vuoto = come impostato in GetTerms; 0 = squadrati.</p>
					</td>
				</tr>
			</table>

			<?php submit_button( 'Salva impostazioni' ); ?>
		</form>

		<hr>
		<h2>Policy (shortcode)</h2>
		<?php if ( ! $s['token'] ) : ?>
			<p>Collega il token GetTerms per usare le policy.</p>
		<?php else : ?>
			<p>Shortcode generico: <code>[mb_getterms_policy type="privacy"]</code> &middot; <code>[mb_getterms_policy type="cookie"]</code> &middot; <code>[mb_getterms_policy type="terms" lang="en"]</code></p>
			<?php if ( $policies && $languages ) : ?>
				<p>Sono attivi anche gli shortcode del plugin ufficiale (compatibili, se il plugin ufficiale è disattivo):</p>
				<table class="widefat striped mb-gt-table">
					<thead><tr><th>Policy</th><?php foreach ( $languages as $label ) : ?><th><?php echo esc_html( $label ); ?></th><?php endforeach; ?></tr></thead>
					<tbody>
						<?php foreach ( $policies as $policy ) : ?>
							<tr>
								<td><?php echo esc_html( ucfirst( $policy ) ); ?></td>
								<?php foreach ( array_keys( mb_gt_get( 'languages' ) ) as $lang_key ) : ?>
									<td><code>[getterms_<?php echo esc_html( $policy . '_' . $lang_key ); ?>]</code></td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>

		<hr>
		<h2>Verifica installazione</h2>
		<p>Scarica la homepage e controlla: banner presente una sola volta e come primo script, GTM presente una sola volta, nessun altro CMP attivo.</p>
		<p><button type="button" class="button" id="mb-gt-check">Verifica homepage</button></p>
		<div id="mb-gt-check-result"></div>
		<p class="description">Il controllo vede la pagina come la serve il server (cache comprese). Dopo modifiche, svuota la cache del sito.</p>
	</div>
	<?php
}
