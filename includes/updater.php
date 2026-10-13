<?php
/**
 * Aggiornamenti automatici da GitHub Releases.
 *
 * Usa l'header "Update URI" (WordPress 5.8+): WordPress chiede gli aggiornamenti
 * al filtro update_plugins_github.com invece che a wordpress.org.
 * Ogni release GitHub deve avere come asset il file "mb-getterms.zip"
 * (lo crea in automatico la GitHub Action inclusa nel repository).
 *
 * @package mb-getterms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MB_GT_RELEASE_TRANSIENT', 'mb_gt_github_release' );

/**
 * Il repository è configurato?
 *
 * @return bool
 */
function mb_gt_repo_configured() {
	return (bool) preg_match( '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', MB_GT_GITHUB_REPO ) && false === strpos( MB_GT_GITHUB_REPO, 'CHANGE-ME' );
}

/**
 * Ultima release pubblicata su GitHub (cache 6 ore, 1 ora in caso di errore).
 *
 * @param bool $force Ignora la cache.
 * @return array|null { version, package, url, body, published }
 */
function mb_gt_latest_release( $force = false ) {
	if ( ! mb_gt_repo_configured() ) {
		return null;
	}

	$cached = $force ? false : get_site_transient( MB_GT_RELEASE_TRANSIENT );
	if ( is_array( $cached ) ) {
		return empty( $cached['version'] ) ? null : $cached;
	}

	$headers = array(
		'Accept'     => 'application/vnd.github+json',
		'User-Agent' => 'mb-getterms/' . MB_GT_VERSION,
	);
	if ( defined( 'MB_GT_GITHUB_TOKEN' ) && MB_GT_GITHUB_TOKEN ) {
		$headers['Authorization'] = 'Bearer ' . MB_GT_GITHUB_TOKEN;
	}

	$response = wp_remote_get(
		'https://api.github.com/repos/' . MB_GT_GITHUB_REPO . '/releases/latest',
		array(
			'timeout' => 10,
			'headers' => $headers,
		)
	);

	$release = array();
	if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( is_array( $data ) && ! empty( $data['tag_name'] ) ) {
			$package = '';
			if ( ! empty( $data['assets'] ) && is_array( $data['assets'] ) ) {
				foreach ( $data['assets'] as $asset ) {
					if ( isset( $asset['name'], $asset['browser_download_url'] ) && MB_GT_SLUG . '.zip' === $asset['name'] ) {
						$package = $asset['browser_download_url'];
						break;
					}
				}
			}
			if ( $package ) {
				$release = array(
					'version'   => ltrim( $data['tag_name'], 'vV' ),
					'package'   => $package,
					'url'       => isset( $data['html_url'] ) ? $data['html_url'] : 'https://github.com/' . MB_GT_GITHUB_REPO,
					'body'      => isset( $data['body'] ) ? (string) $data['body'] : '',
					'published' => isset( $data['published_at'] ) ? $data['published_at'] : '',
				);
			}
		}
	}

	set_site_transient( MB_GT_RELEASE_TRANSIENT, $release, $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	return $release ? $release : null;
}

/**
 * Risponde a WordPress quando controlla gli aggiornamenti dei plugin con Update URI github.com.
 */
add_filter(
	'update_plugins_github.com',
	function ( $update, $plugin_data, $plugin_file ) {
		if ( MB_GT_BASENAME !== $plugin_file ) {
			return $update;
		}
		$release = mb_gt_latest_release();
		if ( ! $release ) {
			return $update;
		}
		return array(
			'id'           => 'https://github.com/' . MB_GT_GITHUB_REPO,
			'slug'         => MB_GT_SLUG,
			'plugin'       => MB_GT_BASENAME,
			'version'      => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'requires_php' => '7.4',
		);
	},
	10,
	3
);

/**
 * Popup "Visualizza dettagli" nella pagina Plugin.
 */
