<?php
/**
 * Output frontend: CMP GetTerms, Google Tag Manager, shortcode delle policy,
 * esclusioni per i plugin di ottimizzazione.
 *
 * @package mb-getterms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Il plugin deve stampare qualcosa in questa richiesta?
 * Esclude admin, feed, REST e le anteprime dei page builder (il blocker
 * può interferire con gli editor visuali).
 *
 * @return bool
 */
function mb_gt_should_output() {
	if ( ! mb_gt_get( 'enabled' ) ) {
		return false;
	}
	if ( is_admin() || wp_doing_ajax() || is_feed() || is_embed() ) {
		return false;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}
	if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
		return false;
	}
	// Editor visuali: WPBakery, Elementor, Beaver, Divi, Oxygen, Bricks, Brizy, Thrive.
	$builder_params = array( 'vc_editable', 'vc_action', 'elementor-preview', 'fl_builder', 'et_fb', 'ct_builder', 'bricks', 'brizy-edit-iframe', 'tve' );
	foreach ( $builder_params as $param ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET[ $param ] ) ) {
			return false;
		}
	}
	return (bool) apply_filters( 'mb_gt_should_output', true );
}

/**
 * Lingue disponibili (normalizzate) come array codice => nome.
 *
 * @return array
 */
function mb_gt_available_languages() {
	$langs = mb_gt_get( 'languages' );
	if ( is_string( $langs ) ) {
		$langs = json_decode( $langs, true );
	}
	$out = array();
	if ( is_array( $langs ) ) {
		foreach ( $langs as $code => $name ) {
			$out[ mb_gt_norm_lang( $code ) ] = is_string( $name ) ? $name : $code;
		}
	}
	return $out;
}

/**
 * Lingua del banner per la richiesta corrente.
 *
 * - fixed: lingua scelta nelle impostazioni (o default GetTerms);
 * - site: segue la lingua della pagina (WPML, Polylang, TranslatePress...)
 *   mappandola sulle lingue disponibili in GetTerms.
 *
 * @return string
 */
function mb_gt_resolve_lang() {
	$s         = mb_gt_get();
	$default   = mb_gt_norm_lang( $s['lang'] ? $s['lang'] : $s['default_language'] );
	$available = mb_gt_available_languages();

	if ( 'site' === $s['lang_mode'] ) {
		$locale     = mb_gt_norm_lang( determine_locale() ); // es. it-it, en-us, pt-br.
		$parts      = explode( '-', $locale );
		$candidates = array_unique( array( $locale, $parts[0] ) );
		foreach ( $candidates as $candidate ) {
			if ( empty( $available ) || isset( $available[ $candidate ] ) ) {
				// Senza elenco lingue (setup da embed code) usiamo la lingua breve.
				return empty( $available ) ? $parts[0] : $candidate;
			}
		}
		// Prova anche varianti regionali: "en" -> "en-us", "en-gb"...
		foreach ( array_keys( $available ) as $code ) {
			if ( 0 === strpos( $code, $parts[0] . '-' ) ) {
				return $code;
			}
		}
	}

	return (string) apply_filters( 'mb_gt_lang', $default );
}

/**
 * Attributi che dicono ai plugin di ottimizzazione / CDN di non toccare lo script
 * (LiteSpeed, WP Rocket, Autoptimize, Cloudflare Rocket Loader, WP Fastest Cache...).
 *
 * @return string
 */
function mb_gt_no_optimize_attrs() {
	return 'data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-noptimize="1" data-wpfc-render="false" nowprocket';
}

/**
 * Markup del CMP GetTerms: preload + blocker sincrono + widget async.
 *
 * @return string
 */
function mb_gt_cmp_markup() {
	$slug = mb_gt_get( 'widget_slug' );
	$lang = mb_gt_resolve_lang();
	if ( ! $slug || ! $lang ) {
		return '';
	}

	$suffix  = rawurlencode( $slug ) . '/' . rawurlencode( $lang ) . ( mb_gt_get( 'auto_detect' ) ? '?auto=true' : '' );
	$base    = 'https://gettermscmp.com/cookie-consent/';
	$blocker = esc_url( $base . 'blocker/' . $suffix );
	$widget  = esc_url( $base . 'widget/' . $suffix );
	$attrs   = mb_gt_no_optimize_attrs();

	// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript
	return "<!-- GetTerms CMP (mB GetTerms Consent " . MB_GT_VERSION . ") -->\n"
		. '<link rel="preload" href="' . $blocker . '" as="script">' . "\n"
		. '<script type="text/javascript" src="' . $blocker . '" ' . $attrs . '></script>' . "\n"
		. '<script async type="text/javascript" src="' . $widget . '" ' . $attrs . '></script>' . "\n"
		. "<!-- End GetTerms CMP -->\n";
	// phpcs:enable
}

