/**
 * EveryAlt – "Generate alt text with EveryAlt" in Block Editor (core/image block).
 */
(function () {
	'use strict';

	if (typeof wp === 'undefined' || !wp.hooks || !wp.element || !wp.apiFetch) return;

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var addFilter = wp.hooks.addFilter;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var Button = wp.components.Button;

	var i18n = (typeof everyaltBlock !== 'undefined' && everyaltBlock.i18n) || {};
	function t(key, fallback) { return i18n[key] || fallback; }

	// Show a dismissible snackbar/notice in the editor; failures used to be swallowed silently.
	function notify(type, message) {
		if (!wp.data || !wp.data.dispatch) return;
		var notices = wp.data.dispatch('core/notices');
		if (!notices) return;
		var create = type === 'error' ? notices.createErrorNotice : notices.createSuccessNotice;
		create(message, { id: 'everyalt-generate', type: 'snackbar', isDismissible: true });
	}

	function EveryAltImageEdit(BlockEdit) {
		return function (props) {
			if (props.name !== 'core/image') {
				return el(BlockEdit, props);
			}

			var attachmentId = props.attributes.id;
			var hasAttachment = attachmentId && attachmentId > 0;
			var setAttributes = props.setAttributes;
			var isBusyState = useState(false);
			var isBusy = isBusyState[0];
			var setIsBusy = isBusyState[1];

			function generateAlt() {
				if (!attachmentId) return;
				setIsBusy(true);
				wp.apiFetch({
					path: 'everyalt-api/v1/bulk_generate_alt',
					method: 'POST',
					data: { media_id: attachmentId }
				}).then(function (res) {
					setIsBusy(false);
					if (res && res.success && res.alt_text) {
						setAttributes({ alt: res.alt_text });
						notify('success', t('generated', 'Alt text generated.'));
					} else {
						notify('error', (res && res.message) ? res.message : t('failed', 'Could not generate alt text.'));
					}
				}).catch(function (err) {
					setIsBusy(false);
					notify('error', t('errorPrefix', 'Error:') + ' ' + ((err && err.message) ? err.message : t('failed', 'Could not generate alt text.')));
				});
			}

			return el(Fragment, {},
				el(BlockEdit, props),
				el(InspectorControls, { key: 'everyalt' },
					el(PanelBody, {
						title: t('panelTitle', 'EveryAlt'),
						initialOpen: true,
						className: 'everyalt-inspector-panel'
					},
						hasAttachment
							? el(Button, {
								className: 'everyalt-gutenberg-btn',
								variant: 'secondary',
								isSmall: true,
								onClick: generateAlt,
								isBusy: isBusy,
								disabled: isBusy,
								style: { marginTop: '8px' }
							}, t('button', 'Generate alt text with EveryAlt'))
							: el('p', { className: 'everyalt-gutenberg-help', style: { margin: 0, fontSize: '12px', color: '#757575' } },
								t('selectImage', 'Select or upload an image to generate alt text.'))
					)
				)
			);
		};
	}

	addFilter('editor.BlockEdit', 'everyalt/image-generate-alt', EveryAltImageEdit);
})();
