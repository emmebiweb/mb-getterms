<?php
/**
 * Completa view_item_list di GTM4WP per le griglie prodotti dei temi (Impreza, XStore...).
 *
 * GTM4WP genera view_item_list leggendo gli span .gtm4wp_productdata che aggiunge
 * con l'hook woocommerce_after_shop_loop_item. Molti temi disegnano la griglia
 * con un proprio template e quell'hook non viene mai chiamato, quindi l'evento
 * non parte. Sulle pagine shop / categoria / tag / ricerca i prodotti mostrati
 * sono quelli della query principale: qui stampiamo gli stessi span (generati da
 * GTM4WP) in fondo alla pagina, e uno script li aggiunge solo per i prodotti che
 * GTM4WP non ha già marcato, così non ci sono doppioni.
 *
 * @package mb-getterms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pagina con una lista prodotti della query principale?
 *
 * @return bool
 */
function mb_gt_lists_is_product_archive() {
	if ( ! function_exists( 'is_shop' ) ) {
		return false;
	}
	if ( is_shop() || is_product_taxonomy() ) {
		return true;
	}
	return is_search() && 'product' === get_query_var( 'post_type' );
}

/**
 * Istanza ListTracking di GTM4WP 2.x (presa dal callback che registra).
 *
 * @return object|null
 */
function mb_gt_lists_tracker() {
	global $wp_filter;
	if ( empty( $wp_filter['woocommerce_after_shop_loop_item'] ) ) {
		return null;
	}
	foreach ( $wp_filter['woocommerce_after_shop_loop_item']->callbacks as $callbacks ) {
		foreach ( $callbacks as $cb ) {
			$fn = $cb['function'];
			if ( is_array( $fn ) && is_object( $fn[0] ) && method_exists( $fn[0], 'get_product_list_item_extra_tag' ) ) {
				return $fn[0];
			}
		}
	}
	return null;
}

/**
 * Span dati prodotto di GTM4WP per un prodotto della lista.
 *
 * @param WC_Product $product Prodotto.
 * @param int        $index   Posizione nella pagina (da 1).
 * @return string
 */
function mb_gt_lists_tag( $product, $index ) {
	$tracker = mb_gt_lists_tracker();
	if ( $tracker ) {
		$tag = $tracker->get_product_list_item_extra_tag( $product, '', $index, get_permalink( $product->get_id() ) );
		return is_string( $tag ) ? $tag : '';
	}
	// GTM4WP 1.x.
	if ( function_exists( 'gtm4wp_woocommerce_get_product_list_item_extra_tag' ) ) {
		$tag = gtm4wp_woocommerce_get_product_list_item_extra_tag( $product, '', $index, get_permalink( $product->get_id() ) );
		return is_string( $tag ) ? $tag : '';
	}
	return '';
}

/**
 * Stampa in fondo alla pagina gli span mancanti.
 */
function mb_gt_lists_print() {
	if ( ! mb_gt_get( 'gtm4wp_lists' ) || ! mb_gt_should_output() ) {
		return;
	}
	if ( ! mb_gt_lists_is_product_archive() ) {
		return;
	}
	global $wp_query;
	if ( empty( $wp_query->posts ) ) {
		return;
	}
	$tags  = '';
	$index = 0;
	foreach ( $wp_query->posts as $post ) {
		$product = wc_get_product( $post );
		if ( ! $product || ! $product->is_visible() ) {
			continue;
		}
		++$index;
		$tags .= mb_gt_lists_tag( $product, $index );
	}
	if ( '' === $tags ) {
		return;
	}
	// Gli span stanno in un <template> (inerte): lo script aggiunge al DOM solo quelli dei
	// prodotti che GTM4WP non ha già marcato. Gira prima di DOMContentLoaded, quando
	// GTM4WP raccoglie gli span e invia view_item_list.
	echo '<template id="mb-gt-gtm4wp-list">' . $tags . '</template>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup prodotto da GTM4WP con esc_attr().
	echo '<script id="mb-gt-gtm4wp-list-js" data-cfasync="false" data-no-optimize="1" data-no-defer="1">(function(){'
		. 'var t=document.getElementById("mb-gt-gtm4wp-list");if(!t||!t.content)return;'
		. 'function key(el){try{var d=JSON.parse(el.getAttribute("data-gtm4wp_product_data")||"{}");return String(d.internal_id||d.item_id||d.id||"");}catch(e){return "";}}'
		. 'var seen={},ex=document.querySelectorAll(".gtm4wp_productdata");for(var i=0;i<ex.length;i++){var k=key(ex[i]);if(k)seen[k]=1;}'
		. 'var box=document.createElement("div");box.id="mb-gt-gtm4wp-list-box";box.style.display="none";'
		. 'var sp=t.content.querySelectorAll(".gtm4wp_productdata"),n=0;for(var j=0;j<sp.length;j++){var k2=key(sp[j]);if(!k2||!seen[k2]){box.appendChild(document.importNode(sp[j],true));n++;}}'
		. 'if(n)t.parentNode.insertBefore(box,t);'
		. '})();</script>' . "\n";
}
add_action( 'wp_footer', 'mb_gt_lists_print', 5 );
