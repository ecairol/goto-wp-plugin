// JavaScript for GoTo AI search modal
(function($) {
	let modal, overlay, input, results;
	let menuData = [];

	function createModal() {
		if ($('#goto-ai-modal').length) return;
		$('body').append(`
			<div id="goto-ai-modal-overlay" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.3);z-index:9998;"></div>
			<div id="goto-ai-modal" style="display:none;position:fixed;top:10vh;left:50%;transform:translateX(-50%);width:400px;max-width:86vw;background:#fff;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,0.2);z-index:9999;padding:24px 20px 16px 20px;">
				<input id="goto-ai-modal-input" type="text" placeholder="Search admin screens..." style="width:100%;padding:8px 12px;font-size:16px;margin-bottom:12px;" />
				<ul id="goto-ai-modal-results" style="list-style:none;padding:0;margin:0;max-height:300px;overflow:auto;"></ul>
			</div>
		`);
		modal = $('#goto-ai-modal');
		overlay = $('#goto-ai-modal-overlay');
		input = $('#goto-ai-modal-input');
		results = $('#goto-ai-modal-results');

		$('#goto-ai-modal-close, #goto-ai-modal-overlay').on('click', closeModal);
		input.on('keydown', function(e) {
			if (e.key === 'Escape') closeModal();
		});
	}

	function openModal() {
		createModal();
		modal.show();
		overlay.show();
		input.val('').focus();
		results.empty();
		if (menuData.length === 0) fetchMenus();
	}

	function closeModal() {
		modal.hide();
		overlay.hide();
	}

	function fetchMenus() {
		results.html('<li>Loading...</li>');
		$.ajax({
			url: GoToAI.apiUrl,
			method: 'GET',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', GoToAI.nonce);
			}
		})
			.done(function(data) {
				menuData = flattenMenus(data);
				showResults('');
			})
			.fail(function() {
				results.html('<li style="color:red;">Failed to load menus</li>');
			});
	}

	function flattenMenus(data) {
		let items = [];
		if (!data) return items;
		Object.values(data).forEach(menu => {
			items.push({ title: menu.title, url: menu.url });
			if (menu.children) {
				menu.children.forEach(child => {
					items.push({ title: menu.title + ' > ' + child.title, url: child.url });
				});
			}
		});
		return items;
	}

	function showResults(query) {
		results.empty();
		let filtered = menuData.filter(item => item.title.toLowerCase().includes(query.toLowerCase()));
		if (filtered.length === 0) {
			results.html('<li>No results found</li>');
			return;
		}
		filtered.forEach(item => {
			results.append(`<li style="padding:6px 0;"><a href="${item.url}" style="text-decoration:none;">${item.title}</a></li>`);
		});
	}

	// Open modal on admin bar button click
	$(document).on('click', '#wp-admin-bar-goto-ai-modal-btn a', function(e) {
		e.preventDefault();
		openModal();
	});

	// Open modal on Cmd+K or Ctrl+K
	$(window).on('keydown', function(e) {
		if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
			e.preventDefault();
			openModal();
		}
	});

	// Filter results as user types
	$(document).on('input', '#goto-ai-modal-input', function() {
		showResults($(this).val());
	});

})(jQuery); 