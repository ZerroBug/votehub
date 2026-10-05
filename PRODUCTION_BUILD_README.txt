VOTEHUB CONSOLIDATED PRODUCTION BUILD
====================================

This package consolidates the current VoteHub production feature set:
- Event/category/contestant management
- Web voting
- Paystack Mobile Money payment flow
- Arkesel USSD callback and USSD voting engine
- Event-level Admin/VoteHub vs Client revenue split (30% default)
- Client cash-out ledger and available-balance calculation
- Live Results
- Transactions, reconciliation, reports, audit and settings
- Production dashboard styling
- Setup with Maintain Existing Database (default) or destructive Reset option

DATABASE SAFETY
---------------
Normal updates should preserve the existing database. Use the migration scripts only when the corresponding feature is being introduced and inspect/backup first.

The setup.php page provides:
1. Maintain Existing Database / normal installation behavior.
2. Fresh installation.
3. Reset Existing VoteHub Installation, requiring the exact phrase RESET VOTEHUB.

A reset permanently deletes VoteHub tables and data. Do not use it on a production database unless a full reset is intentionally required.

DEPLOYMENT
----------
1. Backup the production database and files.
2. Upload/merge the application files into public_html.
3. Preserve config/secrets.php and config/runtime.php if already configured.
4. Run only the necessary migrations.
5. Test login, event creation, web voting, Paystack, webhook/verification, USSD, live results and cash-outs.
6. Delete setup.php after installation/reset work is complete.
7. Never upload or expose real Paystack secret keys in source control.
