# VoteHub Ghana — Full Production Application

VoteHub is an event-neutral paid voting platform for USSD and web administration.

## Included
- Multi-event architecture with strict event/category/contestant isolation
- Super Admin authentication
- Event CRUD
- Category CRUD with automatic category codes
- Contestant management with automatic 4-digit voting codes
- Live results
- Transaction management
- Payment/vote reconciliation
- Paystack Mobile Money integration
- Paystack webhook verification and duplicate webhook protection
- Arkesel USSD JSON callback adapter
- Browser Live USSD Bot Simulator
- USSD session monitor
- Administrator management
- Audit log viewer
- System health/settings page
- Production database installer and migrations
- HTTPS/security headers

## Core URLs
- Website: https://votehubgh.org
- Login: https://votehubgh.org/auth/login.php
- Arkesel callback: https://votehubgh.org/api/ussd_arkesel.php
- Paystack webhook: https://votehubgh.org/api/payment_webhook.php
- Live USSD simulator: https://votehubgh.org/admin/ussd-simulator.php

## Installation
1. Upload all files to `public_html`.
2. Create a MySQL/MariaDB database and user in Hostinger.
3. Open `/setup.php`.
4. Enter the Hostinger database credentials.
5. Use `https://votehubgh.org` as the application URL.
6. Use Paystack Test mode during testing.
7. Create the Super Admin.
8. If this is a clean database, select Fresh Installation.
9. After successful installation, verify `storage/install.lock` exists and remove/disable public access to `setup.php`.

## Existing installation upgrade
Do not select Fresh Installation when real data exists. Upload the new files and run:
`https://votehubgh.org/migrate_production_hardening.php`
while logged in as Super Admin.

## Arkesel
Configure the Arkesel USSD callback to:
`https://votehubgh.org/api/ussd_arkesel.php`
Keep Paystack in Test mode until USSD, payment and vote reconciliation have passed end-to-end tests.

## Production payment rule
A vote is never created merely because a payment was initiated. A vote is created only after Paystack confirms a successful payment and the server validates the amount and transaction state.

## Payment reconciliation
The Transactions page now has **Verify with Paystack** for Pending transactions. It calls Paystack's server-side Verify Transaction API, checks reference/currency/amount, and fulfills a successful transaction exactly once. The Paystack webhook also verifies the transaction before fulfillment and can retry an unprocessed webhook event.