/**
 * ID GTM valido o stringa vuota.
 *
 * @return string
 */
function mb_gt_gtm_id() {
	$id = strtoupper( trim( (string) mb_gt_get( 'gtm_id' ) ) );
	return ( mb_gt_get( 'gtm_enabled' ) && preg_match( '/^GTM-[A-Z0-9]+$/', $id ) ) ? $id : '';
}

/**
 * Snippet GTM per l'head.
 *
 * @return string
 */
function mb_gt_gtm_head_markup() {
	$id = mb_gt_gtm_id();
	if ( ! $id ) {
		return '';
	}
	return "<!-- Google Tag Manager -->\n"
		. '<script data-cfasync="false">(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({\'gtm.start\':'
		. 'new Date().getTime(),event:\'gtm.js\'});var f=d.getElementsByTagName(s)[0],'
		. 'j=d.createElement(s),dl=l!=\'dataLayer\'?\'&l=\'+l:\'\';j.async=true;j.src='
		. '\'https://www.googletagmanager.com/gtm.js?id=\'+i+dl;f.parentNode.insertBefore(j,f);'
		. '})(window,document,\'script\',\'dataLayer\',\'' . esc_js( $id ) . '\');</script>' . "\n"
		. "<!-- End Google Tag Manager -->\n";
}

/**
 * Stampa CMP + GTM in testa all'head con la priorità più bassa possibile
 * (PHP_INT_MIN), prima di ogni altro output agganciato a wp_head
 * (es. lo script inline di Gravity Forms). Restano sopra solo gli script
 * scritti dal tema in header.php prima di wp_head().
 */
