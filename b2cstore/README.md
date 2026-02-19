# B2CStore – B2C Storefront Module for Dolibarr

**Module ID:** 580400
**Version:** 1.0.0
**Minimum Dolibarr:** 17.0
**Author:** Henaxis
**Licence:** GPL v3

---

## Overview

B2CStore is a fully self-contained, zero-dependency B2C storefront module for Dolibarr ERP.
It exposes a public-facing web store that allows end-customers to browse products, register an account, add items to a shopping cart, and place orders that appear directly in Dolibarr as DRAFT sales orders.

Key design goals:
- **No external dependencies** – pure PHP, vanilla JS, CSS custom properties.
- **Mobile-first responsive design** with CSS custom properties (`--b2cs-*`).
- **Configurable homepage** with modular sections (HERO, ABOUT, SERVICES, HISTORY, PRODUCTS_PREVIEW, CONTACT, B2B_LINK).
- **Guest browsing mode** – configurable; optional price hiding and cart login requirement.
- **Separate session namespace** – uses prefix `B2CSTORE_SESSID_` to avoid collisions with Dolibarr back-office or the b2border module.
- **CSRF protection** on every form via token stored in `$_SESSION['b2cstore_token']`.
- **Honeypot anti-spam** on registration and contact forms.

---

## Installation

1. Copy the `b2cstore/` folder to `<dolibarr_root>/custom/`.
2. Make sure all files are readable by the web server (`chown -R www-data:www-data b2cstore/`).
3. Log in to Dolibarr as administrator.
4. Go to **Home → Setup → Modules** and enable **B2C Storefront**.
5. The database table `llx_b2cstore_contact` is created automatically on activation.
6. Configure the module at **Home → Setup → Modules → B2C Storefront** or directly via the admin tabs.

---

## Configuration Constants

All configuration is stored in `llx_const` and managed via the admin panel.

### General settings

| Constant | Default | Description |
|---|---|---|
| `B2CSTORE_ENABLE_REGISTRATION` | `1` | Allow new customers to self-register |
| `B2CSTORE_REGISTRATION_APPROVAL` | `0` | Require admin approval before new accounts are active |
| `B2CSTORE_ALLOW_GUEST_BROWSING` | `1` | Allow catalog browsing without login |
| `B2CSTORE_HIDE_PRICES_GUEST` | `0` | Hide prices from non-authenticated visitors |
| `B2CSTORE_CART_REQUIRES_LOGIN` | `0` | Require login before adding items to cart |
| `B2CSTORE_REGISTRATION_FIELDS` | `firstname,lastname,email,password,phone,address,zip,town` | Comma-separated list of fields shown in the registration form |
| `B2CSTORE_DEFAULT_PRICE_LEVEL` | `1` | Price level from `llx_product_price` (1 = base) |
| `B2CSTORE_ITEMS_PER_PAGE` | `12` | Number of products per catalog page |
| `B2CSTORE_CUSTOMER_TYPENT_ID` | `0` | `llx_c_typent` rowid for auto-created customer companies (`0` = Dolibarr default) |
| `B2CSTORE_NOTIFICATION_EMAIL` | `` | Email address that receives new order notifications |
| `B2CSTORE_META_TITLE` | `` | HTML `<title>` tag for the storefront |
| `B2CSTORE_META_DESC` | `` | HTML meta description |

### Appearance settings

| Constant | Default | Description |
|---|---|---|
| `B2CSTORE_LOGO` | `` | Filename of uploaded logo (stored in `<dolibarr_data>/b2cstore/`) |
| `B2CSTORE_FAVICON` | `` | Filename of uploaded favicon |
| `B2CSTORE_COLOR_PRIMARY` | `#2563eb` | CSS `--b2cs-primary` |
| `B2CSTORE_COLOR_SECONDARY` | `#64748b` | CSS `--b2cs-secondary` |
| `B2CSTORE_COLOR_ACCENT` | `#f97316` | CSS `--b2cs-accent` |
| `B2CSTORE_COLOR_BG` | `#ffffff` | CSS `--b2cs-bg` |
| `B2CSTORE_COLOR_TEXT` | `#1a1a2e` | CSS `--b2cs-text` |
| `B2CSTORE_FONT_FAMILY` | `` | CSS font-family string |
| `B2CSTORE_FOOTER_TEXT` | `` | Footer text (HTML escaped) |
| `B2CSTORE_POWERED_BY` | `1` | Show "Powered by Dolibarr" in footer |
| `B2CSTORE_CUSTOM_CSS` | `` | Raw CSS injected into every page `<head>` |

### Section constants (per `{TYPE}`)

