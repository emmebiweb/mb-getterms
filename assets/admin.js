/* mB GetTerms Consent - admin */
(function () {
	'use strict';

	var cfg = window.mbGtAdmin || {};

	function post(action, data) {
		var body = new URLSearchParams();
		body.append('action', action);
		body.append('nonce', cfg.nonce);
		Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
		return fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json(); });
	}

	function showMsg(el, text, type) {
		el.hidden = false;
		el.className = 'mb-gt-msg is-' + (type || 'info');
		el.textContent = text;
	}

	/* ---------- Sincronizzazione token ---------- */
	var syncBtn = document.getElementById('mb-gt-sync');
	var tokenEl = document.getElementById('mb-gt-token');
	var msgEl = document.getElementById('mb-gt-sync-msg');

	function serverSync(token) {
		return post('mb_gt_sync_server', { token: token }).then(function (res) {
			if (res && res.success) {
				showMsg(msgEl, res.data + ' Ricarico…', 'ok');
				window.location.reload();
			} else {
				showMsg(msgEl, (res && res.data) || 'Sincronizzazione non riuscita.', 'error');
				syncBtn.disabled = false;
			}
		});
	}

	if (syncBtn && tokenEl) {
		syncBtn.addEventListener('click', function () {
			var token = (tokenEl.value || '').trim();
			if (!token) {
				showMsg(msgEl, 'Inserisci il token GetTerms.', 'error');
				return;
			}
			syncBtn.disabled = true;
			showMsg(msgEl, 'Collegamento a GetTerms…', 'info');

			// Stessa chiamata del plugin ufficiale, fatta dal browser.
			var host = cfg.host || window.location.hostname;
			var url = 'https://app.getterms.io/api/wp-plugin/v1/' + encodeURIComponent(token) +
				'?dm=' + encodeURIComponent(window.btoa('https://' + host));

			fetch(url, { headers: { 'X-Site-Domain': host } })
				.then(function (r) {
					if (!r.ok) { throw new Error('HTTP ' + r.status); }
					return r.text();
				})
				.then(function (text) {
					return post('mb_gt_save_sync', { payload: text });
				})
				.then(function (res) {
					if (res && res.success) {
						showMsg(msgEl, res.data + ' Ricarico…', 'ok');
						window.location.reload();
					} else {
						throw new Error((res && res.data) || 'risposta non valida');
					}
				})
				.catch(function () {
					// Fallback: chiamata lato server.
					showMsg(msgEl, 'Tentativo lato server…', 'info');
					serverSync(token).catch(function () {
						showMsg(msgEl, 'Sincronizzazione non riuscita. Usa l\'embed code.', 'error');
						syncBtn.disabled = false;
					});
				});
		});
	}

	/* ---------- Verifica homepage ---------- */
	var checkBtn = document.getElementById('mb-gt-check');
	var out = document.getElementById('mb-gt-check-result');

	if (checkBtn && out) {
		checkBtn.addEventListener('click', function () {
			checkBtn.disabled = true;
			out.innerHTML = '<p>Verifica in corso…</p>';
			post('mb_gt_check', {})
				.then(function (res) {
					out.innerHTML = '';
					if (!res || !res.success) {
						out.innerHTML = '';
						var p = document.createElement('p');
						p.className = 'mb-gt-msg is-error';
						p.textContent = (res && res.data) || 'Verifica non riuscita.';
						out.appendChild(p);
						return;
					}
					var ul = document.createElement('ul');
					ul.className = 'mb-gt-checks';
					res.data.forEach(function (c) {
						var li = document.createElement('li');
						li.className = 'is-' + c.status;
						li.textContent = c.text;
						ul.appendChild(li);
					});
					out.appendChild(ul);
				})
				.catch(function () {
					out.innerHTML = '<p class="mb-gt-msg is-error">Errore durante la verifica.</p>';
				})
				.then(function () { checkBtn.disabled = false; });
		});
	}

	/* ---------- Schede ---------- */
	var tabs = document.querySelectorAll('.mb-gt-tabs [data-tab]');
	var panels = document.querySelectorAll('.mb-gt-panel');
	var saveBar = document.querySelector('.mb-gt-savebar');
	function showTab(name) {
		var found = false;
		tabs.forEach(function (t) { var on = t.getAttribute('data-tab') === name; t.classList.toggle('is-active', on); t.setAttribute('aria-selected', on ? 'true' : 'false'); if (on) { found = true; } });
		if (!found) { name = 'generale'; return showTab(name); }
		panels.forEach(function (p) { p.hidden = p.getAttribute('data-panel') !== name; });
		if (saveBar) { saveBar.hidden = name === 'verifica'; }
		try { localStorage.setItem('mbGtTab', name); } catch (e) {}
	}
	tabs.forEach(function (t) { t.addEventListener('click', function () { showTab(t.getAttribute('data-tab')); }); });
	if (tabs.length) {
		var start = 'generale';
		try { start = localStorage.getItem('mbGtTab') || 'generale'; } catch (e) {}
		showTab(start);
	}

	/* ---------- Campi dipendenti ---------- */
	var form = document.querySelector('.mb-gt-form');
	function val(field) {
		var el = form && form.querySelector('[name$="[' + field + ']"]:checked');
		return el ? el.value : '';
	}
	function checked(field) {
		var el = form && form.querySelector('input[type=checkbox][name$="[' + field + ']"]');
		return !!(el && el.checked);
	}
	function deps() {
		if (!form) { return; }
		var custom = val('btn_style') === 'custom';
		document.querySelectorAll('.mb-gt-dep-custom').forEach(function (el) { el.hidden = !custom; });
		var gtm = checked('gtm_enabled');
		document.querySelectorAll('.mb-gt-dep-gtm').forEach(function (el) { el.classList.toggle('is-dim', !gtm); });
		document.querySelectorAll('[data-show-if]').forEach(function (el) {
			var p = el.getAttribute('data-show-if').split('=');
			el.hidden = val(p[0]) !== p[1];
		});
		preview();
	}

	/* ---------- Anteprima pulsante ---------- */
	function num(key, def) {
		var el = form && form.querySelector('[data-key="' + key + '"]');
		var n = el ? parseInt(el.value, 10) : NaN;
		return isNaN(n) ? def : n;
	}
	function color(key) {
		var el = form && form.querySelector('.mb-gt-colorpicker[data-key="' + key + '"]');
		return el ? el.value : '';
	}
	var hovering = false;
	function preview() {
		var btn = document.querySelector('.mb-gt-preview__btn');
		if (!btn) { return; }
		var w = Math.max(20, Math.min(120, num('btn_width', 40)));
		var h = Math.max(20, Math.min(120, num('btn_height', 40)));
		var round = val('btn_shape') === 'round';
		if (round) { w = h = Math.max(w, h); }
		var stage = btn.parentNode;
		var right = val('btn_side') === 'right';
		btn.style.width = w + 'px';
		btn.style.height = h + 'px';
		btn.style.borderRadius = round ? '50%' : '0';
		btn.style.background = hovering ? (color('btn_bg_hover') || color('btn_bg')) : color('btn_bg');
		btn.style.setProperty('--mb-icon', color('btn_icon') || '#fff');
		btn.style.setProperty('--mb-icon-size', Math.round(Math.min(w, h) * 0.56) + 'px');
		btn.style.left = right ? 'auto' : Math.min(num('btn_offset_x', 0), 40) + 'px';
		btn.style.right = right ? Math.min(num('btn_offset_x', 0), 40) + 'px' : 'auto';
		btn.style.bottom = Math.min(num('btn_offset_y', 20), 40) + 'px';
		stage.classList.toggle('is-right', right);
	}
	var pbtn = document.querySelector('.mb-gt-preview__btn');
	if (pbtn) {
		pbtn.addEventListener('mouseenter', function () { hovering = true; preview(); });
		pbtn.addEventListener('mouseleave', function () { hovering = false; preview(); });
	}

	/* ---------- Selettore colori di WordPress ---------- */
	if (window.jQuery && jQuery.fn.wpColorPicker) {
		jQuery('.mb-gt-colorpicker').wpColorPicker({
			change: function (e, ui) { e.target.value = ui.color.toString(); preview(); },
			clear: function () { setTimeout(preview, 0); }
		});
	}

	if (form) {
		form.addEventListener('change', deps);
		form.addEventListener('input', deps);
		deps();
	}

	/* ---------- Copia shortcode ---------- */
	document.querySelectorAll('.mb-gt-copy').forEach(function (code) {
		code.setAttribute('title', 'Clic per copiare');
		code.addEventListener('click', function () {
			var text = code.textContent;
			var done = function () { code.classList.add('is-copied'); setTimeout(function () { code.classList.remove('is-copied'); }, 1200); };
			if (navigator.clipboard) { navigator.clipboard.writeText(text).then(done); } else {
				var r = document.createRange(); r.selectNodeContents(code); var sel = getSelection(); sel.removeAllRanges(); sel.addRange(r); document.execCommand('copy'); done();
			}
		});
	});
})();
