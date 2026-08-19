# Magenx_Gdpr

Cookie registry, consent logging and data-subject request tracking for a
headless Magento 2 storefront (composer package `magenxcommerce/module-gdpr`).

## Why this module exists

The storefront needs to show visitors the cookies the site *actually* sets
(not a generic guess), record what they consented to, and give customers a
self-service way to exercise GDPR rights - export, anonymize, or erase their
data - with an admin view over that history. There is no Luma frontend here:
the storefront is a separate Next.js app. `view/adminhtml` exists only for
the merchant-facing cookie registry and request queue, which have no
storefront equivalent.

This module owns the data, admin UI and cron. Its GraphQL surface lives in
the companion [`magenxcommerce/module-gdpr-graph-ql`](https://github.com/magenxcommerce/module-gdpr-graph-ql)
module - the usual Magenx `<Name>` / `<Name>GraphQl` split - so this module
has no `Magento_GraphQl` dependency of its own.

## What it does

- **Cookie registry** - admin-managed cookie groups (`necessary` /
  `analytics` / `marketing` / `preferences`, matching the storefront's own
  existing consent categories) and the individual cookies in each. Seeded on
  install with the cookies this app actually sets (`magenx_ct`, `magenx_auth`,
  the Auth.js broker session/CSRF cookies) plus the Google Analytics cookies,
  inactive by default until GTM/GA is actually configured. Exposed as
  `gdprCookieGroups` (see GraphQL surface below) for the storefront's cookie
  policy page.
- **Consent logging** - `submitCookieConsent` records every cookie-banner
  decision (customer id when signed in, IP, which categories were granted)
  for an audit trail. Guest-usable, since most consent decisions happen
  before sign-in.
- **Data-subject requests** - `requestGdprAction` lets a signed-in customer
  anonymize their own data immediately, or request erasure (queued for admin
  approval, since it's the most destructive option). `myPersonalDataExport`
  returns the customer's own profile/addresses/orders/consent history inline
  for a client-side download, and logs a completed export request.
  `myGdprRequests` lists a customer's own request history.
- **Retention automation** (all off by default except the log prune) -
  optional cron to anonymize dormant accounts, optional cron to anonymize old
  order addresses, and an always-on cron that prunes the consent log past its
  retention window. "Dormant" means no sign-in (`customer_log.last_login_at`)
  and no order within the window, not merely an old account. Each anonymization
  cron works through at most 200 records per run, so a backlog drains over
  successive nights; the old-order cron tracks its position with a persisted
  cursor so each run picks up where the last one stopped.

## Admin

**Stores > Settings > Configuration > Magenx > GDPR** - master enable,
consent log retention, and the two anonymization automations (each off by
default; read the in-admin warning before enabling).

The master enable is scoped per store view and is honoured by every GraphQL
entry point. The three retention groups below it are default-scope only,
because the cron jobs they drive read config without a store id - showing them
per website would promise scoping the crons cannot deliver.

**Customers > GDPR**:
- *Cookie Groups* / *Cookies* - manage the registry that feeds the storefront
  cookie policy page. If a third-party script sets its own cookie, add it
  here so it shows up accurately.
- *Consent Log* - read-only audit trail of cookie-banner decisions.
- *Data Requests* - customer export/anonymize/erase requests; Approve/Deny
  actions appear only on pending (erase) rows.

## GraphQL surface

Exposed by the companion [`magenxcommerce/module-gdpr-graph-ql`](https://github.com/magenxcommerce/module-gdpr-graph-ql)
module: root `Query`/`Mutation` fields (not nested under `Customer`) -
`gdprCookieGroups` (public, cacheable), `submitCookieConsent` (guest or
customer), `myGdprRequests` / `myPersonalDataExport` / `requestGdprAction`
(customer-only). Install that module alongside this one to expose these
over GraphQL.

## CLI

```
bin/magento magenx:gdpr:seed-cookies
```

Idempotently (re-)installs the default cookie-group/cookie rows by their
natural key (`code` / `name`). Safe to re-run; rows for codes/names outside
the default set are untouched.

## Install

```
composer require magenxcommerce/module-gdpr
bin/magento module:enable Magenx_Gdpr
bin/magento setup:upgrade
bin/magento cache:clean
```

Add `magenxcommerce/module-gdpr-graph-ql` as well to expose the GraphQL
surface described below.

## What anonymization actually touches

`Model/Anonymizer` is the single place PII is scrubbed, used identically by the
self-service `anonymize_data` mutation and the admin-approved erase flow. For
one customer it rewrites:

- `customer_entity` - name, email, date of birth, gender, tax/VAT number
- `customer_address_entity` - name, street, city, postcode, phone, company,
  fax, VAT id on every address
- `sales_order` - the denormalized `customer_email` / `customer_firstname` /
  `customer_lastname` / `customer_middlename` / `customer_dob` /
  `customer_taxvat` columns on every order they placed
- `sales_order_address` - name, street, city, postcode, phone, email, company,
  fax, VAT id on both billing and shipping
- `sales_order_grid` - the flat table the admin order grid reads, so the old
  name and email are not still one grid search away
- `newsletter_subscriber` - the stored address, keeping the subscription row so
  an unsubscribe is not silently reset

It then revokes the customer's access tokens, so an erased account is not left
signed in on every device it was signed in on.

Region is left alone in both paths: a state or province is too coarse to
identify anyone, and Magento's customer `AddressInterface::setRegion` takes a
`RegionInterface` rather than null.

Order totals, items and financial history are never touched - those are the
merchant's accounting records, not the customer's personal data.

## Verification status

Checked without a Magento installation available: every PHP file passes
`php -l`, every XML file parses, and `composer.json` /
`db_schema_whitelist.json` are valid JSON. **Not yet verified**:
`setup:upgrade` against a real database and the admin grids/forms rendering in
a browser. Do that before shipping to production.

If you enable the old-order cron on an existing store, confirm it is making
progress: run `bin/magento cron:run --group=default` twice and check that the
second run anonymizes a *different* set of orders than the first.

## Known limitations / follow-ups

- `requestGdprAction`'s "erase" is PII anonymization + admin approval, not a
  hard delete - Magento customer/order records can't be dropped without
  breaking order history, so anonymization is the erasure mechanism (see
  `Model/Anonymizer.php`).
- Anonymization covers this module's tables and Magento's core customer/sales
  tables. It does not reach into third-party modules that hold personal data of
  their own; a store running those has to extend `Model/Anonymizer` for erasure
  to be complete.
- Each anonymization cron processes up to 200 records per run; a large backlog
  is worked off gradually across daily runs rather than all at once.
- No store-view scoping on the cookie registry - this app runs one storefront
  across locale routes, so a global registry was simpler than an unused scope
  dimension. Revisit if a genuinely separate storefront is added later.
- `admin_note` is set automatically when an admin approves or denies from the
  grid, or from a `note` request parameter. There is no admin form for typing a
  custom note yet.