Each homepage section has a set of constants with the prefix `B2CSTORE_SECTION_{TYPE}_`.
Available types: `HERO`, `ABOUT`, `SERVICES`, `HISTORY`, `PRODUCTS_PREVIEW`, `CONTACT`, `B2B_LINK`.

| Suffix | Default | Description |
|---|---|---|
| `_ENABLED` | `0` | Show section on homepage |
| `_ORDER` | `50` | Sort order (ascending) |
| `_TITLE` | `` | Section heading |
| `_CONTENT` | `` | HTML content (admin-sanitized) |
| `_IMAGE` | `` | Image filename served via `getfile.php` |
| `_BG_COLOR` | `` | Inline background colour override |
| `_CSS_CLASS` | `` | Extra CSS class on `<section>` |

Additional constants for specific sections:

| Constant | Section | Description |
|---|---|---|
| `B2CSTORE_SECTION_HERO_CTA_LABEL` | HERO | Label for the CTA button |
| `B2CSTORE_SECTION_PRODUCTS_PREVIEW_COUNT` | PRODUCTS_PREVIEW | Number of featured products (default: 4) |
| `B2CSTORE_B2B_LINK_URL` | B2B_LINK | Override URL for B2B portal link |
| `B2CSTORE_B2B_LINK_BTN_TEXT` | B2B_LINK | Override button text |

---

## Product Catalog Filter

The catalog uses an allow-list approach.
Products are included if they pass **all** of the following:

1. `tosell = 1` (on sale).
2. `entity` matches the current Dolibarr entity.
3. If `B2CSTORE_ALLOWED_CATEGORIES` is set (comma-separated category IDs) **or** `B2CSTORE_ALLOWED_TAGS` is set (comma-separated tag IDs): product must belong to at least one of the listed categories **or** tags (OR logic).
4. If `B2CSTORE_EXCLUDE_PRODUCT_IDS` is set (comma-separated rowids): product is excluded.

---

## Authentication

Customers log in using credentials stored in `llx_societe_account` with `site = 'b2cstore_portal'`.

