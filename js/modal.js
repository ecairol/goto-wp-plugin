// JavaScript for Jinx search modal
(function($) {
	let modal, overlay, input, results;
	let menuData = [];
	let selectedIndex = -1;
	let llmTimeout = null;
	let lastQuery = '';
	let lastInputValue = '';

	function createModal() {
		if ($('.jinx-modal').length) return;
		$('body').append(`
			<div class="jinx-modal-overlay" style="display:none;"></div>
			<div class="jinx-modal" style="display:none;">
				<input class="jinx-modal-input" type="text" placeholder="Search Admin screens..." autocomplete="off" />
				<ul id="jinx-modal-results"></ul>
				<div id="jinx-llm-suggestions" style="margin-top:12px;"></div>
			</div>
		`);
		modal = $('.jinx-modal');
		overlay = $('.jinx-modal-overlay');
		input = $('.jinx-modal-input');
		results = $('#jinx-modal-results');

		$('.jinx-modal-close, .jinx-modal-overlay').on('click', closeModal);
	}

	function openModal() {
		createModal();
		modal.show();
		overlay.show();
		input.val('').focus();
		results.empty();
		$('#jinx-llm-suggestions').empty();
		selectedIndex = -1;
		lastQuery = '';
		lastInputValue = '';
		if (menuData.length === 0) fetchMenus();
	}

	function closeModal() {
		modal.hide();
		overlay.hide();
		if (llmTimeout) clearTimeout(llmTimeout);
	}

	function fetchMenus() {
		results.html('<li>Loading...</li>');
		$.ajax({
			url: Jinx.apiUrl,
			method: 'GET',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', Jinx.nonce);
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
		} else {
			filtered.forEach((item, idx) => {
				const selectedClass = idx === selectedIndex ? 'jinx-selected' : '';
				results.append(`<li class="${selectedClass}" data-url="${item.url}"><a href="#" tabindex="-1">${item.title}</a></li>`);
			});
		}
		// LLM suggestions: clear and set up delayed fetch
		$('#jinx-llm-suggestions').empty();
		if (llmTimeout) clearTimeout(llmTimeout);
		if (query.trim().length > 0) {
			lastQuery = query;
			llmTimeout = setTimeout(function() {
				showLLMLoading();
				fetchLLMSuggestions(query);
			}, 400);
		}
	}

	function showLLMLoading() {
        $('#jinx-llm-suggestions').addClass('jinx-loading');
        $('#jinx-llm-suggestions').html('<h3>AI Suggestions</h3><div>Loading...</div>');
	}

	function fetchLLMSuggestions(query) {
		$.ajax({
			url: Jinx.apiUrl.replace('/menus', '/search'),
			type: 'POST',
			data: JSON.stringify({ query: query }),
			contentType: 'application/json',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', Jinx.nonce);
			}
		})
		.done(function(data) {
            $('#jinx-llm-suggestions').removeClass('jinx-loading');
			if (query !== lastQuery) return; // Only show if still relevant
			if (data.llm && data.llm.length > 0) {
				let html = '<h3>AI Suggestions</h3>';
				data.llm.forEach(function(item) {
					html += `<div class="jinx-llm-suggestion"><a href="${item.url || '#'}">${item.title}</a></div>`;
				});
				$('#jinx-llm-suggestions').html(html);
			} else {
				$('#jinx-llm-suggestions').empty();
			}
		});
	}

	function scrollToSelected() {
		const sel = results.find('.jinx-selected');
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
	$(document).on('click', '#jinx-modal-results li', function(e) {
		e.preventDefault();
		const url = $(this).data('url');
		if (url) window.location.href = url;
	});

	// Open modal on admin bar button click
	$(document).on('click', '#wp-admin-bar-jinx-modal-btn a', function(e) {
		e.preventDefault();
		openModal();
	});

	// Open modal on Cmd+J or Ctrl+J
	$(window).on('keydown', function(e) {
		if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'j') {
			e.preventDefault();
			openModal();
		}
	});

	// Filter results as user types
	$(document).on('input', '.jinx-modal-input', function() {
		selectedIndex = -1;
		lastInputValue = $(this).val();
		showResults(lastInputValue);
	});

	// Keyboard navigation for modal input
	$(document).on('keydown', '.jinx-modal-input', function(e) {
		let $input = $(this);
		let query = $input.val();
		let filtered = menuData.filter(item => item.title.toLowerCase().includes(query.toLowerCase()));
		if (filtered.length === 0) return;
		if (e.key === 'ArrowDown') {
			e.preventDefault();
			selectedIndex = (selectedIndex + 1) % filtered.length;
			// Only update selection, do not trigger new search or LLM
			results.children().removeClass('jinx-selected');
			results.children().eq(selectedIndex).addClass('jinx-selected');
			scrollToSelected();
		} else if (e.key === 'ArrowUp') {
			e.preventDefault();
			selectedIndex = (selectedIndex - 1 + filtered.length) % filtered.length;
			results.children().removeClass('jinx-selected');
			results.children().eq(selectedIndex).addClass('jinx-selected');
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