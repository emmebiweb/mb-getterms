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
