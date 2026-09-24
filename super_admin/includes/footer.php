</section>
	</main>
</div>

<!-- Change Password modal (available on every admin page via header.php's profile dropdown) -->
<div class="modal-overlay" id="changePasswordModal">
	<div class="modal-box" style="position:relative;">
		<button type="button" data-close-modal="changePasswordModal" aria-label="Close"
			style="position:absolute;top:14px;right:14px;width:30px;height:30px;border:0;background:none;border-radius:8px;color:var(--muted);cursor:pointer;display:flex;align-items:center;justify-content:center;"
			onmouseover="this.style.background='var(--canvas)'" onmouseout="this.style.background='none'">
			<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
		</button>
		<div class="modal-icon info">
			<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
		</div>
		<h3 class="modal-title">Change Password</h3>
		<p class="modal-text">Update the password for this Super Admin account.</p>

		<form id="changePasswordForm" class="form-grid" style="margin-top:18px;" autocomplete="off">
			<div class="form-group">
				<label>Current Password</label>
				<div class="password-field">
					<input type="password" class="form-control" id="cpOldPassword" name="old_password" required autocomplete="current-password">
					<button type="button" class="password-toggle" data-toggle-password="cpOldPassword" aria-label="Show password">
						<svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
						<svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="display:none;"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a20.3 20.3 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a20.3 20.3 0 0 1-2.68 3.9M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
					</button>
				</div>
			</div>
			<div class="form-group">
				<label>New Password</label>
				<div class="password-field">
					<input type="password" class="form-control" id="cpNewPassword" name="new_password" required autocomplete="new-password">
					<button type="button" class="password-toggle" data-toggle-password="cpNewPassword" aria-label="Show password">
						<svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
						<svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="display:none;"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a20.3 20.3 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a20.3 20.3 0 0 1-2.68 3.9M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
					</button>
				</div>
				<ul class="pw-requirements" id="pwRequirements">
					<li data-rule="length">At least 8 characters</li>
					<li data-rule="upper">At least 1 uppercase letter</li>
					<li data-rule="lower">At least 1 lowercase letter</li>
					<li data-rule="number">At least 1 number</li>
				</ul>
			</div>
			<div class="form-group">
				<label>Confirm New Password</label>
				<div class="password-field">
					<input type="password" class="form-control" id="cpConfirmPassword" name="confirm_password" required autocomplete="new-password">
					<button type="button" class="password-toggle" data-toggle-password="cpConfirmPassword" aria-label="Show password">
						<svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
						<svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="display:none;"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a20.3 20.3 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a20.3 20.3 0 0 1-2.68 3.9M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
					</button>
				</div>
				<div class="hint" id="pwMatchHint" style="display:none;"></div>
			</div>
			<div id="cpFormError" class="hint" style="color:var(--red);display:none;"></div>
			<div class="modal-actions">
				<button type="button" class="btn btn-secondary" data-close-modal="changePasswordModal">Cancel</button>
				<button type="submit" class="btn btn-primary" id="cpSubmitBtn" disabled>Update Password</button>
			</div>
		</form>
	</div>
</div>

<script>
	// Prevent the site-wide click-outside-closes-modal behavior for Change Password
	// specifically — only the X button and Cancel button should close it.
	document.getElementById('changePasswordModal').addEventListener('click', function (e) {
		if (e.target === this) {
			e.stopImmediatePropagation();
		}
	});
</script>
<script src="assets/js/js/app.js?v=<?= @filemtime(__DIR__ . '/../assets/js/js/app.js') ?: time() ?>"></script>
</body>
</html>