add_filter(
	'plugins_api',
	function ( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || MB_GT_SLUG !== $args->slug ) {
			return $result;
		}
		$release = mb_gt_latest_release();
		if ( ! $release ) {
			return $result;
		}
		return (object) array(
			'name'          => 'mB GetTerms Consent',
			'slug'          => MB_GT_SLUG,
			'version'       => $release['version'],
			'author'        => 'mB',
			'homepage'      => 'https://github.com/' . MB_GT_GITHUB_REPO,
			'requires_php'  => '7.4',
			'requires'      => '5.8',
			'last_updated'  => $release['published'],
			'download_link' => $release['package'],
			'sections'      => array(
				'changelog' => '<pre>' . esc_html( $release['body'] ) . '</pre>',
			),
		);
	},
	10,
	3
);

/**
 * "Controlla di nuovo" in Bacheca > Aggiornamenti svuota anche la nostra cache.
 */
add_action(
	'load-update-core.php',
	function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['force-check'] ) ) {
			delete_site_transient( MB_GT_RELEASE_TRANSIENT );
		}
	}
);

/**
 * Rete di sicurezza: se lo zip non contiene la cartella "mb-getterms",
 * rinomina la cartella estratta per non duplicare il plugin.
 */
add_filter(
	'upgrader_source_selection',
	function ( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;
		if ( empty( $hook_extra['plugin'] ) || MB_GT_BASENAME !== $hook_extra['plugin'] || ! $wp_filesystem ) {
			return $source;
		}
		$expected = trailingslashit( $remote_source ) . MB_GT_SLUG . '/';
		if ( untrailingslashit( $source ) === untrailingslashit( $expected ) ) {
			return $source;
		}
		if ( $wp_filesystem->move( $source, $expected, true ) ) {
			return $expected;
		}
		return new WP_Error( 'mb_gt_rename_failed', 'mB GetTerms: impossibile rinominare la cartella del pacchetto di aggiornamento.' );
	},
	10,
	4
);

/*
 * ------------------------------------------------------------------
 * Resta attivo dopo l'aggiornamento.
 * Durante l'aggiornamento WordPress disattiva il plugin prima di sostituire
 * i file e non sempre lo riattiva (dipende da come si avvia l'aggiornamento).
 * Per un CMP restare spento significa sito senza banner e senza GTM: qui
 * ricordiamo lo stato prima dell'installazione e, se serve, lo ripristiniamo
 * alla fine. Aggiorniamo solo l'opzione active_plugins (senza includere di
 * nuovo il file, che in questa richiesta è già caricato nella versione vecchia).
 * ------------------------------------------------------------------
 */
add_filter(
	'upgrader_pre_install',
	function ( $response, $hook_extra ) {
		if ( ! empty( $hook_extra['plugin'] ) && MB_GT_BASENAME === $hook_extra['plugin'] ) {
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			if ( is_plugin_active( MB_GT_BASENAME ) && ! is_plugin_active_for_network( MB_GT_BASENAME ) ) {
				$GLOBALS['mb_gt_was_active'] = true;
			}
		}
		return $response;
	},
	5, // Prima della disattivazione di WordPress (priorità 10).
	2
);

add_action(
	'upgrader_process_complete',
	function ( $upgrader, $data ) {
		if ( empty( $GLOBALS['mb_gt_was_active'] ) || ( isset( $data['type'] ) && 'plugin' !== $data['type'] ) ) {
			return;
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( is_plugin_active( MB_GT_BASENAME ) || ! file_exists( WP_PLUGIN_DIR . '/' . MB_GT_BASENAME ) ) {
			return;
		}
		$active   = (array) get_option( 'active_plugins', array() );
		$active[] = MB_GT_BASENAME;
		update_option( 'active_plugins', array_values( array_unique( $active ) ) );
		unset( $GLOBALS['mb_gt_was_active'] );
	},
	10,
	2
);
