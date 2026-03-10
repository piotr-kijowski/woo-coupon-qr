# Woo Coupon QR

Contributors: piotr.kijowski  
Tags: woocommerce, coupons, qr-code, discounts, marketing  
Requires at least: 6.5  
Tested up to: 6.9  
Requires PHP: 7.4  
Requires Plugins: woocommerce  
Stable tag: 1.4.0  
License: GPLv3 or later  
License URI: https://www.gnu.org/licenses/gpl-3.0.html  

*Generate branded QR codes for WooCommerce coupons and apply discounts automatically when scanned.*

## Description

Woo Coupon QR extends WooCommerce’s built-in coupon system by adding QR code support directly to coupons.

Each coupon can generate a unique QR code that, when scanned:

- Applies the coupon automatically
- Redirects the customer to the cart or checkout
- Optionally embeds your site logo in the center of the QR code

This plugin does NOT replace WooCommerce coupons. It builds on top of them, using all existing WooCommerce rules such as:

- Product restrictions
- Expiry dates
- Usage limits
- Individual use rules

Perfect for:

- In-store promotions
- Printed flyers or packaging
- Event discounts
- QR-based marketing campaigns

## Features

- QR code generation directly on WooCommerce coupons
- Automatic coupon application via QR scan
- Optional redirect to cart or checkout
- Per-coupon toggle to embed site logo in the QR
- QR images stored in the WordPress Media Library
- Uses the active Site Logo from the Customizer
- Secure AJAX-based generation (Gutenberg-safe)
- Fully compatible with WooCommerce coupon rules
- Native WordPress dependency handling (Requires Plugins)

## Installation

1. Upload the plugin folder to:


`/wp-content/plugins/pp-woo-coupon-qr/`


2. Activate the plugin through the **Plugins** menu in WordPress.

3. Ensure WooCommerce is installed and activated.

## Usage

1. Go to **WooCommerce → Marketing → Coupons**
2. Create or edit a coupon
3. In the **Coupon QR Code** box:
   - Copy the QR link
   - Toggle logo embedding on or off
   - Click **Generate / Refresh QR**
4. Download or use the generated QR image

Scanning the QR code will:

- Apply the coupon automatically
- Redirect to the cart by default  
  (Add `&pp_redirect=checkout` to the URL for checkout redirect)

## Requirements

- WordPress 6.5 or newer
- WooCommerce installed and activated
- PHP 7.4+
- PHP GD extension (required only for logo embedding)

## FAQ

### Does this replace WooCommerce coupons?

No. It extends WooCommerce’s existing coupon system.

### Can I restrict coupons to specific products?

Yes. Use WooCommerce’s built-in coupon restrictions.

### What happens if WooCommerce is disabled?

The plugin will not run. WordPress will prevent activation if WooCommerce is missing.

### Is the logo required in QR codes?

No. Logo embedding can be toggled per coupon and requires a Site Logo to be set.

### Where are QR images stored?

QR codes are saved as PNG files in the WordPress Media Library.

### Does this work with Gutenberg?

Yes. QR generation uses AJAX and does not rely on post save hooks.

## Changelog

### 1.4.0

- Professional hard guard for WooCommerce dependency
- Scoped admin CSS to coupon edit screens only
- Per-coupon logo embed toggle
- QR image content-type validation
- Improved security and error handling
- General code cleanup and hardening

### 1.3.0

- Added logo embedding with Site Logo
- Added per-coupon toggle
- Improved admin UI and preview

### 1.2.0

- AJAX-based QR generation
- Stored QR images in Media Library
- Added QR preview in coupon editor

### 1.1.0

- Reliable QR generation using external API
- Improved error reporting

### 1.0.0

- Initial release

## Upgrade Notice

### 1.4.0

Recommended upgrade. Improves stability, security, and admin UI behavior.

## License

This plugin is licensed under the GPL v3 or later.