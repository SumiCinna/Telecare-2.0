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

	// Profile menu (topbar) — same open/close pattern as the patient-side header
	var profileMenu = document.getElementById('profileMenu');
	var profileBtn = document.getElementById('profileBtn');
	if (profileMenu && profileBtn) {
		profileBtn.addEventListener('click', function (e) {
			e.stopPropagation();
			var isOpen = profileMenu.classList.toggle('open');
			profileBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		});
		profileMenu.querySelectorAll('.profile-option').forEach(function (opt) {
			opt.addEventListener('click', function () {
				profileMenu.classList.remove('open');
				profileBtn.setAttribute('aria-expanded', 'false');
			});
		});
		document.addEventListener('click', function (e) {
			if (!profileMenu.contains(e.target)) {
				profileMenu.classList.remove('open');
				profileBtn.setAttribute('aria-expanded', 'false');
			}
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				profileMenu.classList.remove('open');
				profileBtn.setAttribute('aria-expanded', 'false');
			}
		});
	}

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

	// ── Change Password modal: live validation + submit ────────────────
	var cpForm = document.getElementById('changePasswordForm');
	if (cpForm) {
		// Show/hide eye toggle for each password field
		document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var input = document.getElementById(btn.getAttribute('data-toggle-password'));
				var eyeIcon = btn.querySelector('.icon-eye');
				var eyeOffIcon = btn.querySelector('.icon-eye-off');
				var showing = input.type === 'text';
				input.type = showing ? 'password' : 'text';
				eyeIcon.style.display = showing ? 'block' : 'none';
				eyeOffIcon.style.display = showing ? 'none' : 'block';
				btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
			});
		});

		var oldPwInput = document.getElementById('cpOldPassword');
		var newPwInput = document.getElementById('cpNewPassword');
		var confirmPwInput = document.getElementById('cpConfirmPassword');
		var submitBtn = document.getElementById('cpSubmitBtn');
		var reqList = document.getElementById('pwRequirements');
		var matchHint = document.getElementById('pwMatchHint');
		var formError = document.getElementById('cpFormError');

		function checkRequirements(pw) {
			return {
				length: pw.length >= 8,
				upper: /[A-Z]/.test(pw),
				lower: /[a-z]/.test(pw),
				number: /[0-9]/.test(pw)
			};
		}

		function updateValidationState() {
			var pw = newPwInput.value;
			var confirm = confirmPwInput.value;
			var results = checkRequirements(pw);
			var allPassed = true;

			reqList.querySelectorAll('li').forEach(function (li) {
				var rule = li.getAttribute('data-rule');
				var passed = !!results[rule];
				li.classList.toggle('pw-ok', passed);
				if (!passed) allPassed = false;
			});

			var matches = confirm.length > 0 && pw === confirm;
			if (confirm.length === 0) {
				matchHint.style.display = 'none';
			} else {
				matchHint.style.display = 'block';
				matchHint.textContent = matches ? 'Passwords match.' : 'Passwords do not match.';
				matchHint.style.color = matches ? 'var(--green)' : 'var(--red)';
			}

			var oldFilled = oldPwInput.value.length > 0;
			submitBtn.disabled = !(allPassed && matches && oldFilled);
			formError.style.display = 'none';
			return allPassed && matches && oldFilled;
		}

		[oldPwInput, newPwInput, confirmPwInput].forEach(function (input) {
			input.addEventListener('input', updateValidationState);
		});

		// Reset the form whenever the modal is opened
		document.querySelectorAll('[data-open-modal="changePasswordModal"]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				cpForm.reset();
				reqList.querySelectorAll('li').forEach(function (li) { li.classList.remove('pw-ok'); });
				matchHint.style.display = 'none';
				formError.style.display = 'none';
				submitBtn.disabled = true;
				document.querySelectorAll('[data-toggle-password]').forEach(function (toggleBtn) {
					var input = document.getElementById(toggleBtn.getAttribute('data-toggle-password'));
					input.type = 'password';
					toggleBtn.querySelector('.icon-eye').style.display = 'block';
					toggleBtn.querySelector('.icon-eye-off').style.display = 'none';
					toggleBtn.setAttribute('aria-label', 'Show password');
				});
			});
		});

		cpForm.addEventListener('submit', function (e) {
			e.preventDefault();
			if (!updateValidationState()) return;

			submitBtn.disabled = true;
			submitBtn.textContent = 'Updating...';
			formError.style.display = 'none';

			var payload = new URLSearchParams({
				old_password: oldPwInput.value,
				new_password: newPwInput.value,
				confirm_password: confirmPwInput.value
			});

			fetch('change_password.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: payload.toString()
			})
				.then(function (res) { return res.json(); })
				.then(function (data) {
					if (data.success) {
						closeModal('changePasswordModal');
						showToast(data.message || 'Password updated successfully.');
					} else {
						formError.textContent = data.message || 'Something went wrong. Please try again.';
						formError.style.display = 'block';
					}
				})
				.catch(function () {
					formError.textContent = 'Could not reach the server. Please try again.';
					formError.style.display = 'block';
				})
				.finally(function () {
					submitBtn.disabled = false;
					submitBtn.textContent = 'Update Password';
					updateValidationState();
				});
		});
	}
});