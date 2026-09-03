# Super-admin deployment setup

## Dedicated account transition

1. Upload the release and sign back into the existing administrator account. Existing sessions are intentionally refreshed once for the new session-security version.
2. Register the new dedicated account through the normal app registration and complete onboarding.
3. Open **Settings → Super Admin → Admin Console**, search for the dedicated account, and promote it.
4. Sign into the dedicated account and confirm that the Admin Console opens.
5. Find the personal account from the dedicated account and remove its super-admin role. The app will not allow the final super admin to be removed.

## SMTP password-reset delivery

The private Hostinger SMTP configuration is stored in `challenge/config/mail.php` and sends from `unfiltered@unmaskedculture.org` (display name: Kinto) over SSL on port 465.

After deployment, open a user in the Admin Console and send a reset email to verify delivery.
The same SMTP mailbox sends the welcome email automatically after each successful registration.

`mail.php` is intentionally excluded from version control. Never commit SMTP credentials.
