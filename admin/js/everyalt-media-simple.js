/**
 * EveryAlt - Generate alt button on media edit page (no Vue/React).
 */
(function() {
	'use strict';

	var config = typeof everyaltMedia !== 'undefined' ? everyaltMedia : {};
	var restUrl = (config.restUrl || '').replace(/\/$/, '');
	var restNonce = config.restNonce || '';
	var mediaId = config.mediaId || 0;
	var i18n = config.i18n || {};
	function t(key, fallback) { return i18n[key] || fallback; }
	// Announce results to screen readers (the message paragraph alone is not a live region).
	function speak(message, assertive) {
		if (message && window.wp && wp.a11y && wp.a11y.speak) wp.a11y.speak(message, assertive ? 'assertive' : 'polite');
	}

	if (!restUrl || !mediaId) return;

	var wrap = document.getElementById('everyalt-custom-media-button');
	if (!wrap) return;

	var btn = document.createElement('button');
	btn.type = 'button';
	btn.className = 'button';
	btn.textContent = t('button', 'Generate alt text with EveryAlt');
	wrap.appendChild(btn);

	var msg = document.createElement('p');
	msg.className = 'everyalt-media-message';
	msg.style.marginTop = '8px';
	wrap.appendChild(msg);

	btn.addEventListener('click', function() {
		// aria-disabled instead of disabled, so keyboard focus stays on the button.
		if (btn.getAttribute('aria-disabled') === 'true') return;
		btn.setAttribute('aria-disabled', 'true');
		msg.textContent = t('generating', 'Generating…');
		speak(msg.textContent);
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
				btn.removeAttribute('aria-disabled');
				var success = data && data.success && (data.alt_text || data.decorative);
				if (success) {
					msg.textContent = data.decorative ? t('decorative', 'Marked as decorative: alt text left empty on purpose.') : t('generated', 'Alt text generated.');
					msg.className = 'everyalt-media-message notice notice-success';
					speak(msg.textContent);
					var altField = document.getElementById('attachment_alt') || document.querySelector('textarea[name*="_wp_attachment_image_alt"]') || document.querySelector('input[name*="_wp_attachment_image_alt"]');
					if (altField) {
						altField.value = data.alt_text;
					}
				} else {
					msg.textContent = (data && data.message) ? data.message : t('failed', 'Could not generate alt text.');
					msg.className = 'everyalt-media-message notice notice-error';
					speak(msg.textContent, true);
				}
			})
			.catch(function(err) {
				btn.removeAttribute('aria-disabled');
				msg.textContent = t('errorPrefix', 'Error:') + ' ' + (err.message || t('failed', 'Could not generate alt text.'));
				msg.className = 'everyalt-media-message notice notice-error';
				speak(msg.textContent, true);
			});
	});
})();
