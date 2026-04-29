(function () {
	'use strict';

	window.aiSeoGeoAdmin = window.aiSeoGeoAdmin || {
		version: '0.1.0'
	};

	document.addEventListener('DOMContentLoaded', function () {
		var master = document.getElementById('ai-seo-geo-check-all');
		if (!master) {
			return;
		}

		master.addEventListener('change', function () {
			var checkboxes = document.querySelectorAll('input[name="post_ids[]"]');
			checkboxes.forEach(function (item) {
				item.checked = master.checked;
			});
		});

		var highRiskConfirm = document.querySelector('input[name="confirm_high_ai_style_risk"]');
		var applyButton = document.querySelector('input[name="submit"][value]');
		if (highRiskConfirm && applyButton) {
			applyButton.disabled = !highRiskConfirm.checked;
			highRiskConfirm.addEventListener('change', function () {
				applyButton.disabled = !highRiskConfirm.checked;
			});
		}
	});
})();
