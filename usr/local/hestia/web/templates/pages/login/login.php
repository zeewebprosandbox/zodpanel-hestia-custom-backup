<?php
$error = $error ?? '';
if (!empty($_GET['error'])) {
    $error = $_GET['error'];
}
?>
<div class="zod-login-wrapper" style="min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 20px;">
	<div class="zod-login-card" style="background: rgba(18, 18, 24, 0.85); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 16px; padding: 36px 32px; width: 100%; max-width: 400px; box-shadow: 0 12px 40px rgba(0, 0, 0, 0.5);">
		
		<!-- Brand Header -->
		<div style="text-align: center; margin-bottom: 28px;">
			<div style="display: inline-flex; align-items: center; gap: 8px; margin-bottom: 12px;">
				<span style="font-size: 22px; filter: drop-shadow(0 0 10px rgba(99, 102, 241, 0.6));">⚡</span>
				<span style="font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 900; font-size: 22px; letter-spacing: -0.02em; background: linear-gradient(135deg, #ffffff 0%, #a5b4fc 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">ZODPANEL</span>
			</div>
			<p style="font-size: 13px; color: #a1a1aa; margin: 0; font-weight: 500;">Cloud Infrastructure Control Panel</p>
		</div>

		<form id="login-form" method="post" action="/login/">
			<input type="hidden" name="token" value="<?= tohtml($_SESSION["token"]) ?>">
			
			<?php if (!empty($error)) { ?>
				<div class="error-message" style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444; padding: 10px 14px; border-radius: 8px; margin-bottom: 18px; font-size: 13px; font-weight: 600; text-align: center;">
					<?= tohtml($error) ?>
				</div>
			<?php } ?>

			<div style="margin-bottom: 18px;">
				<label for="username" style="font-weight: 600; font-size: 12px; margin-bottom: 6px; display: block; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.04em;"><?= tohtml(_("Username")) ?></label>
				<input type="text" class="form-control" name="user" id="username" autocomplete="username" value="<?= tohtml($_POST['user'] ?? '') ?>" required autofocus placeholder="admin" style="min-height: 42px; width: 100%; border-radius: 8px; background: #14141a; border: 1px solid rgba(255, 255, 255, 0.08); color: #f4f4f7; padding: 8px 14px; font-size: 13px;">
			</div>

			<div style="margin-bottom: 22px;">
				<label for="password" style="font-weight: 600; font-size: 12px; margin-bottom: 6px; display: block; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.04em;"><?= tohtml(_("Password")) ?></label>
				<input type="password" class="form-control" name="password" id="password" autocomplete="current-password" required placeholder="••••••••••••" style="min-height: 42px; width: 100%; border-radius: 8px; background: #14141a; border: 1px solid rgba(255, 255, 255, 0.08); color: #f4f4f7; padding: 8px 14px; font-size: 13px;">
			</div>

			<?php if (!empty($_SESSION["login"]["username"])) { ?>
			<div style="margin-bottom: 18px;">
				<label for="twofa" style="font-weight: 600; font-size: 12px; margin-bottom: 6px; display: block; color: #a1a1aa;"><?= tohtml(_("2FA Code")) ?></label>
				<input type="text" class="form-control" name="twofa" id="twofa" autocomplete="one-time-code" placeholder="123456" style="min-height: 42px; width: 100%; border-radius: 8px; background: #14141a; border: 1px solid rgba(255, 255, 255, 0.08); color: #f4f4f7; padding: 8px 14px; font-size: 13px;">
			</div>
			<?php } ?>

			<button type="submit" class="button button-primary" style="width: 100%; min-height: 44px; border-radius: 8px; font-size: 14px; font-weight: 700; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border: 1px solid #6366f1; color: #ffffff; cursor: pointer; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35); display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s ease;">
				<i class="fas fa-right-to-bracket"></i><?= tohtml(_("Sign In")) ?>
			</button>
		</form>
	</div>
</div>
