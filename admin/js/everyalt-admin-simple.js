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

	// Bulk run: process each image ID then reload
	var bulkBtn = document.getElementById('everyalt-bulk-run');
	if (bulkBtn) {
		bulkBtn.addEventListener('click', function() {
			var ids = [];
			try {
				ids = JSON.parse(bulkBtn.getAttribute('data-ids') || '[]');
			} catch (e) {}
			if (!ids.length) return;
			var progress = document.getElementById('everyalt-bulk-progress');
			if (progress) progress.classList.remove('hidden');
			bulkBtn.disabled = true;
			var index = 0;
			function next() {
				if (index >= ids.length) {
					if (progress) progress.classList.add('hidden');
					bulkBtn.disabled = false;
					window.location.reload();
					return;
				}
				var id = ids[index];
				request('POST', '/everyalt-api/v1/bulk_generate_alt', { media_id: id })
					.then(function() {
						index++;
						next();
					})
					.catch(function(err) {
						alert('Error: ' + (err.message || 'Request failed'));
						if (progress) progress.classList.add('hidden');
						bulkBtn.disabled = false;
					});
			}
			next();
		});
	}

	// Save alt: single row save button
	document.querySelectorAll('.everyalt-save-alt').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var row = btn.closest('tr');
			if (!row) return;
			var mediaId = row.getAttribute('data-media-id');
			var logId = row.getAttribute('data-log-id');
			var textarea = row.querySelector('.everyalt-alt-field');
			var altText = textarea ? textarea.value : '';
			btn.disabled = true;
			request('POST', '/everyalt-api/v1/save_alt', {
				media_id: mediaId,
				log_id: logId,
				alt_text: altText
			})
				.then(function() {
					btn.disabled = false;
					btn.textContent = 'Saved!';
					setTimeout(function() { btn.textContent = 'Save'; }, 2000);
				})
				.catch(function(err) {
					btn.disabled = false;
					alert('Error: ' + (err.message || 'Save failed'));
				});
		});
	});
})();
