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

	/* ---------- Pulsante preferenze: mostra le opzioni solo se "personalizzato" ---------- */
	var btnTable = document.getElementById('mb-gt-btn');
	if (btnTable) {
		var toggleRows = function () {
			var custom = btnTable.querySelector('input[name$="[btn_style]"][value="custom"]');
			btnTable.querySelectorAll('.mb-gt-btn-custom').forEach(function (row) {
				row.style.display = custom && custom.checked ? '' : 'none';
			});
		};
		btnTable.addEventListener('change', toggleRows);
		toggleRows();
	}
})();
