<?php
$error = $error ?? '';
?>
<div class="login">
	<a href="/" class="u-block u-mb30" style="text-align: center;">
		<img src="/images/logo.svg" alt="ZodPanel" width="80" height="96" style="margin: 0 auto;">
	</a>
	<form id="login-form" method="post" action="/login/">
		<input type="hidden" name="token" value="<?= tohtml($_SESSION["token"]) ?>">
		<h1 class="login-title" style="text-align: center; margin-bottom: 24px;">
			<?= tohtml(sprintf(_("Sign in to %s"), $_SESSION["APP_NAME"] ?? 'ZodPanel')) ?>
		</h1>
		<?php if (!empty($error)) { ?>
			<div class="error-message" style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 600; text-align: center;">
				<?= tohtml($error) ?>
			</div>
		<?php } ?>
		<div class="u-mb20">
			<label for="username" class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block; color: var(--zod-text-muted, #a1a1aa);"><?= tohtml(_("Username")) ?></label>
			<input type="text" class="form-control" name="user" id="username" autocomplete="username" value="<?= tohtml($_POST['user'] ?? '') ?>" required autofocus style="min-height: 46px; border-radius: 8px;">
		</div>
		<div class="u-mb20">
			<label for="password" class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block; color: var(--zod-text-muted, #a1a1aa);"><?= tohtml(_("Password")) ?></label>
			<input type="password" class="form-control" name="password" id="password" autocomplete="current-password" required style="min-height: 46px; border-radius: 8px;">
		</div>
		<?php if (!empty($_SESSION["login"]["username"])) { ?>
		<div class="u-mb20">
			<label for="twofa" class="form-label" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block; color: var(--zod-text-muted, #a1a1aa);"><?= tohtml(_("2FA Token (Optional)")) ?></label>
			<input type="text" class="form-control" name="twofa" id="twofa" autocomplete="one-time-code" placeholder="123456" style="min-height: 46px; border-radius: 8px;">
		</div>
		<?php } ?>
		<button type="submit" class="button button-primary" style="width: 100%; min-height: 48px; border-radius: 8px; font-size: 15px; font-weight: 700; margin-top: 10px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border: 1px solid #6366f1; color: #ffffff; cursor: pointer; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);">
			<i class="fas fa-right-to-bracket" style="margin-right: 8px;"></i><?= tohtml(_("Sign In")) ?>
		</button>
	</form>
</div>