function mb_gt_print_head() {
	if ( ! mb_gt_should_output() ) {
		return;
	}
	// Markup costruito con URL già escapati.
	echo mb_gt_cmp_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo mb_gt_gtm_head_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_head', 'mb_gt_print_head', PHP_INT_MIN );

/**
 * Noscript GTM subito dopo l'apertura del body (temi che supportano wp_body_open).
 */
function mb_gt_print_body_open() {
	if ( ! mb_gt_should_output() || ! mb_gt_get( 'gtm_noscript' ) ) {
		return;
	}
	$id = mb_gt_gtm_id();
	if ( ! $id ) {
		return;
	}
	echo '<!-- Google Tag Manager (noscript) --><noscript><iframe src="' . esc_url( 'https://www.googletagmanager.com/ns.html?id=' . $id ) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript><!-- End Google Tag Manager (noscript) -->' . "\n";
}
add_action( 'wp_body_open', 'mb_gt_print_body_open', 0 );

/*
 * ------------------------------------------------------------------
 * Pulsante "preferenze cookie" personalizzato.
 * Il widget GetTerms vive in uno Shadow DOM (<cookie-widget-wrapper>) e
 * ridisegna periodicamente il suo contenuto: il CSS del tema non lo
 * raggiunge. Iniettiamo uno <style> nello shadow root e lo reinseriamo
 * ogni volta che il widget lo rimuove. Il pulsante resta quello
 * originale (stessa funzione e accessibilità), cambia solo l'aspetto.
 * ------------------------------------------------------------------
 */

/**
 * CSS del pulsante personalizzato.
 *
 * @return string
 */
function mb_gt_reopen_button_css() {
	$s     = mb_gt_get();
	$round = 'round' === $s['btn_shape'];
	$w     = (int) $s['btn_width'];
	$h     = (int) $s['btn_height'];
	if ( $round ) {
		$w = max( $w, $h );
		$h = $w;
	}
	$icon_size = (int) round( min( $w, $h ) * 0.56 );
	$side      = 'right' === $s['btn_side'] ? 'right' : 'left';
	$other     = 'right' === $side ? 'left' : 'right';
	$x         = (int) $s['btn_offset_x'];
	$y         = (int) $s['btn_offset_y'];
	$bg        = sanitize_hex_color( $s['btn_bg'] ) ? $s['btn_bg'] : '#003a70';
	$bg_hover  = sanitize_hex_color( $s['btn_bg_hover'] ) ? $s['btn_bg_hover'] : $bg;
	$icon_col  = sanitize_hex_color( $s['btn_icon'] ) ? $s['btn_icon'] : '#ffffff';
	$mask      = "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5zm-3.5 7A1.5 1.5 0 1 1 7 10.5 1.5 1.5 0 0 1 8.5 9zm-1 6A1.5 1.5 0 1 1 6 16.5 1.5 1.5 0 0 1 7.5 15zm6 2a1.5 1.5 0 1 1-1.5 1.5 1.5 1.5 0 0 1 1.5-1.5zm3-4a1 1 0 1 1-1 1 1 1 0 0 1 1-1z'/%3E%3C/svg%3E\") center/contain no-repeat";

	return '.gt-cookie-reopen-btn{' . $side . ':' . $x . 'px!important;' . $other . ':auto!important;bottom:' . $y . 'px!important;top:auto!important;'
		. 'width:' . $w . 'px!important;height:' . $h . 'px!important;min-width:0!important;min-height:0!important;padding:0!important;margin:0!important;border:0!important;'
		. 'border-radius:' . ( $round ? '50%' : '0' ) . '!important;background:' . $bg . '!important;box-shadow:none!important;transform:none!important;'
		. 'display:flex!important;align-items:center!important;justify-content:center!important;cursor:pointer!important;transition:background-color .2s!important}'
		. '.gt-cookie-reopen-btn svg{display:none!important}'
		. '.gt-cookie-reopen-btn::before{content:"";display:block;width:' . $icon_size . 'px;height:' . $icon_size . 'px;background:' . $icon_col . ';-webkit-mask:' . $mask . ';mask:' . $mask . '}'
		. '.gt-cookie-reopen-btn:hover,.gt-cookie-reopen-btn:focus-visible{background:' . $bg_hover . '!important}'
		. '.gt-cookie-reopen-btn:focus-visible{outline:2px solid ' . $icon_col . '!important;outline-offset:-4px!important}';
}

/**
 * CSS complessivo da iniettare nel widget (vuoto = nessuna personalizzazione).
 *
 * @return string
 */
function mb_gt_widget_css() {
	$css = '';
	if ( 'custom' === mb_gt_get( 'btn_style' ) ) {
		$css .= mb_gt_reopen_button_css();
	}
	$radius = mb_gt_get( 'btns_radius' );
	if ( '' !== $radius && null !== $radius ) {
		$r    = max( 0, min( 100, (int) $radius ) ) . 'px';
		$css .= '.gt-cookie-widget .gt-cookie-widget__button,.gt-cookie-widget .gt-cookie-widget__button__pill-button,'
			. '.gt-cookie-dialog .gt-cookie-dialog__button,.gt-cookie-dialog .gt-cookie-dialog__button--pill{border-radius:' . $r . '!important}';
	}
	$offset = mb_gt_get( 'banner_offset_y' );
	if ( '' !== $offset && null !== $offset ) {
		$css .= '.gt-cookie-widget:not(.gt-cookie-widget--banner){bottom:' . max( 0, min( 400, (int) $offset ) ) . 'px!important}';
	}
	return (string) apply_filters( 'mb_gt_widget_css', $css );
}

/**
 * Mappa dei link policy da riscrivere nel banner: tipo => URL della pagina del sito.
 *
 * @return array
 */
function mb_gt_policy_link_map() {
	$map = array();
	foreach ( array(
		'cookie'  => 'policy_cookie_page',
		'privacy' => 'policy_privacy_page',
	) as $type => $key ) {
		$page_id = (int) mb_gt_get( $key );
		if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
			$map[ $type ] = get_permalink( $page_id );
		}
	}
	return (array) apply_filters( 'mb_gt_policy_link_map', $map );
}

/**
 * Stampa lo script che personalizza il widget GetTerms (Shadow DOM):
 * stile (pulsante preferenze, angoli dei pulsanti) e link alle policy.
 * Il widget ridisegna periodicamente il suo contenuto: un MutationObserver
 * riapplica tutto quando serve.
 */
