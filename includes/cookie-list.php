<?php
/**
 * Elenco dei cookie sotto la Cookie Policy.
 *
 * La Cookie Policy generata da GetTerms descrive solo le categorie: l'elenco dei
 * singoli cookie (nome, piattaforma, durata) sta nel pannello preferenze del banner.
 * Lo script del banner espone gli stessi dati in window.gtCookieWidgetConfig (cookie e
 * altro archivio del sito, già classificati in Cookie Management), con le intestazioni
 * tradotte nella lingua del banner: qui li mostriamo in una tabella sotto la policy,
 * più un pulsante che riapre il banner ([data-gt-cookie-widget-show], gestito da GetTerms).
 * Nessun dato copiato a mano: l'elenco si aggiorna da solo a ogni scansione.
 *
 * @package mb-getterms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contenitore dell'elenco cookie (riempito via JS).
 *
 * @param string $lang Codice lingua (vuoto = lingua del banner).
 * @return string
 */
function mb_gt_cookie_list_markup( $lang = '' ) {
	$lang = $lang ? mb_gt_norm_lang( $lang ) : mb_gt_resolve_lang();
	$GLOBALS['mb_gt_cookie_list_used'] = true;
	return '<div class="mb-gt-cookie-list" data-lang="' . esc_attr( $lang ) . '"></div>';
}

/**
 * Shortcode [mb_getterms_cookie_list] per mettere l'elenco dove si vuole.
 */
add_shortcode(
	'mb_getterms_cookie_list',
	function ( $atts ) {
		$atts = shortcode_atts( array( 'lang' => '' ), $atts, 'mb_getterms_cookie_list' );
		return mb_gt_cookie_list_markup( $atts['lang'] );
	}
);

/**
 * Script e stile: stampati in footer solo se nella pagina è stato generato un contenitore.
 */
