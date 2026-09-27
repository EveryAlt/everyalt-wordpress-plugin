/**
 * EveryAlt admin - simple WordPress UI (no Vue/React).
 */
(function() {
	'use strict';

	var restUrl = typeof everyaltAdmin !== 'undefined' ? everyaltAdmin.restUrl : '';
	var restNonce = typeof everyaltAdmin !== 'undefined' ? everyaltAdmin.restNonce : '';
	var ajaxUrl = typeof everyaltAdmin !== 'undefined' ? everyaltAdmin.ajaxUrl : '';
	var validateKeyNonce = typeof everyaltAdmin !== 'undefined' ? everyaltAdmin.validateKeyNonce : '';
	var i18n = (typeof everyaltAdmin !== 'undefined' && everyaltAdmin.i18n) || {};
	function t(key, fallback) { return i18n[key] || fallback; }

	// Announce a message to screen readers via WordPress's shared live regions (wp.a11y.speak), which
	// are always present in the page, so the first message isn't lost like it can be with a live
	// region that was just revealed.
	function speak(message, assertive) {
		if (message && window.wp && wp.a11y && wp.a11y.speak) wp.a11y.speak(message, assertive ? 'assertive' : 'polite');
	}

	// Mark a button as busy without the disabled attribute: a disabled button loses keyboard focus
	// (it jumps to the top of the page), while aria-disabled keeps the user's place. Clicks are
	// ignored while busy.
	function isBusy(btn) { return btn.getAttribute('aria-disabled') === 'true'; }
	function setBusy(btn, busy) {
		if (busy) btn.setAttribute('aria-disabled', 'true'); else btn.removeAttribute('aria-disabled');
	}

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

	// Settings: show only the API key block for the selected model's provider.
	var modelRadios = document.querySelectorAll('.everyalt-model-list input[type="radio"]');
	function showProviderKey() {
		var checked = document.querySelector('.everyalt-model-list input[type="radio"]:checked');
		var provider = checked ? checked.getAttribute('data-provider') : '';
		document.querySelectorAll('.everyalt-provider-key').forEach(function(block) {
			block.classList.toggle('hidden', block.getAttribute('data-provider') !== provider);
		});
	}
	if (modelRadios.length) {
		modelRadios.forEach(function(radio) { radio.addEventListener('change', showProviderKey); });
		showProviderKey();
	}

	// Validate API key (Settings tab), one button per provider.
	document.querySelectorAll('.everyalt-validate-key').forEach(function(validateKeyBtn) {
		var block = validateKeyBtn.closest('.everyalt-provider-key');
		var resultEl = block ? block.querySelector('.everyalt-validate-result') : null;
		var provider = validateKeyBtn.getAttribute('data-provider');
		function showResult(ok, message) {
			resultEl.style.display = 'block';
			resultEl.className = 'everyalt-validate-result notice ' + (ok ? 'notice-success' : 'notice-error');
			resultEl.textContent = '';
			var p = document.createElement('p');
			p.textContent = message;
			resultEl.appendChild(p);
			speak(message, !ok);
		}
		validateKeyBtn.addEventListener('click', function() {
			var keyInput = block ? block.querySelector('input[type="password"]') : null;
			var key = keyInput ? keyInput.value.trim() : '';
			if (!resultEl) return;
			resultEl.style.display = 'none';
			resultEl.className = 'everyalt-validate-result';
			if (isBusy(validateKeyBtn)) return;
			setBusy(validateKeyBtn, true);
			var formData = new FormData();
			formData.append('action', 'everyalt_validate_key');
			formData.append('nonce', validateKeyNonce);
			formData.append('provider', provider);
			formData.append('key', key);
			fetch(ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			})
				.then(function(r) { return r.json(); })
				.then(function(data) {
					setBusy(validateKeyBtn, false);
					var msg = (data.data && data.data.message) ? data.data.message : (data.success ? '' : t('error', 'Error'));
					showResult(!!data.success, msg);
				})
				.catch(function() {
					setBusy(validateKeyBtn, false);
					showResult(false, t('requestFailed', 'Request failed'));
				});
		});
	});

	// Bulk tabs: highlight selected image cards. Select all/none change checkboxes without a change
	// event, so they call syncCards() themselves.
	function syncCards() {
		document.querySelectorAll('.everyalt-bulk-card').forEach(function(card) {
			var cb = card.querySelector('input[type="checkbox"]');
			card.classList.toggle('is-selected', !!(cb && cb.checked));
		});
	}
	document.addEventListener('change', function(e) {
		if (e.target && e.target.closest && e.target.closest('.everyalt-bulk-card')) syncCards();
	});

	// Bulk: Select all / Select none
	var selectAllBtn = document.getElementById('everyalt-bulk-select-all');
	var selectNoneBtn = document.getElementById('everyalt-bulk-select-none');
	if (selectAllBtn) {
		selectAllBtn.addEventListener('click', function() {
			document.querySelectorAll('.everyalt-bulk-checkbox:not(:disabled)').forEach(function(cb) { cb.checked = true; });
			syncCards();
		});
	}
	if (selectNoneBtn) {
		selectNoneBtn.addEventListener('click', function() {
			document.querySelectorAll('.everyalt-bulk-checkbox').forEach(function(cb) { cb.checked = false; });
			syncCards();
		});
	}

	// Background queue: "Generate for selected" / "Generate for all" add jobs, then this page helps work
	// through them (WP-Cron also does, even after the page is closed). The panel shows progress on every
	// EveryAlt tab, and resumes automatically if jobs are waiting when a page opens.
	var queuePanel = document.getElementById('everyalt-queue-panel');
	var queueText = document.getElementById('everyalt-queue-text');
	var queueLog = document.getElementById('everyalt-queue-log');
	var queueClearBtn = document.getElementById('everyalt-queue-clear');
	var queueRunning = false;

	function fmt(str, n) { return String(str).replace('%d', n); }

	function renderQueue(status) {
		if (!queuePanel || !status) return;
		var due = status.due || 0;
		var waiting = (status.total || 0) - due;
		var msg;
		if (status.paused === 'budget') {
			msg = t('queuePausedBudget', 'Paused: monthly spending limit reached.');
		} else if (status.paused === 'no_key') {
			msg = t('queuePausedKey', 'Paused: add an API key in Settings.');
		} else if (due > 0) {
			msg = fmt(t('queueWorking', 'Generating in the background: %d remaining.'), due);
			if (waiting > 0) msg += ' ' + fmt(t('queueWaiting', '%d waiting to retry.'), waiting);
		} else if (waiting > 0) {
			msg = fmt(t('queueWaiting', '%d waiting to retry.'), waiting);
		} else {
			msg = t('queueDone', 'All done.');
		}
		queueText.textContent = msg;
		queuePanel.classList.remove('hidden');
		queuePanel.classList.toggle('is-working', due > 0 && !status.paused);
		queuePanel.classList.toggle('notice-warning', !!status.paused);
		queuePanel.classList.toggle('notice-info', !status.paused);
		if (queueClearBtn) queueClearBtn.classList.toggle('hidden', !status.total);
	}

	function markItem(r) {
		var selector = r.type === 'title' ? '.everyalt-bulk-title-item' : '.everyalt-bulk-item';
		var itemEl = document.querySelector(selector + '[data-media-id="' + r.media_id + '"]');
		var text = r.success ? (r.decorative ? t('decorative', 'Marked as decorative') : r.text) : (r.message || t('error', 'Error'));
		if (itemEl) {
			var statusEl = itemEl.querySelector('.everyalt-bulk-item-status');
			if (statusEl) {
				statusEl.textContent = (r.success ? '\u2713 ' : '\u2717 ') + text;
				statusEl.className = 'everyalt-bulk-item-status ' + (r.success ? 'success' : 'error');
			}
			var cb = itemEl.querySelector('input[type="checkbox"]');
			if (cb && r.success) { cb.checked = false; cb.disabled = true; }
			itemEl.classList.toggle('is-done', !!r.success);
			itemEl.classList.toggle('is-error', !r.success);
			itemEl.classList.remove('is-selected');
		}
		if (queueLog) {
			var li = document.createElement('li');
			li.className = r.success ? 'success' : 'error';
			li.textContent = '#' + r.media_id + ': ' + text;
			queueLog.insertBefore(li, queueLog.firstChild);
			while (queueLog.children.length > 50) queueLog.removeChild(queueLog.lastChild);
		}
	}

	// Keep calling /queue/process while jobs are due. If WP-Cron holds the lock, check back shortly.
	function runQueue() {
		if (queueRunning) return;
		queueRunning = true;
		(function step() {
			request('POST', '/everyalt-api/v1/queue/process')
				.then(function(data) {
					var results = data.results || [];
					results.forEach(markItem);
					renderQueue(data.status);
					// One summary per batch rather than one announcement per image.
					if (results.length || (data.status && (data.status.paused || !data.status.total))) {
						speak((results.length ? fmt(t('queueBatchDone', '%d finished.'), results.length) + ' ' : '') + queueText.textContent, !!(data.status && data.status.paused));
					}
					var st = data.status || {};
					if (st.paused || !st.total) { queueRunning = false; return; }
					if (data.locked || !st.due) {
						setTimeout(step, st.due ? 5000 : 30000);
						return;
					}
					step();
				})
				.catch(function() {
					// Network hiccup or timeout: WP-Cron keeps going; try again in a bit.
					setTimeout(step, 15000);
				});
		})();
	}

	function addToQueue(body, btn) {
		if (btn) {
			if (isBusy(btn)) return Promise.resolve();
			setBusy(btn, true);
		}
		return request('POST', '/everyalt-api/v1/queue', body)
			.then(function(data) {
				if (btn) setBusy(btn, false);
				if (queueLog) {
					var li = document.createElement('li');
					li.textContent = data.added ? fmt(t('queueAdded', 'Added %d images to the queue.'), data.added) : t('queueNothing', 'Those images were already queued.');
					queueLog.insertBefore(li, queueLog.firstChild);
					speak(li.textContent);
				}
				renderQueue(data.status);
				runQueue();
			})
			.catch(function(err) {
				if (btn) setBusy(btn, false);
				renderQueue({ total: 0 });
				queueText.textContent = t('errorPrefix', 'Error:') + ' ' + (err && err.message ? err.message : t('requestFailed', 'Request failed'));
				speak(queueText.textContent, true);
			});
	}

	document.querySelectorAll('.everyalt-queue-selected').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var boxes = document.querySelectorAll(btn.getAttribute('data-checkboxes') + ':checked');
			var ids = Array.prototype.map.call(boxes, function(cb) { return parseInt(cb.value, 10); });
			if (!ids.length) return;
			addToQueue({ type: btn.getAttribute('data-type'), media_ids: ids }, btn);
		});
	});

	document.querySelectorAll('.everyalt-queue-all').forEach(function(btn) {
		btn.addEventListener('click', function() {
			addToQueue({ type: btn.getAttribute('data-type'), all: true }, btn);
		});
	});

	if (queueClearBtn) {
		queueClearBtn.addEventListener('click', function() {
			request('DELETE', '/everyalt-api/v1/queue').then(function(status) {
				renderQueue(status);
				queueText.textContent = t('queueCleared', 'Queue cleared.');
				speak(queueText.textContent);
			});
		});
	}

	var initialQueue = typeof everyaltAdmin !== 'undefined' ? everyaltAdmin.queue : null;
	if (initialQueue && initialQueue.total) {
		renderQueue(initialQueue);
		if (!initialQueue.paused) runQueue();
	}

	// Review Alt Text: Save edited alt (AJAX)
	document.querySelectorAll('.everyalt-review-save').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var mediaId = parseInt(btn.getAttribute('data-media-id'), 10);
			var item = btn.closest('.everyalt-review-item');
			var textarea = item ? item.querySelector('.everyalt-review-alt-field') : null;
			var statusEl = item ? item.querySelector('.everyalt-review-status') : null;
			var altText = textarea ? textarea.value : '';
			if (isBusy(btn)) return;
			setBusy(btn, true);
			request('POST', '/everyalt-api/v1/save_alt', {
				media_id: mediaId,
				alt_text: altText
			})
				.then(function() {
					setBusy(btn, false);
					if (statusEl) {
						statusEl.textContent = t('saved', 'Saved!');
						speak(statusEl.textContent);
						statusEl.className = 'everyalt-review-status success';
						setTimeout(function() { statusEl.textContent = ''; statusEl.className = 'everyalt-review-status'; }, 2000);
					}
				})
				.catch(function(err) {
					setBusy(btn, false);
					if (statusEl) {
						statusEl.textContent = t('errorPrefix', 'Error:') + ' ' + (err && err.message ? err.message : t('saveFailed', 'Save failed'));
						speak(statusEl.textContent, true);
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
			if (isBusy(btn)) return;
			setBusy(btn, true);
			if (statusEl) statusEl.textContent = '';
			var body = { media_id: mediaId };
			if (btn.getAttribute('data-describe')) body.describe = true;
			request('POST', '/everyalt-api/v1/bulk_generate_alt', body)
				.then(function(data) {
					setBusy(btn, false);
					if (data && data.success && data.alt_text !== undefined) {
						if (textarea) textarea.value = data.alt_text;
						if (statusEl) {
							statusEl.textContent = data.decorative ? t('decorative', 'Marked as decorative') : t('regenerated', 'Regenerated!');
							speak(statusEl.textContent);
							statusEl.className = 'everyalt-review-status success';
							setTimeout(function() { statusEl.textContent = ''; statusEl.className = 'everyalt-review-status'; }, 2000);
						}
					} else {
						if (statusEl) {
							statusEl.textContent = t('errorPrefix', 'Error:') + ' ' + (data && data.message ? data.message : t('regenerateFailed', 'Regenerate failed'));
							speak(statusEl.textContent, true);
							statusEl.className = 'everyalt-review-status error';
						}
					}
				})
				.catch(function(err) {
					setBusy(btn, false);
					if (statusEl) {
						statusEl.textContent = t('errorPrefix', 'Error:') + ' ' + (err && err.message ? err.message : t('requestFailed', 'Request failed'));
						speak(statusEl.textContent, true);
						statusEl.className = 'everyalt-review-status error';
					}
				});
		});
	});

	// Bulk Titles: Select all / Select none
	var titleSelectAllBtn = document.getElementById('everyalt-bulk-title-select-all');
	var titleSelectNoneBtn = document.getElementById('everyalt-bulk-title-select-none');
	if (titleSelectAllBtn) {
		titleSelectAllBtn.addEventListener('click', function() {
			document.querySelectorAll('.everyalt-bulk-title-checkbox:not(:disabled)').forEach(function(cb) { cb.checked = true; });
			syncCards();
		});
	}
	if (titleSelectNoneBtn) {
		titleSelectNoneBtn.addEventListener('click', function() {
			document.querySelectorAll('.everyalt-bulk-title-checkbox').forEach(function(cb) { cb.checked = false; });
			syncCards();
		});
	}

	// Review Image Titles: Save edited title (AJAX)
	document.querySelectorAll('.everyalt-review-title-save').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var mediaId = parseInt(btn.getAttribute('data-media-id'), 10);
			var item = btn.closest('.everyalt-review-title-item');
			var textarea = item ? item.querySelector('.everyalt-review-title-field') : null;
			var statusEl = item ? item.querySelector('.everyalt-review-status') : null;
			var title = textarea ? textarea.value : '';
			if (isBusy(btn)) return;
			setBusy(btn, true);
			request('POST', '/everyalt-api/v1/save_title', {
				media_id: mediaId,
				title: title
			})
				.then(function() {
					setBusy(btn, false);
					if (statusEl) {
						statusEl.textContent = t('saved', 'Saved!');
						speak(statusEl.textContent);
						statusEl.className = 'everyalt-review-status success';
						setTimeout(function() { statusEl.textContent = ''; statusEl.className = 'everyalt-review-status'; }, 2000);
					}
				})
				.catch(function(err) {
					setBusy(btn, false);
					if (statusEl) {
						statusEl.textContent = t('errorPrefix', 'Error:') + ' ' + (err && err.message ? err.message : t('saveFailed', 'Save failed'));
						speak(statusEl.textContent, true);
						statusEl.className = 'everyalt-review-status error';
					}
				});
		});
	});

	// Review Image Titles: Regenerate title (AJAX)
	document.querySelectorAll('.everyalt-review-title-regenerate').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var mediaId = parseInt(btn.getAttribute('data-media-id'), 10);
			var item = btn.closest('.everyalt-review-title-item');
			var textarea = item ? item.querySelector('.everyalt-review-title-field') : null;
			var statusEl = item ? item.querySelector('.everyalt-review-status') : null;
			if (isBusy(btn)) return;
			setBusy(btn, true);
			if (statusEl) statusEl.textContent = '';
			request('POST', '/everyalt-api/v1/bulk_generate_title', { media_id: mediaId })
				.then(function(data) {
					setBusy(btn, false);
					if (data && data.success && data.title !== undefined) {
						if (textarea) textarea.value = data.title;
						if (statusEl) {
							statusEl.textContent = t('regenerated', 'Regenerated!');
							speak(statusEl.textContent);
							statusEl.className = 'everyalt-review-status success';
							setTimeout(function() { statusEl.textContent = ''; statusEl.className = 'everyalt-review-status'; }, 2000);
						}
					} else {
						if (statusEl) {
							statusEl.textContent = t('errorPrefix', 'Error:') + ' ' + (data && data.message ? data.message : t('regenerateFailed', 'Regenerate failed'));
							speak(statusEl.textContent, true);
							statusEl.className = 'everyalt-review-status error';
						}
					}
				})
				.catch(function(err) {
					setBusy(btn, false);
					if (statusEl) {
						statusEl.textContent = t('errorPrefix', 'Error:') + ' ' + (err && err.message ? err.message : t('requestFailed', 'Request failed'));
						speak(statusEl.textContent, true);
						statusEl.className = 'everyalt-review-status error';
					}
				});
		});
	});
})();
