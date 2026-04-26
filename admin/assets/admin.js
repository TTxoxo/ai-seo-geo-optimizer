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
	});
})();
