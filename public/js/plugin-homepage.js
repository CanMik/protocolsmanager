(() => {
	const repositoryUrl = 'https://github.com/Cyril6789/protocolsmanager';

	const ensureRepositoryLink = () => {
		const cards = document.querySelectorAll('.marketplace [data-key="protocolsmanager"]');
		for (const card of cards) {
			const links = card.querySelector('.footer .misc-left .links');
			if (!links) {
				continue;
			}

			let link = Array.from(links.querySelectorAll('a')).find((anchor) => anchor.querySelector('i.ti-home-2'));
			if (!link) {
				link = document.createElement('a');
				link.className = 'add_tooltip';
				link.innerHTML = '<i class="ti ti-home-2" aria-hidden="true"></i>';
				links.prepend(link);
			}
			link.href = repositoryUrl;
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
			link.title = 'GitHub Protocols Manager';
			link.setAttribute('aria-label', 'GitHub Protocols Manager');
		}
	};

	const initialize = () => {
		if (!document.body) {
			return;
		}
		ensureRepositoryLink();
		new MutationObserver(ensureRepositoryLink).observe(document.body, { childList: true, subtree: true });
		document.addEventListener('click', (event) => {
			// GLPI renders icon markup inside the buttons, so resolve the actual
			// button from the clicked node before handling either action.
			const target = event.target instanceof Element ? event.target : event.target?.parentElement;
			if (!target) {
				return;
			}
			const removeButton = target.closest('[data-pv-remove-row]');
			if (removeButton) {
				event.preventDefault();
				removeButton.closest('tr[data-pv-config-row]')?.remove();
				return;
			}
			const addButton = target.closest('[data-pv-add-row]');
			if (addButton) {
				event.preventDefault();
				const listId = addButton.dataset.pvAddRow;
				const list = document.getElementById(listId);
				const template = document.getElementById(`template-${listId}`);
				if (!list || !template) {
					return;
				}
				const index = Number.parseInt(list.dataset.nextIndex || '0', 10);
				list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
				list.dataset.nextIndex = String(index + 1);
			}
		});
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initialize, { once: true });
	} else {
		initialize();
	}
})();
