/**
 * EveryAlt admin - simple WordPress UI (no Vue/React).
 */
(function() {
	'use strict';

	var restUrl = typeof everyaltAdmin !== 'undefined' ? everyaltAdmin.restUrl : '';
	var restNonce = typeof everyaltAdmin !== 'undefined' ? everyaltAdmin.restNonce : '';

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
