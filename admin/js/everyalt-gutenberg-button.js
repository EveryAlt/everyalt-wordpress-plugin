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
					data: { media_id: attachmentId },
					headers: { 'Content-Type': 'application/json' }
				}).then(function (res) {
					setIsBusy(false);
					if (res && res.success && res.alt_text) {
						setAttributes({ alt: res.alt_text });
					}
				}).catch(function () {
					setIsBusy(false);
				});
			}

			return el(Fragment, {},
				el(BlockEdit, props),
				el(InspectorControls, { key: 'everyalt' },
					el(PanelBody, {
						title: 'EveryAlt',
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
							}, 'Generate alt text with EveryAlt')
							: el('p', { className: 'everyalt-gutenberg-help', style: { margin: 0, fontSize: '12px', color: '#757575' } },
								'Select or upload an image to generate alt text.')
					)
				)
			);
		};
	}

	addFilter('editor.BlockEdit', 'everyalt/image-generate-alt', EveryAltImageEdit);
})();
