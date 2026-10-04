# VoteHub — Production Deployment Package

Production package for **https://votehubgh.org**.

## 1. Upload
Upload the contents of this package into the domain's document root (usually `public_html/`). Do not upload a second nested `votehub/` folder unless your domain is configured to use that path.

## 2. Create MySQL database
In cPanel → MySQL Databases, create a database and database user, then grant the user **ALL PRIVILEGES** to that database. Note the exact cPanel database name/user, which may be prefixed with your hosting account name.

## 3. HTTPS
Install/enable the SSL certificate for `votehubgh.org`. VoteHub redirects HTTP to HTTPS. Paystack API calls require HTTPS, and the webhook must be publicly reachable. Paystack recommends webhooks over relying on customer callbacks and signs webhook requests with HMAC-SHA512. citeturn0search0turn0search2

## 4. Run the installer
Open:
`https://votehubgh.org/setup.php`

Enter:
- MySQL host, port, database, username and password
- `https://votehubgh.org` as the application URL
- your Super Admin name/email/password
- Paystack **Live** secret key when going live, or Test key for staging

The installer creates the schema and writes `config/runtime.php` and `config/secrets.php`. It then creates `storage/install.lock`. Delete/disable `setup.php` after installation for extra protection.

## 5. Paystack production
Paystack uses separate test and live environments. Live secret keys begin with `sk_live_`; test keys begin with `sk_test_`. Secret keys must stay server-side. citeturn0search2turn0search3

For Ghana Mobile Money, Paystack's charge API accepts a GHS amount, customer email, phone number and provider such as MTN (`mtn`). Mobile Money is asynchronous, so configure the webhook below. citeturn0search6

Webhook URL:
`https://votehubgh.org/api/payment_webhook.php`

Configure it in the **Live** Paystack environment for production. Test and live webhook environments are separate. citeturn0search8

## 6. Go-live checks
1. Log in with the new Super Admin account.
2. Create a real event and categories.
3. Add contestants and verify their 4-digit codes.
4. Confirm category vote prices and transaction limits.
5. Test with Paystack Test mode first.
6. Confirm a successful payment creates exactly one vote.
7. Confirm failed/pending payments create zero votes.
8. Switch the installer/configuration to Live key only after the Paystack account is activated.
9. Configure the Live webhook URL.
10. Make a small real payment and confirm transaction + vote.

## 7. USSD gateway
The application includes `/api/ussd.php`. Your Ghana USSD aggregator must POST the gateway's session fields to that endpoint. The application does not itself provide a USSD short code; that is supplied by your telecom/USSD gateway provider.

## Security
- Never expose a Paystack secret key in JavaScript.
- Rotate any secret key that has been shared or committed.
- Keep `config/`, `database/`, `includes/` and `storage/` blocked from direct HTTP access.
- Keep the site on HTTPS.
- Do not leave `setup.php` publicly usable after installation.
- Back up the database regularly.
