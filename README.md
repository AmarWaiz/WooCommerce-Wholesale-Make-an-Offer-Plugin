# WooCommerce Wholesale & Make an Offer

A custom WordPress/WooCommerce plugin that adds:

1. **Role-based registration** — a styled front-end login/registration page with a Customer / Wholesale Customer dropdown. Retail accounts activate instantly; wholesale accounts are held for **admin approval**.
2. **Role-based pricing** — approved wholesale customers see a separate **wholesale price** per product (and per variation).
3. **"Make an Offer" negotiation** — approved wholesale customers propose a price on a product; the admin can **accept / counter / reject**, and the customer can **accept / reject / counter back** (bounded rounds). Accepted prices are applied securely at checkout.
4. **Admin dashboard** — pending approvals and an offers list table (sortable, filterable by status).
5. **Live color customizer** — change the plugin's brand colors from the settings screen; nothing is hard-coded.

---

## Installation

1. Copy the `wc-wholesale-offers` folder into `wp-content/plugins/`.
2. In **Plugins**, activate **WooCommerce Wholesale & Make an Offer** (WooCommerce must already be active).
3. On activation the plugin:
   - creates two database tables (`{prefix}_wwo_offers`, `{prefix}_wwo_offer_history`),
   - registers the **Wholesale Customer** role,
   - creates a page named **Account Access** containing the `[wwo_login_register]` shortcode,
   - schedules an hourly cron to expire accepted prices.

### Pages & shortcode

Put the login/registration form on any page with:

```
[wwo_login_register]
```

(The activation step already creates an *Account Access* page with this shortcode.)

The wholesale **"My Offers"** tab is added automatically to the WooCommerce **My Account** area.

---

## Configuration

**Wholesale & Offers → Settings**

- **Brand colors** — Primary / Secondary / Light / Accent. Uses the WordPress color picker with a **live preview** (updates CSS variables instantly); Save to apply site-wide.
- **Maximum negotiation rounds** — total proposals before only accept/reject remain *(default 3)*.
- **Accepted price expiry (hours)** — how long an accepted price stays redeemable *(default 48; 0 = never)*.
- **Offer rate limit** — N offers per time window per customer *(default 5 per hour; 0 disables)*.
- **Auto-approve wholesale** — skip manual review (off by default).
- **Notifications email** — where new-offer / approval emails are sent.

**Setting a wholesale price:** edit a product → **Product data → General** → *Wholesale price*. For variable products, set it per variation under each variation's pricing section.

---

## How it works

### Registration & approval
- Registering as **Customer** → role `customer`, logged in immediately.
- Registering as **Wholesale Customer** → role `wholesale_customer`, status `pending`, shows *"Your account is waiting for admin approval."*
- Admin approves/rejects under **Wholesale & Offers → Approvals**. On approval the customer is emailed and wholesale pricing/offers unlock.

### Negotiation flow (example)
Wholesale price **$10** → customer offers **$8** → admin counters **$9** → customer accepts → agreed price **$9** is applied to that product **for that customer** at checkout, and expires after 48 hours (one-time use).

### Default decisions (as implemented)
| Decision | Default |
|---|---|
| Back-and-forth counters | Allowed, up to **3 rounds** total (configurable) |
| Accepted price expiry | **48 hours** (configurable; 0 = never) |
| Reuse of accepted price | **One-time** (marked used after checkout) |
| Wholesale price source | **Per-product custom field** |

---

## Security

- **Nonces** on every form and AJAX request (`wwo_login`, `wwo_register`, `wwo_public`, `wwo_admin`).
- **Capability checks** on all admin/AJAX actions (`manage_wwo_offers`, `wwo_make_offer`).
- **Server-side validation & sanitisation** of all inputs; **all output escaped**.
- **Prepared statements** for every custom query; table names built only from `$wpdb->prefix`.
- **`ABSPATH` guard** at the top of every PHP file.
- **Rate limiting** on offer submissions.
- **Pricing is never trusted from the client** — the wholesale base price and the agreed price are recomputed/re-validated against the database on every cart recalculation and at checkout.

---

## File structure

```
wc-wholesale-offers/
├── wc-wholesale-offers.php      # Bootstrap, constants, activation hooks
├── uninstall.php               # Full data removal on delete
├── includes/                   # Core logic (roles, db, offers, pricing, ajax, …)
├── admin/                      # Admin menu, list tables, product fields
├── public/                     # Storefront controller + My Account endpoint
├── templates/                  # Overridable front-end templates
├── assets/css|js/              # Styles & scripts (color via CSS variables)
└── languages/                  # i18n (.pot)
```

### Template overrides
Copy any file from `templates/` to `your-theme/wc-wholesale-offers/<file>.php` to customise it.

---

## Notes & limitations

- **Login page design:** the included login/registration layout is a clean, palette-driven baseline. It is built to be fine-tuned to the supplied design image — the markup is in `templates/login-register.php` and all visuals use the brand CSS variables.
- **"Make an Offer" on variable products** is tracked at the parent-product level; set a parent-level wholesale price for the best experience. Simple products are fully supported out of the box.
- **Real-time updates** use AJAX polling (default every 15s) plus email on every status change. The poll interval is filterable via `wwo_poll_interval_ms`.
- Requires **WooCommerce 7.0+**, **WordPress 6.0+**, **PHP 7.4+**. HPOS-compatible.

---

## Developer hooks (selection)

- `wwo_offer_created`, `wwo_offer_countered` (`$offer, $actor`), `wwo_offer_accepted` (`$offer, $accepted_by`), `wwo_offer_rejected`, `wwo_offer_expired`
- `wwo_wholesale_registered` (`$user_id, $auto`), `wwo_wholesale_approved`, `wwo_wholesale_rejected`
- `wwo_email_html` (filter outgoing email HTML), `wwo_poll_interval_ms`
