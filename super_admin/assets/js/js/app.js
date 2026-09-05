// assets/js/app.js
// Frontend-only interactions for the Legal Policies workflow.
// No requests are sent yet — confirm actions just close the modal and
// show a lightweight toast so the flow can be reviewed before wiring up PHP handlers.

function openModal(id) {
	var el = document.getElementById(id);
	if (el) el.classList.add('open');
}
function closeModal(id) {
	var el = document.getElementById(id);
	if (el) el.classList.remove('open');
}
function showToast(message) {
	var toast = document.createElement('div');
	toast.textContent = message;
	toast.style.cssText = 'position:fixed;left:50%;bottom:28px;transform:translateX(-50%);background:#151c27;color:#fff;padding:12px 20px;border-radius:10px;font-size:12.5px;font-weight:600;box-shadow:0 12px 30px rgba(0,0,0,.25);z-index:200;opacity:0;transition:opacity .2s';
	document.body.appendChild(toast);
	requestAnimationFrame(function () { toast.style.opacity = '1'; });
	setTimeout(function () {
		toast.style.opacity = '0';
		setTimeout(function () { toast.remove(); }, 200);
	}, 2600);
}

document.addEventListener('DOMContentLoaded', function () {

	// Close modal on overlay click or [data-close-modal]
	document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
		overlay.addEventListener('click', function (e) {
			if (e.target === overlay) overlay.classList.remove('open');
		});
	});
	document.querySelectorAll('[data-close-modal]').forEach(function (btn) {
		btn.addEventListener('click', function () { closeModal(btn.getAttribute('data-close-modal')); });
	});
	document.querySelectorAll('[data-open-modal]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var modalId = btn.getAttribute('data-open-modal');
			// Optional per-row data (e.g. version number) fills matching [data-fill] spans in the modal
			var fillValue = btn.getAttribute('data-fill');
			if (fillValue) {
				var modal = document.getElementById(modalId);
				if (modal) modal.querySelectorAll('[data-fill-target]').forEach(function (t) { t.textContent = fillValue; });
			}
			openModal(modalId);
		});
	});
	// Confirm buttons: close the modal + toast (placeholder until backend actions are wired up)
	document.querySelectorAll('[data-confirm-action]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var modal = btn.closest('.modal-overlay');
			var message = btn.getAttribute('data-confirm-action');
			if (modal) modal.classList.remove('open');
			showToast(message);
		});
	});

	// Underline tab filter (Legal Policies list)
	document.querySelectorAll('.tabline').forEach(function (group) {
		var targetTable = document.querySelector(group.getAttribute('data-target'));
		group.querySelectorAll('button').forEach(function (tab) {
			tab.addEventListener('click', function () {
				group.querySelectorAll('button').forEach(function (t) { t.classList.remove('active'); });
				tab.classList.add('active');
				var filter = tab.getAttribute('data-filter');
				if (!targetTable) return;
				targetTable.querySelectorAll('tbody tr').forEach(function (row) {
					row.style.display = (filter === 'all' || row.getAttribute('data-type') === filter) ? '' : 'none';
				});
			});
		});
	});

	// Kebab / row-action dropdowns
	document.querySelectorAll('.kebab-btn').forEach(function (btn) {
		btn.addEventListener('click', function (e) {
			e.stopPropagation();
			var menu = btn.nextElementSibling;
			document.querySelectorAll('.dropdown-menu.open').forEach(function (m) { if (m !== menu) m.classList.remove('open'); });
			menu.classList.toggle('open');
		});
	});
	document.addEventListener('click', function () {
		document.querySelectorAll('.dropdown-menu.open').forEach(function (m) { m.classList.remove('open'); });
	});

	// Rich text editor toolbar
	document.querySelectorAll('.editor-toolbar button[data-cmd]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			document.execCommand(btn.getAttribute('data-cmd'), false, null);
			var body = btn.closest('.editor').querySelector('.editor-body');
			if (body) body.focus();
		});
	});

	// Simple search-as-you-type filter on the policies table
	var policySearch = document.getElementById('policySearch');
	if (policySearch) {
		policySearch.addEventListener('input', function () {
			var q = policySearch.value.trim().toLowerCase();
			document.querySelectorAll('#policiesTable tbody tr').forEach(function (row) {
				row.style.display = row.textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none';
			});
		});
	}

	// Status select dot color sync (Edit Policy settings panel)
	var statusSelect = document.getElementById('statusSelect');
	if (statusSelect) {
		var dot = document.querySelector('.status-dot');
		var colors = { Published: '#10b981', Draft: '#d97706', Archived: '#94a3b8' };
		function syncDot() { if (dot) dot.style.background = colors[statusSelect.value] || '#10b981'; }
		statusSelect.addEventListener('change', syncDot);
		syncDot();
	}
});