- Passwords are stored with `password_hash(PASSWORD_BCRYPT)`.
- At login, both `password_verify()` (bcrypt) and `dol_verifyHash()` (Dolibarr native) are attempted for compatibility.
- The internal `$user` object used for backend operations is loaded from the Dolibarr user specified by the `WEBPORTAL_USER_LOGGED` constant (same as Dolibarr's WebPortal module).

### Session

- Session name: `B2CSTORE_SESSID_` + `dol_getprefix('b2cstore')`.
- Session keys used:
  - `b2cstore_account_id` – rowid of authenticated `llx_societe_account`.
  - `b2cstore_token` – CSRF token (regenerated on login/logout).
  - `b2cstore_cart` – serialized cart array.
  - `b2cstore_redirect_after_login` – target controller to redirect after successful login.
  - `b2cstore_reg_attempts` – rate-limit counter for registration (max 3/hour per session).

---

## Shopping Cart

The cart is stored entirely in the PHP session (`$_SESSION['b2cstore_cart']`).
On every `getItems()` call, prices are **recalculated from the database** to prevent stale price exploits.

Cart item structure:

```
[
  'product_id'      => int,
  'qty'             => int,
  'unit_price_ht'   => float,   // recalculated
  'unit_price_ttc'  => float,   // recalculated
  'tva_tx'          => float,
  'subtotal_ht'     => float,
  'subtotal_ttc'    => float,
  'label'           => string,
  'ref'             => string,
  'photo'           => string,
]
```

---

## Order Creation

On checkout confirmation (`action=createorder`):

1. A `Commande` object is instantiated and pre-filled with:
   - `socid` = authenticated customer's `fk_soc`.
   - `module_source = 'b2cstore'`.
   - `statut = 0` (DRAFT).
   - Optional `note_public` from the checkout form.
2. `Commande::create($internalUser)` is called inside a DB transaction.
3. `Commande::addline()` is called for each cart item.
4. On success: cart is cleared, redirect to `controller=confirmation&id={order_id}`.
5. On failure: DB rollback, error message displayed.

---

## Customer Registration

`B2CStoreCustomer::register($data, $internalUser)`:

1. Validates required fields.
2. Checks `B2CSTORE_ENABLE_REGISTRATION` flag.
3. Rate-limits to 3 attempts per hour per session.
4. Checks email uniqueness against `llx_societe_account` (`site='b2cstore_portal'`).
5. Creates a `Societe` record (type from `B2CSTORE_CUSTOMER_TYPENT_ID`).
6. Creates a `SocieteAccount` record with `password_hash(PASSWORD_BCRYPT)`, `site='b2cstore_portal'`, `status=1` (or `0` if approval required).
7. Returns `['success'=>true, 'pending_approval'=>bool]`.

---

## Contact Form

Messages are saved to `llx_b2cstore_contact`.

| Column | Type | Description |
|---|---|---|
| `rowid` | INT PK | Auto-increment |
| `entity` | INT | Dolibarr entity |
| `datec` | DATETIME | Submission date |
| `name` | VARCHAR(255) | Sender name |
| `email` | VARCHAR(255) | Sender email |
| `phone` | VARCHAR(30) | Sender phone |
| `subject` | VARCHAR(255) | Message subject |
| `message` | TEXT | Message body |
| `ip` | VARCHAR(45) | Sender IP (IPv4/IPv6) |
| `status` | TINYINT | 0=new, 1=read, 2=replied |
| `fk_soc` | INT | FK to `llx_societe` if logged in |

---

## File Serving

### `public/getfile.php`

Serves logo, favicon, and section images.
Accessible without authentication.
Supports query params:
- `?f=filename` – file in the module data directory (`<dolibarr_data>/b2cstore/`).
- `?logo=1` – serves the file specified by `B2CSTORE_LOGO`.
- `?favicon=1` – serves the file specified by `B2CSTORE_FAVICON`.

Path traversal protection: `basename()` is applied to the filename before serving.

### `public/image.php`

Serves product images.
Accessible to guests if `B2CSTORE_ALLOW_GUEST_BROWSING = 1`, otherwise requires login.
Query params:
- `?id=product_id` – full-size product image.
- `?id=product_id&thumb=1` – thumbnail (from `thumbs/` subdirectory).

Validates the requested product against the catalog filter before serving.

---

## B2B Integration

If the `b2border` module is active, the `B2B_LINK` homepage section auto-populates the link URL to the b2border storefront.
The auto-detected URL can be overridden via `B2CSTORE_B2B_LINK_URL`.

---

## Admin Panel

Located in `admin/`:

| File | Description |
|---|---|
| `setup.php` | General configuration (registration, guest mode, price levels) |
| `appearance.php` | Logo/favicon upload, color pickers, fonts, footer, custom CSS |
| `sections.php` | Enable/disable/reorder homepage sections; inline content editor per section |
| `pages.php` | Edit title + HTML content for static pages (About, Services, History, Contact) |
| `downloadcss.php` | Download the bundled CSS file as a starter template |

---

## Routing

All requests go through `public/index.php`.
The `controller` GET parameter selects the active controller:

| Controller | Auth required | Description |
|---|---|---|
| `home` | No | Homepage with configured sections |
| `login` | No | Login form |
| `register` | No | Registration form |
| `catalog` | Configurable | Product catalog with search and pagination |
| `product` | Configurable | Single product detail |
| `cart` | Configurable | Cart view and item management |
| `checkout` | Yes | Order confirmation form |
| `confirmation` | Yes | Order success page |
| `contact` | No | Contact form (standalone page) |
| `page` | No | Static page renderer (About, Services, History) |

Controllers in `$guestControllers` are always accessible.
Catalog and product controllers additionally check `B2CSTORE_ALLOW_GUEST_BROWSING`.
Cart controller checks `B2CSTORE_CART_REQUIRES_LOGIN`.

---

## Security Notes

- All user output is escaped with `dol_escape_htmltag()`.
- All POST forms include a CSRF token verified via `B2CStoreContext::verifyToken()`.
- Registration and contact forms include a honeypot field (`website` / `company`) to block basic bots.
- SQL queries use `$db->escape()` for dynamic values.
- File serving endpoints apply `basename()` to prevent directory traversal.
- No shell commands or `eval()` are used anywhere.
- All prices are recomputed server-side on checkout; the client-side cart total is purely informational.

---

## Compatibility

| Component | Requirement |
|---|---|
| PHP | ≥ 7.4 |
| Dolibarr | ≥ 17.0 |
| MariaDB / MySQL | ≥ 10.3 |
| Browser | Any modern browser (ES5-compatible JS) |

The module is compatible with Dolibarr multi-entity and multi-price (`PRODUIT_MULTIPRICES`) configurations.

---

## Changelog

### 1.0.0 (2025)
- Initial release.
- Configurable homepage sections.
- Guest browsing with optional price/cart login requirement.
- Customer registration with optional admin approval.
- Full cart and checkout flow creating DRAFT Dolibarr orders.
- Contact form saved to `llx_b2cstore_contact`.
- Admin panel with 4 tabs: Setup, Appearance, Sections, Pages.
- Mobile-first CSS with CSS custom properties.
- Zero external JavaScript dependencies.