function mb_gt_print_reopen_button() {
	if ( ! mb_gt_should_output() ) {
		return;
	}
	$css   = mb_gt_widget_css();
	$links = mb_gt_policy_link_map();
	if ( '' === $css && ! $links ) {
		return;
	}
	$config = wp_json_encode(
		array(
			'css'   => $css,
			'links' => (object) $links,
		)
	);
	// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript
	echo '<script id="mb-gt-widget">(function(c){var ID="mb-gt-reopen-style";'
		. 'var RE={cookie:/gettermscdn\\.com\\/view\\/[^\\/]+\\/cookies?\\//i,privacy:/gettermscdn\\.com\\/view\\/[^\\/]+\\/privacy\\//i};'
		. 'function fix(sr){if(c.css&&!sr.getElementById(ID)){var st=document.createElement("style");st.id=ID;st.textContent=c.css;sr.appendChild(st);}'
		. 'var as=sr.querySelectorAll("a[href*=\"gettermscdn.com/view/\"]");for(var i=0;i<as.length;i++){var a=as[i],h=a.getAttribute("href");'
		. 'for(var t in c.links){if(RE[t]&&RE[t].test(h)){a.setAttribute("href",c.links[t]);a.removeAttribute("target");a.removeAttribute("rel");}}}}'
		. 'function apply(host){var sr=host&&host.shadowRoot;if(!sr)return false;fix(sr);'
		. 'if(!host.__mbGtObs){host.__mbGtObs=new MutationObserver(function(){fix(sr);});host.__mbGtObs.observe(sr,{childList:true,subtree:true});}return true;}'
		. 'function find(){var h=document.querySelector("cookie-widget-wrapper");return h&&apply(h);}'
		. 'if(!find()){var mo=new MutationObserver(function(){if(find())mo.disconnect();});mo.observe(document.documentElement,{childList:true,subtree:true});setTimeout(function(){mo.disconnect();},30000);}'
		. '})(' . $config . ');</script>' . "\n";
	// phpcs:enable
}
add_action( 'wp_footer', 'mb_gt_print_reopen_button', 100 );

/*
 * ------------------------------------------------------------------
 * Esclusioni per i plugin di ottimizzazione (filtri innocui se il plugin non c'è).
 * ------------------------------------------------------------------
 */

/**
 * Aggiunge i pattern GetTerms a un array di esclusioni.
 *
 * @param mixed $list Lista esistente.
 * @return array
 */
function mb_gt_add_exclusions( $list ) {
	$list   = is_array( $list ) ? $list : array();
	$list[] = 'gettermscmp.com';
	$list[] = 'gettermscdn.com';
	return array_values( array_unique( $list ) );
}
// WP Rocket.
add_filter( 'rocket_delay_js_exclusions', 'mb_gt_add_exclusions' );
add_filter( 'rocket_exclude_defer_js', 'mb_gt_add_exclusions' );
add_filter( 'rocket_exclude_js', 'mb_gt_add_exclusions' );
add_filter( 'rocket_minify_excluded_external_js', 'mb_gt_add_exclusions' );
// SiteGround Speed Optimizer.
add_filter( 'sgo_javascript_combine_excluded_external_paths', 'mb_gt_add_exclusions' );
add_filter( 'sgo_javascript_combine_excluded_inline_content', 'mb_gt_add_exclusions' );
// Perfmatters.
add_filter( 'perfmatters_delay_js_exclusions', 'mb_gt_add_exclusions' );
// LiteSpeed Cache.
add_filter( 'litespeed_optm_js_defer_exc', 'mb_gt_add_exclusions' );
add_filter( 'litespeed_optimize_js_excludes', 'mb_gt_add_exclusions' );
// Autoptimize (stringa separata da virgole).
add_filter(
	'autoptimize_filter_js_exclude',
	function ( $exclude ) {
		return rtrim( (string) $exclude, ', ' ) . ', gettermscmp.com, gettermscdn.com';
	}
);

/*
 * ------------------------------------------------------------------
 * WP Consent API: il banner GetTerms comunica già con l'API se il plugin
 * WP Consent API è attivo. Qui ci dichiariamo integrati.
 * ------------------------------------------------------------------
 */
add_filter( 'wp_consent_api_registered_' . MB_GT_BASENAME, '__return_true' );

