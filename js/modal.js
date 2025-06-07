// JavaScript for GoTo AI search modal
(function($) {
	let modal, overlay, input, results;
	let menuData = [];
	let selectedIndex = -1;

	function createModal() {
		if ($('.goto-ai-modal').length) return;
		$('body').append(`
			<div class="goto-ai-modal-overlay" style="display:none;"></div>
			<div class="goto-ai-modal" style="display:none;">
				<button class="goto-ai-modal-close">&times;</button>
				<input class="goto-ai-modal-input" type="text" placeholder="Search admin screens..." autocomplete="off" />
				<ul id="goto-ai-modal-results"></ul>
			</div>
		`);
		modal = $('.goto-ai-modal');
		overlay = $('.goto-ai-modal-overlay');
		input = $('.goto-ai-modal-input');
		results = $('#goto-ai-modal-results');

		$('.goto-ai-modal-close, .goto-ai-modal-overlay').on('click', closeModal);
	}

	function openModal() {
		createModal();
		modal.show();
		overlay.show();
		input.val('').focus();
		results.empty();
		selectedIndex = -1;
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
			selectedIndex = -1;
			return;
		}
		filtered.forEach((item, idx) => {
			const selectedClass = idx === selectedIndex ? 'goto-ai-selected' : '';
			results.append(`<li class="${selectedClass}" data-url="${item.url}"><a href="#" tabindex="-1">${item.title}</a></li>`);
		});
	}

	function scrollToSelected() {
		const sel = results.find('.goto-ai-selected');
		if (sel.length) {
			const container = results[0];
			const el = sel[0];
			const elTop = el.offsetTop;
			const elBottom = elTop + el.offsetHeight;
			if (elTop < container.scrollTop) {
				container.scrollTop = elTop;
			} else if (elBottom > container.scrollTop + container.clientHeight) {
				container.scrollTop = elBottom - container.clientHeight;
			}
		}
	}

	// Mouse click navigation
	$(document).on('click', '#goto-ai-modal-results li', function(e) {
		e.preventDefault();
		const url = $(this).data('url');
		if (url) window.location.href = url;
	});

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
	$(document).on('input', '.goto-ai-modal-input', function() {
		selectedIndex = -1;
		showResults($(this).val());
	});

	// Keyboard navigation for modal input
	$(document).on('keydown', '.goto-ai-modal-input', function(e) {
		let $input = $(this);
		let query = $input.val();
		let filtered = menuData.filter(item => item.title.toLowerCase().includes(query.toLowerCase()));
		if (filtered.length === 0) return;
		if (e.key === 'ArrowDown') {
			e.preventDefault();
			selectedIndex = (selectedIndex + 1) % filtered.length;
			showResults(query);
			scrollToSelected();
		} else if (e.key === 'ArrowUp') {
			e.preventDefault();
			selectedIndex = (selectedIndex - 1 + filtered.length) % filtered.length;
			showResults(query);
			scrollToSelected();
		} else if (e.key === 'Enter') {
			if (selectedIndex >= 0 && filtered[selectedIndex]) {
				window.location.href = filtered[selectedIndex].url;
			}
		} else if (e.key === 'Escape') {
			closeModal();
		}
	});

})(jQuery); 