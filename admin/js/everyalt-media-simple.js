/**
 * EveryAlt - Generate alt button on media edit page (no Vue/React).
 */
(function() {
	'use strict';

	var config = typeof everyaltMedia !== 'undefined' ? everyaltMedia : {};
	var restUrl = (config.restUrl || '').replace(/\/$/, '');
	var restNonce = config.restNonce || '';
	var mediaId = config.mediaId || 0;

	if (!restUrl || !mediaId) return;

	var wrap = document.getElementById('everyalt-custom-media-button');
	if (!wrap) return;

	var btn = document.createElement('button');
	btn.type = 'button';
	btn.className = 'button';
	btn.textContent = 'Generate alt text with EveryAlt';
	wrap.appendChild(btn);

	var msg = document.createElement('p');
	msg.className = 'everyalt-media-message';
	msg.style.marginTop = '8px';
	wrap.appendChild(msg);

	btn.addEventListener('click', function() {
		btn.disabled = true;
		msg.textContent = 'Generating…';
		msg.className = 'everyalt-media-message';

		fetch(restUrl + '/everyalt-api/v1/bulk_generate_alt', {
			method: 'POST',
			headers: {
				'X-WP-Nonce': restNonce,
				'Content-Type': 'application/json'
			},
			body: JSON.stringify({ media_id: mediaId })
		})
			.then(function(r) {
				if (!r.ok) throw new Error(r.statusText);
				return r.json();
			})
			.then(function(data) {
				btn.disabled = false;
				if (data.alt_text) {
					msg.textContent = 'Alt text generated.';
					msg.className = 'everyalt-media-message notice notice-success';
					var altField = document.getElementById('attachment_alt');
					if (altField) {
						altField.value = data.alt_text;
					}
				} else {
					msg.textContent = 'Could not generate alt text.';
					msg.className = 'everyalt-media-message notice notice-error';
				}
			})
			.catch(function(err) {
				btn.disabled = false;
				msg.textContent = 'Error: ' + (err.message || 'Request failed');
				msg.className = 'everyalt-media-message notice notice-error';
			});
	});
})();
