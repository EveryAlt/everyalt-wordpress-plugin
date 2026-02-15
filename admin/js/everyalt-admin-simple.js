/**
 * EveryAlt admin - simple WordPress UI (no Vue/React).
 */
(function() {
	'use strict';

	var restUrl = typeof everyaltAdmin !== 'undefined' ? everyaltAdmin.restUrl : '';
	var restNonce = typeof everyaltAdmin !== 'undefined' ? everyaltAdmin.restNonce : '';
	var ajaxUrl = typeof everyaltAdmin !== 'undefined' ? everyaltAdmin.ajaxUrl : '';
	var validateKeyNonce = typeof everyaltAdmin !== 'undefined' ? everyaltAdmin.validateKeyNonce : '';

	function request(method, path, body) {
		var url = restUrl.replace(/\/$/, '') + path;
		var opts = {
			method: method,
			headers: {
				'X-WP-Nonce': restNonce,
				'Content-Type': 'application/json'
			}
		};
		if (body && method !== 'GET') {
			opts.body = JSON.stringify(body);
		}
		return fetch(url, opts).then(function(r) {
			if (!r.ok) throw new Error(r.statusText);
			return r.json();
		});
	}

	// Validate API key (Settings tab)
	var validateKeyBtn = document.getElementById('everyalt-validate-key');
	if (validateKeyBtn) {
		var resultEl = document.getElementById('everyalt-validate-result');
		validateKeyBtn.addEventListener('click', function() {
			var keyInput = document.getElementById('every_alt_openai_key');
			var key = keyInput ? keyInput.value.trim() : '';
			if (!resultEl) return;
			resultEl.style.display = 'none';
			resultEl.className = 'everyalt-validate-result';
			validateKeyBtn.disabled = true;
			var formData = new FormData();
			formData.append('action', 'everyalt_validate_key');
			formData.append('nonce', validateKeyNonce);
			formData.append('key', key);
			fetch(ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			})
				.then(function(r) { return r.json(); })
				.then(function(data) {
					validateKeyBtn.disabled = false;
					resultEl.style.display = 'block';
					var msg = (data.data && data.data.message) ? data.data.message : (data.success ? '' : 'Error');
					resultEl.className = 'everyalt-validate-result notice ' + (data.success ? 'notice-success' : 'notice-error');
					resultEl.textContent = '';
					var p = document.createElement('p');
					p.textContent = msg;
					resultEl.appendChild(p);
				})
				.catch(function() {
					validateKeyBtn.disabled = false;
					resultEl.style.display = 'block';
					resultEl.className = 'everyalt-validate-result notice notice-error';
					resultEl.innerHTML = '<p>Request failed.</p>';
				});
		});
	}

	// Bulk: Select all / Select none
	var selectAllBtn = document.getElementById('everyalt-bulk-select-all');
	var selectNoneBtn = document.getElementById('everyalt-bulk-select-none');
	if (selectAllBtn) {
		selectAllBtn.addEventListener('click', function() {
			document.querySelectorAll('.everyalt-bulk-checkbox').forEach(function(cb) { cb.checked = true; });
		});
	}
	if (selectNoneBtn) {
		selectNoneBtn.addEventListener('click', function() {
			document.querySelectorAll('.everyalt-bulk-checkbox').forEach(function(cb) { cb.checked = false; });
		});
	}

	// Bulk run: generate for selected, progress bar + per-item log, no reload
	var bulkBtn = document.getElementById('everyalt-bulk-run');
	if (bulkBtn) {
		var progressEl = document.getElementById('everyalt-bulk-progress');
		var progressTextEl = document.getElementById('everyalt-bulk-progress-text');
		var progressFill = document.getElementById('everyalt-bulk-progress-fill');
		var progressBar = document.querySelector('.everyalt-bulk-progress-bar');
		var progressLog = document.getElementById('everyalt-bulk-progress-log');
		bulkBtn.addEventListener('click', function() {
			var checkboxes = document.querySelectorAll('.everyalt-bulk-checkbox:checked');
			var ids = Array.prototype.map.call(checkboxes, function(cb) { return parseInt(cb.value, 10); });
			if (!ids.length) return;
			progressEl.classList.remove('hidden');
			bulkBtn.disabled = true;
			if (selectAllBtn) selectAllBtn.disabled = true;
			if (selectNoneBtn) selectNoneBtn.disabled = true;
			document.querySelectorAll('.everyalt-bulk-checkbox').forEach(function(cb) { cb.disabled = true; });
			var total = ids.length;
			var done = 0;
			if (progressLog) progressLog.innerHTML = '';
			function updateProgress() {
				var pct = total ? Math.round((done / total) * 100) : 0;
				if (progressTextEl) progressTextEl.textContent = done + ' / ' + total;
				if (progressFill) progressFill.style.width = pct + '%';
				if (progressBar) progressBar.setAttribute('aria-valuenow', pct);
			}
			var index = 0;
			function next() {
				if (index >= ids.length) {
					progressEl.classList.add('hidden');
					bulkBtn.disabled = false;
					if (selectAllBtn) selectAllBtn.disabled = false;
					if (selectNoneBtn) selectNoneBtn.disabled = false;
					document.querySelectorAll('.everyalt-bulk-checkbox').forEach(function(cb) { cb.disabled = false; });
					return;
				}
				var id = ids[index];
				var itemEl = document.querySelector('.everyalt-bulk-item[data-media-id="' + id + '"]');
				var statusEl = itemEl ? itemEl.querySelector('.everyalt-bulk-item-status') : null;
				request('POST', '/everyalt-api/v1/bulk_generate_alt', { media_id: id })
					.then(function(data) {
						done++;
						updateProgress();
						var success = data && data.success;
						var msg = success ? (data.alt_text || '') : (data && data.message) ? data.message : '';
						if (statusEl) {
							statusEl.textContent = success ? '\u2713 ' + (data.alt_text || '') : '\u2717 ' + (data.message || 'Error');
							statusEl.className = 'everyalt-bulk-item-status ' + (success ? 'success' : 'error');
						}
						if (progressLog) {
							var li = document.createElement('li');
							li.className = success ? 'success' : 'error';
							li.textContent = '#' + id + ': ' + (success ? (data.alt_text || '') : (data.message || 'Error'));
							progressLog.appendChild(li);
						}
						if (success && itemEl) {
							var label = itemEl.querySelector('label');
							if (label) label.style.opacity = '0.5';
						}
						index++;
						next();
					})
					.catch(function(err) {
						done++;
						updateProgress();
						var errMsg = err && err.message ? err.message : 'Request failed';
						if (statusEl) {
							statusEl.textContent = '\u2717 ' + errMsg;
							statusEl.className = 'everyalt-bulk-item-status error';
						}
						if (progressLog) {
							var li = document.createElement('li');
							li.className = 'error';
							li.textContent = '#' + id + ': ' + errMsg;
							progressLog.appendChild(li);
						}
						index++;
						next();
					});
			}
			updateProgress();
			next();
		});
	}

	// Review Alt Text: Save edited alt (AJAX)
	document.querySelectorAll('.everyalt-review-save').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var mediaId = parseInt(btn.getAttribute('data-media-id'), 10);
			var item = btn.closest('.everyalt-review-item');
			var textarea = item ? item.querySelector('.everyalt-review-alt-field') : null;
			var statusEl = item ? item.querySelector('.everyalt-review-status') : null;
			var altText = textarea ? textarea.value : '';
			btn.disabled = true;
			request('POST', '/everyalt-api/v1/save_alt', {
				media_id: mediaId,
				log_id: 0,
				alt_text: altText
			})
				.then(function() {
					btn.disabled = false;
					if (statusEl) {
						statusEl.textContent = 'Saved!';
						statusEl.className = 'everyalt-review-status success';
						setTimeout(function() { statusEl.textContent = ''; statusEl.className = 'everyalt-review-status'; }, 2000);
					}
				})
				.catch(function(err) {
					btn.disabled = false;
					if (statusEl) {
						statusEl.textContent = 'Error: ' + (err && err.message ? err.message : 'Save failed');
						statusEl.className = 'everyalt-review-status error';
					}
				});
		});
	});

	// Review Alt Text: Regenerate alt (AJAX)
	document.querySelectorAll('.everyalt-review-regenerate').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var mediaId = parseInt(btn.getAttribute('data-media-id'), 10);
			var item = btn.closest('.everyalt-review-item');
			var textarea = item ? item.querySelector('.everyalt-review-alt-field') : null;
			var statusEl = item ? item.querySelector('.everyalt-review-status') : null;
			btn.disabled = true;
			if (statusEl) statusEl.textContent = '';
			request('POST', '/everyalt-api/v1/bulk_generate_alt', { media_id: mediaId })
				.then(function(data) {
					btn.disabled = false;
					if (data && data.success && data.alt_text !== undefined) {
						if (textarea) textarea.value = data.alt_text;
						if (statusEl) {
							statusEl.textContent = 'Regenerated!';
							statusEl.className = 'everyalt-review-status success';
							setTimeout(function() { statusEl.textContent = ''; statusEl.className = 'everyalt-review-status'; }, 2000);
						}
					} else {
						if (statusEl) {
							statusEl.textContent = 'Error: ' + (data && data.message ? data.message : 'Regenerate failed');
							statusEl.className = 'everyalt-review-status error';
						}
					}
				})
				.catch(function(err) {
					btn.disabled = false;
					if (statusEl) {
						statusEl.textContent = 'Error: ' + (err && err.message ? err.message : 'Request failed');
						statusEl.className = 'everyalt-review-status error';
					}
				});
		});
	});
})();
