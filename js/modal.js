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
				<input class="jinx-modal-input" type="text" placeholder="Jinx it!" autocomplete="off" />
				<div class="jinx-modal-scroll-container">
					<ul id="jinx-modal-results"></ul>
					<div id="jinx-llm-suggestions"></div>
				</div>
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
				results.html('<li>Failed to load menus</li>');
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
		$('#jinx-llm-suggestions').empty();
		if (llmTimeout) clearTimeout(llmTimeout);

		// Don't show any results if the query is empty.
		if (query.trim() === '') {
			selectedIndex = -1;
			return;
		}

        // Show resuts with local data from WordPress menus.
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
		// LLM suggestions: only fetch for queries of 3+ chars
		if (query.trim().length >= 3) {
			lastQuery = query;
			llmTimeout = setTimeout(function() {
				showLLMLoading();
				fetchLLMSuggestions(query);
			}, 400);
		}
	}

	function showLLMLoading() {
        $('#jinx-llm-suggestions').addClass('jinx-loading');
        $('#jinx-llm-suggestions').html('<h3>AI Suggestions</h3><div class="jinx-llm-suggestion">Loading...</div>');
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
				let html = '<h3>Jinx AI Suggestions</h3>';
				data.llm.forEach(function(item) {
					html += `<div class="jinx-llm-suggestion"><a href="${item.url || '#'}">${item.title}</a></div>`;
				});
				$('#jinx-llm-suggestions').html(html);
			} else {
				$('#jinx-llm-suggestions').empty();
			}
			// After LLM results are in, reset selection index as the list has changed
			selectedIndex = -1;
		});
	}

	function scrollToSelected() {
		const container = $('.jinx-modal-scroll-container')[0];
		const sel = $('.jinx-selected', container)[0];

		if (sel) {
			const containerTop = container.scrollTop;
			const containerBottom = containerTop + container.clientHeight;
			const elTop = sel.offsetTop;
			const elBottom = elTop + sel.offsetHeight;

			if (elTop < containerTop) {
				container.scrollTop = elTop;
			} else if (elBottom > containerBottom) {
				container.scrollTop = elBottom - container.clientHeight;
			}
		}
	}

	// Mouse click navigation
	$(document).on('click', '#jinx-modal-results li', function(e) {
		e.preventDefault();
		const url = $(this).data('url');
		if (url) {
			// Cmd/Ctrl+Click to open in a new tab
			if (e.metaKey || e.ctrlKey) {
				window.open(url, '_blank');
			} else {
				window.location.href = url;
			}
		}
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
		// Handle Escape key globally for the modal, regardless of search results.
		if (e.key === 'Escape') {
			closeModal();
			return; // Exit early
		}

		const $navigableItems = $('#jinx-modal-results li, #jinx-llm-suggestions .jinx-llm-suggestion');
		if ($navigableItems.length === 0) return;

		if (e.key === 'ArrowDown') {
			e.preventDefault();
			selectedIndex = (selectedIndex + 1) % $navigableItems.length;
		} else if (e.key === 'ArrowUp') {
			e.preventDefault();
			selectedIndex = (selectedIndex - 1 + $navigableItems.length) % $navigableItems.length;
		} else if (e.key === 'Enter') {
			e.preventDefault();
			const $selectedItem = $navigableItems.eq(selectedIndex);
			if ($selectedItem.length) {
				const url = $selectedItem.is('li') ? $selectedItem.data('url') : $selectedItem.find('a').attr('href');
				if (url) {
					if (e.metaKey || e.ctrlKey) {
						window.open(url, '_blank');
					} else {
						window.location.href = url;
					}
				}
			}
			return; // Prevent further action on Enter
		} else {
			return; // Not a navigation key
		}

		// Update selection for both ArrowUp and ArrowDown
		$navigableItems.removeClass('jinx-selected');
		$navigableItems.eq(selectedIndex).addClass('jinx-selected');
		scrollToSelected();
	});

})(jQuery); 