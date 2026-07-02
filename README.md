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
- File manager bulk-permission customizations
- Custom Hestia helper commands

Excluded scope:

- Hestia user account data
- sessions, logs, RRD metrics, SSL/private keys
- runtime tokens and generated credentials
- backups and database dumps

Restore note:

Copy files back to the same absolute paths under `/usr/local/hestia`, preserve executable mode for files under `/usr/local/hestia/bin`, then restart Hestia if panel PHP/template files are changed.