/*
 * ------------------------------------------------------------------
 * Policy GetTerms via shortcode.
 * ------------------------------------------------------------------
 */

/**
 * Accoda il runtime che riempie i contenitori .getterms-document-embed.
 */
function mb_gt_enqueue_document_embed() {
	wp_enqueue_script( 'mb-gt-embed', 'https://gettermscdn.com/dist/js/embed.js', array(), MB_GT_VERSION, true );
}

/**
 * Rete di sicurezza: se la policy arriva da contenuto in cache (es. cache dei widget di
 * Elementor, schede, template) lo shortcode non viene eseguito e embed.js non viene accodato.
 * In quel caso lo carichiamo in footer solo se nella pagina c'è un embed GetTerms.
 */
add_action(
	'wp_footer',
	function () {
		if ( is_admin() || wp_script_is( 'mb-gt-embed', 'done' ) || wp_script_is( 'mb-gt-embed', 'enqueued' ) ) {
			return;
		}
		echo '<script id="mb-gt-embed-loader" data-no-optimize="1" data-cfasync="false">(function(){if(!document.querySelector(".getterms-document-embed")){return;}var s=document.createElement("script");s.src="https://gettermscdn.com/dist/js/embed.js";s.async=true;document.body.appendChild(s);})();</script>' . "\n";
	},
	999
);

/**
 * Markup di una policy.
 *
 * @param string $policy privacy | cookie(s) | terms | tos | aup | ...
 * @param string $lang   Codice lingua (vuoto = lingua del banner).
 * @return string
 */
function mb_gt_policy_markup( $policy, $lang = '' ) {
	$token = mb_gt_get( 'token' );
	if ( ! $token ) {
		return current_user_can( 'manage_options' )
			? '<p><em>mB GetTerms: per mostrare le policy serve il token GetTerms (Impostazioni &rarr; GetTerms).</em></p>'
			: '';
	}
	$map    = array(
		'terms'   => 'tos',
		'cookies' => 'cookie',
	);
	$policy = strtolower( trim( $policy ) );
	$policy = isset( $map[ $policy ] ) ? $map[ $policy ] : $policy;
	$lang   = $lang ? mb_gt_norm_lang( $lang ) : mb_gt_resolve_lang();

	mb_gt_enqueue_document_embed();

	return '<div class="getterms-document-embed" data-getterms="' . esc_attr( $token ) . '" data-getterms-document="' . esc_attr( $policy ) . '" data-getterms-lang="' . esc_attr( $lang ) . '" data-getterms-mode="direct" data-getterms-env="https://gettermscdn.com"></div>';
}

/**
 * Shortcode [mb_getterms_policy type="privacy" lang="it"].
 */
add_shortcode(
	'mb_getterms_policy',
	function ( $atts ) {
		$atts = shortcode_atts(
			array(
				'type' => 'privacy',
				'lang' => '',
			),
			$atts,
			'mb_getterms_policy'
		);
		return mb_gt_policy_markup( $atts['type'], $atts['lang'] );
	}
);

/**
 * Shortcode compatibili con il plugin ufficiale ([getterms_privacy_it] ecc.),
 * così si può passare da un plugin all'altro senza toccare le pagine.
 * Registrati solo se il plugin ufficiale non è attivo.
 */
function mb_gt_register_compat_shortcodes() {
	if ( function_exists( 'getterms_generate_shortcodes' ) ) {
		return;
	}
	$policies = mb_gt_get( 'policies' );
	if ( is_string( $policies ) ) {
		$policies = json_decode( $policies, true );
	}
	$languages = mb_gt_get( 'languages' );
	if ( is_string( $languages ) ) {
		$languages = json_decode( $languages, true );
	}
	if ( ! is_array( $policies ) || ! is_array( $languages ) ) {
		return;
	}
	foreach ( $policies as $policy ) {
		foreach ( array_keys( $languages ) as $lang_key ) {
			$tag = 'getterms_' . $policy . '_' . $lang_key;
			if ( shortcode_exists( $tag ) ) {
				continue;
			}
			add_shortcode(
				$tag,
				function () use ( $policy, $lang_key ) {
					return mb_gt_policy_markup( $policy, $lang_key );
				}
			);
		}
	}
}
add_action( 'init', 'mb_gt_register_compat_shortcodes', 20 );