function mb_gt_cookie_list_footer() {
	if ( empty( $GLOBALS['mb_gt_cookie_list_used'] ) || ! mb_gt_should_output() ) {
		return;
	}
	$css = '.mb-gt-cookie-list{margin-top:2em}'
		. '.mb-gt-cl__title{font-size:1.4em;margin:0 0 .5em}'
		. '.mb-gt-cl__cat{font-size:1.15em;margin:1.4em 0 .3em}'
		. '.mb-gt-cl__scroll{overflow-x:auto;margin:.5em 0 1.5em}'
		. '.mb-gt-cl__table{width:100%;border-collapse:collapse;font-size:.92em}'
		. '.mb-gt-cl__table th,.mb-gt-cl__table td{text-align:left;vertical-align:top;padding:.5em .6em;border-bottom:1px solid rgba(128,128,128,.25)}'
		. '.mb-gt-cl__table th{font-weight:600}'
		. '.mb-gt-cl__name{font-weight:600;word-break:break-all}'
		. '.mb-gt-cl__desc{display:block;font-weight:400;opacity:.85;word-break:normal}'
		. '.mb-gt-cl__btn{cursor:pointer;padding:.7em 1.4em;border:1px solid currentColor;border-radius:4px;background:transparent;color:inherit;font:inherit}'
		. '.mb-gt-cl__btn:hover{opacity:.8}';

	$js = <<<'JS'
(function(){
var boxes=document.querySelectorAll('.mb-gt-cookie-list');if(!boxes.length)return;
var T={
it:{h:'Elenco dei cookie',btn:'Gestisci le preferenze sui cookie',u:{second:['secondo','secondi'],minute:['minuto','minuti'],hour:['ora','ore'],day:['giorno','giorni'],week:['settimana','settimane'],month:['mese','mesi'],year:['anno','anni']},s:'Sessione'},
en:{h:'List of cookies',btn:'Manage cookie preferences'},
fr:{h:'Liste des cookies',btn:'Gérer les préférences de cookies',u:{second:['seconde','secondes'],minute:['minute','minutes'],hour:['heure','heures'],day:['jour','jours'],week:['semaine','semaines'],month:['mois','mois'],year:['an','ans']},s:'Session'},
de:{h:'Liste der Cookies',btn:'Cookie-Einstellungen verwalten',u:{second:['Sekunde','Sekunden'],minute:['Minute','Minuten'],hour:['Stunde','Stunden'],day:['Tag','Tage'],week:['Woche','Wochen'],month:['Monat','Monate'],year:['Jahr','Jahre']},s:'Sitzung'},
es:{h:'Lista de cookies',btn:'Gestionar las preferencias de cookies',u:{second:['segundo','segundos'],minute:['minuto','minutos'],hour:['hora','horas'],day:['día','días'],week:['semana','semanas'],month:['mes','meses'],year:['año','años']},s:'Sesión'}
};
function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
function ret(v,t){v=String(v||'');if(!t.u)return v;if(/^session$/i.test(v))return t.s;var m=v.match(/^(\d+)\s*(second|minute|hour|day|week|month|year)s?$/i);if(!m)return v;var n=+m[1],w=t.u[m[2].toLowerCase()];return n+' '+(n===1?w[0]:w[1]);}
function render(box,cfg){
var c=(cfg.config&&cfg.config.content)||{},ck=cfg.cookies||{},st=cfg.storage||{};
var l=(box.getAttribute('data-lang')||'en').slice(0,2).toLowerCase(),t=T[l]||T.en;
var cats=['essential','functional','analytics','marketing','unclassified'],h='';
cats.forEach(function(cat){
var cc=(ck[cat]&&ck[cat].cookies)||[],ss=(st[cat]&&st[cat].items)||[];if(!cc.length&&!ss.length)return;
h+='<h3 class="mb-gt-cl__cat">'+esc(c['cookie_'+cat+'_title']||(ck[cat]&&ck[cat].label)||cat)+'</h3>';
var d=c['cookie_'+cat+'_description']||(ck[cat]&&ck[cat].description);if(d)h+='<p>'+esc(d)+'</p>';
if(cc.length){h+='<div class="mb-gt-cl__scroll"><table class="mb-gt-cl__table"><thead><tr><th>'+esc(c.cookie_heading_name_description||'Name & Description')+'</th><th>'+esc(c.cookie_heading_platform||'Platform')+'</th><th>'+esc(c.cookie_heading_retention||'Retention')+'</th></tr></thead><tbody>';
cc.forEach(function(x){h+='<tr><td><span class="mb-gt-cl__name">'+esc(x.key)+'</span>'+(x.description?'<span class="mb-gt-cl__desc">'+esc(x.description)+'</span>':'')+'</td><td>'+esc(x.platform||x.data_controller||'')+'</td><td>'+esc(ret(x.retention_period,t))+'</td></tr>';});
h+='</tbody></table></div>';}
if(ss.length){h+='<div class="mb-gt-cl__scroll"><table class="mb-gt-cl__table"><caption style="text-align:left;font-weight:600;padding:.3em 0">'+esc(c.storage_heading||'Other site storage')+'</caption><thead><tr><th>'+esc(c.storage_heading_name||'Name')+'</th><th>'+esc(c.storage_heading_type||'Type')+'</th><th>'+esc(c.storage_heading_origin||'Origin')+'</th></tr></thead><tbody>';
ss.forEach(function(x){h+='<tr><td><span class="mb-gt-cl__name">'+esc(x.key)+'</span>'+(x.description?'<span class="mb-gt-cl__desc">'+esc(x.description)+'</span>':'')+'</td><td>'+esc(x.type)+'</td><td>'+esc(String(x.origin||x.source_domain||'').replace(/^https?:\/\//,''))+'</td></tr>';});
h+='</tbody></table></div>';}
});
if(h)h='<h2 class="mb-gt-cl__title">'+esc(t.h)+'</h2>'+h;
h+='<p><button type="button" class="mb-gt-cl__btn" data-gt-cookie-widget-show>'+esc(t.btn)+'</button></p>';
box.innerHTML=h;
}
var n=0;(function wait(){var cfg=window.gtCookieWidgetConfig;if(cfg&&(cfg.cookies||cfg.storage)){for(var i=0;i<boxes.length;i++)render(boxes[i],cfg);return;}if(++n<100)setTimeout(wait,150);})();
})();
JS;

	echo '<style id="mb-gt-cookie-list-css">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS statico.
	echo '<script id="mb-gt-cookie-list-js" data-cfasync="false" data-no-optimize="1">' . $js . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JS statico.
}
add_action( 'wp_footer', 'mb_gt_cookie_list_footer', 20 );
