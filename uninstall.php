<?php
/**
 * Pulizia alla disinstallazione.
 *
 * @package mb-getterms
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'mb_gt_settings' );
delete_site_transient( 'mb_gt_github_release' );
