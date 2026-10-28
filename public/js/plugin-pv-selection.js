(() => {
	document.addEventListener('change', (event) => {
		const control = event.target;
		if (!(control instanceof HTMLInputElement)) {
			return;
		}
		const master = control.matches('[data-pv-select-all]') ? control : null;
		if (master) {
			const form = master.closest('form');
			form?.querySelectorAll('.protocolsmanager-pv-item').forEach((checkbox) => {
				checkbox.checked = master.checked;
			});
			return;
		}
		if (control.matches('.protocolsmanager-pv-item')) {
			const form = control.closest('form');
			const toggle = form?.querySelector('[data-pv-select-all]');
			const items = form ? Array.from(form.querySelectorAll('.protocolsmanager-pv-item')) : [];
			if (toggle && items.length > 0) {
				const selected = items.filter((checkbox) => checkbox.checked).length;
				toggle.checked = selected === items.length;
				toggle.indeterminate = selected > 0 && selected < items.length;
			}
		}
	});
})();
