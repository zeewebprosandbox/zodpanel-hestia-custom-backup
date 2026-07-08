# ZodPanel Hestia Custom Backup

Private source backup of the custom files currently deployed on the ZodPanel/Hestia VPS.

Source server:

- Hostname: `zodpanel.zodhost.com`
- Hestia root: `/usr/local/hestia`

Included scope:

- ZodPanel module UI and CSS
- WHMLab bridge API
- Hestia panel template changes
- phpMyAdmin SSO/open entrypoint changes
- Roundcube/webmail SSO open entrypoint and Dovecot master-user setup command
- Automated web/mail SSL sync and mail deliverability DNS repair
- Real-time ZodPanel SSL systemd automation installer
- Per-website terminal command runner for ZodPanel bridge actions
- File manager bulk-permission customizations
- Custom Hestia helper commands

Excluded scope:

- Hestia user account data
- sessions, logs, RRD metrics, SSL/private keys
- runtime tokens and generated credentials
- backups and database dumps

Restore note:

Copy files back to the same absolute paths under `/usr/local/hestia`, preserve executable mode for files under `/usr/local/hestia/bin`, then restart Hestia if panel PHP/template files are changed.

After restoring the SSL automation files, run:

```bash
/usr/local/hestia/bin/v-configure-zodpanel-ssl-automation 30
```

That installer also enables `zodpanel-mail-deliverability-sync`, which repairs MX, SPF, DKIM, DMARC, mail/webmail host records, and mail TLS for domains as Hestia data changes. To run it manually for one domain:

```bash
/usr/local/hestia/bin/zodpanel-mail-deliverability-sync --user USERNAME --domain example.com
```

Inbox placement still depends on provider-controlled PTR/rDNS, IP reputation, complaint rate, bounce rate, and message content. The automation handles the DNS/authentication/TLS side that ZodPanel can control.
