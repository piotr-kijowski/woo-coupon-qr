<?php
/**
 * Plugin Name: Woo Coupon QR
 * Description: Adds a QR link + QR image generator to WooCommerce coupons and applies coupon via URL then redirects to cart. Optionally embeds the site logo in the center of the QR (toggle per coupon).
 * Version: 1.4.0
 * Requires Plugins: woocommerce
 * Author:      Piotr Kijowski [piotr.kijowski@gmail.com]
 * Author URI:  https://github.com/piotr-kijowski
 * License:     GPL-3.0+
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('ABSPATH')) exit;

/**
 * Hard guard: if WooCommerce is not active, do nothing.
 * (WP 6.5+ will also block activation via "Requires Plugins", but this prevents fatals if Woo is deactivated later.)
 */
add_action('plugins_loaded', function () {
  if (!class_exists('WooCommerce')) return;

  /**
   * Admin CSS: ONLY on coupon edit screens.
   */
  add_action('admin_head', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || ($screen->post_type ?? '') !== 'shop_coupon') return;
    ?>
    <style>
      #pp_coupon_qr_box .ppqr-preview {
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        overflow: hidden;
        text-align: center;
        padding: 8px;
        background: #f6f7f7;
        border: 1px solid #dcdcde;
        border-radius: 6px;
      }
      #pp_coupon_qr_box .ppqr-preview img {
        display: block;
        max-width: 100%;
        height: auto;
        margin: 0 auto;
        border-radius: 6px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      }
    </style>
    <?php
  });

  /**
   * 1) Apply coupon via URL
   * Example: https://yoursite.com/?pp_apply_coupon=CODE
   * Optional redirect:
   *  - cart (default)
   *  - checkout: add &pp_redirect=checkout
   */
  add_action('template_redirect', function () {
    if (empty($_GET['pp_apply_coupon'])) return;

    $code = sanitize_text_field(wp_unslash($_GET['pp_apply_coupon']));
    if ($code === '') return;

    $coupon = new WC_Coupon($code);
    if (!$coupon || !$coupon->get_id()) {
      wp_safe_redirect(wc_get_cart_url());
      exit;
    }

    if (!WC()->session) {
      WC()->initialize_session();
    }
    if (!WC()->cart) {
      wc_load_cart();
    }

    $applied = WC()->cart->get_applied_coupons();
    $already = in_array(strtolower($code), array_map('strtolower', $applied), true);

    if (!$already) {
      WC()->cart->apply_coupon($code);
      WC()->cart->calculate_totals();
    }

    $redirect = (!empty($_GET['pp_redirect']) && sanitize_text_field(wp_unslash($_GET['pp_redirect'])) === 'checkout')
      ? wc_get_checkout_url()
      : wc_get_cart_url();

    wp_safe_redirect($redirect);
    exit;
  });

  /**
   * 2) Coupon admin metabox: QR link + Generate / Refresh QR (AJAX)
   */
  add_action('add_meta_boxes', function () {
    if (!current_user_can('manage_woocommerce')) return;

    add_meta_box(
      'pp_coupon_qr_box',
      'Coupon QR Code',
      'pp_coupon_qr_metabox_render',
      'shop_coupon',
      'side',
      'default'
    );
  });

  function pp_coupon_qr_metabox_render($post) {
    if (!current_user_can('manage_woocommerce')) return;

    $coupon_code      = (string) $post->post_title;
    $qr_url           = add_query_arg('pp_apply_coupon', rawurlencode($coupon_code), home_url('/'));
    $qr_attachment_id = (int) get_post_meta($post->ID, '_pp_coupon_qr_attachment_id', true);
    $last_error       = (string) get_post_meta($post->ID, '_pp_coupon_qr_last_error', true);

    // Site logo (Customizer)
    $logo_id = (int) get_theme_mod('custom_logo');

    // Per-coupon toggle (default ON if logo exists, otherwise OFF)
    $logo_enabled = get_post_meta($post->ID, '_ppqr_logo_enabled', true);
    if ($logo_enabled === '') {
      $logo_enabled = $logo_id ? '1' : '0';
    }
    $logo_enabled = ($logo_enabled === '1') ? '1' : '0';

    echo '<p><strong>QR Link</strong></p>';
    echo '<p><input type="text" style="width:100%;" readonly value="' . esc_attr($qr_url) . '"></p>';
    echo '<p style="margin:6px 0 0; font-size:12px; opacity:.85;">Redirect: cart by default. Add <code>&pp_redirect=checkout</code> for checkout.</p>';

    // Toggle
    echo '<p style="margin:10px 0 0;">
      <label style="display:flex; gap:8px; align-items:center;">
        <input type="checkbox" id="ppqr-logo-toggle" ' . checked($logo_enabled, '1', false) . ' ' . ($logo_id ? '' : 'disabled') . '>
        <span style="font-size:12px;">Embed site logo in QR</span>
      </label>
    </p>';

    if (!$logo_id) {
      echo '<p style="margin:6px 0 0; font-size:12px; opacity:.75;">Set a Site Logo in Appearance - Customize - Site Identity to enable logo embedding.</p>';
    }

    if ($last_error) {
      echo '<p style="color:#b32d2e; font-size:12px; margin:10px 0 0;"><strong>Last QR error:</strong><br>' . esc_html($last_error) . '</p>';
    }

    echo '<div id="ppqr-preview" class="ppqr-preview">';
    if ($qr_attachment_id) {
      $img = wp_get_attachment_image($qr_attachment_id, 'medium');
      if ($img) echo $img;
      $dl = wp_get_attachment_url($qr_attachment_id);
      if ($dl) {
        echo '<p style="margin:8px 0 0;"><a class="button" href="' . esc_url($dl) . '" target="_blank" rel="noopener">Open QR Image</a></p>';
      }
    } else {
      echo '<p style="margin:0; font-size:12px; opacity:.8;">No QR generated yet.</p>';
    }
    echo '</div>';

    $nonce = wp_create_nonce('ppqr_generate_' . $post->ID);

    echo '<p style="margin:10px 0 0;">
      <button type="button" class="button button-primary" id="ppqr-generate"
        data-coupon-id="' . (int) $post->ID . '"
        data-nonce="' . esc_attr($nonce) . '">Generate / Refresh QR</button>
    </p>';

    echo '<p id="ppqr-status" style="font-size:12px; opacity:.85; margin:8px 0 0;"></p>';
    ?>
    <script>
      (function(){
        const btn = document.getElementById('ppqr-generate');
        if (!btn) return;

        const status = document.getElementById('ppqr-status');
        const preview = document.getElementById('ppqr-preview');

        btn.addEventListener('click', async function(){
          const couponId = btn.getAttribute('data-coupon-id');
          const nonce = btn.getAttribute('data-nonce');
          const logoToggle = document.getElementById('ppqr-logo-toggle');

          status.textContent = 'Generating QR...';
          btn.disabled = true;

          try {
            const form = new FormData();
            form.append('action', 'ppqr_generate_coupon_qr');
            form.append('coupon_id', couponId);
            form.append('nonce', nonce);
            form.append('logo_enabled', (logoToggle && logoToggle.checked) ? '1' : '0');

            const res = await fetch(ajaxurl, { method: 'POST', body: form });
            const json = await res.json();

            if (!json || !json.success) {
              const msg = (json && json.data && json.data.message) ? json.data.message : 'Unknown error';
              throw new Error(msg);
            }

            status.textContent = 'QR generated ✅';
            const imgUrl = json.data.image_url + (json.data.image_url.includes('?') ? '&' : '?') + 't=' + Date.now();
            preview.innerHTML =
              '<img src="' + imgUrl + '" alt="Coupon QR" />' +
              '<p style="margin:8px 0 0;"><a class="button" href="' + json.data.image_url + '" target="_blank" rel="noopener">Open QR Image</a></p>';
          } catch (e) {
            status.textContent = 'Failed ❌: ' + e.message;
          } finally {
            btn.disabled = false;
          }
        });
      })();
    </script>
    <?php
    echo '<p style="margin:10px 0 0; font-size:12px; opacity:.85;">Tip: Use WooCommerce coupon restrictions (Products, Expiry, Usage limits) as normal.</p>';
  }

  /**
   * Helper: Add site logo to the middle of a QR PNG file
   * - Uses Customizer Site Logo (Appearance -> Customize -> Site Identity)
   * - Logo size ~20% of QR
   * - Adds white padding behind logo for scan reliability
   */
  function ppqr_add_logo_to_qr($qr_path) {
    if (!extension_loaded('gd')) return false;

    $logo_id = (int) get_theme_mod('custom_logo');
    if (!$logo_id) return false;

    $logo_path = get_attached_file($logo_id);
    if (!$logo_path || !file_exists($logo_path)) return false;

    $qr = @imagecreatefrompng($qr_path);
    if (!$qr) return false;

    $logo_info = @getimagesize($logo_path);
    if (!$logo_info) return false;

    switch ($logo_info['mime']) {
      case 'image/png':
        $logo = @imagecreatefrompng($logo_path);
        break;
      case 'image/jpeg':
        $logo = @imagecreatefromjpeg($logo_path);
        break;
      case 'image/webp':
        if (!function_exists('imagecreatefromwebp')) return false;
        $logo = @imagecreatefromwebp($logo_path);
        break;
      default:
        return false;
    }

    if (!$logo) return false;

    imagesavealpha($qr, true);
    imagealphablending($qr, true);

    $qr_w = imagesx($qr);

    $logo_size = (int) max(40, ($qr_w * 0.20));
    $padding   = (int) max(6, ($logo_size * 0.08));
    $bg_size   = $logo_size + ($padding * 2);

    $dst_x = (int)(($qr_w - $logo_size) / 2);
    $dst_y = (int)(($qr_w - $logo_size) / 2);

    $logo_w = imagesx($logo);
    $logo_h = imagesy($logo);

    $bg = imagecreatetruecolor($bg_size, $bg_size);
    $white = imagecolorallocate($bg, 255, 255, 255);
    imagefill($bg, 0, 0, $white);

    imagecopy($qr, $bg, $dst_x - $padding, $dst_y - $padding, 0, 0, $bg_size, $bg_size);

    imagecopyresampled($qr, $logo, $dst_x, $dst_y, 0, 0, $logo_size, $logo_size, $logo_w, $logo_h);

    $ok = imagepng($qr, $qr_path);

    imagedestroy($qr);
    imagedestroy($logo);
    imagedestroy($bg);

    return (bool) $ok;
  }

  /**
   * AJAX: generate QR -> save to uploads -> attach to coupon
   * Uses api.qrserver.com to create the QR PNG
   */
  add_action('wp_ajax_ppqr_generate_coupon_qr', function () {
    if (!current_user_can('manage_woocommerce')) {
      wp_send_json_error(['message' => 'Permission denied.'], 403);
    }

    $coupon_id = isset($_POST['coupon_id']) ? (int) $_POST['coupon_id'] : 0;
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

    if ($coupon_id <= 0 || get_post_type($coupon_id) !== 'shop_coupon') {
      wp_send_json_error(['message' => 'Invalid coupon.'], 400);
    }

    if (!wp_verify_nonce($nonce, 'ppqr_generate_' . $coupon_id)) {
      wp_send_json_error(['message' => 'Security check failed (nonce).'], 400);
    }

    // Save per-coupon toggle
    $logo_enabled = isset($_POST['logo_enabled']) ? sanitize_text_field(wp_unslash($_POST['logo_enabled'])) : '0';
    $logo_enabled = ($logo_enabled === '1') ? '1' : '0';
    update_post_meta($coupon_id, '_ppqr_logo_enabled', $logo_enabled);

    update_post_meta($coupon_id, '_pp_coupon_qr_last_error', '');

    $coupon_code = (string) get_post_field('post_title', $coupon_id);
    if ($coupon_code === '') {
      $msg = 'Coupon code/title is empty.';
      update_post_meta($coupon_id, '_pp_coupon_qr_last_error', $msg);
      wp_send_json_error(['message' => $msg], 400);
    }

    $qr_url = add_query_arg('pp_apply_coupon', rawurlencode($coupon_code), home_url('/'));

    $qr_png_url = add_query_arg([
      'data'   => $qr_url,
      'size'   => '700x700',
      'format' => 'png',
      'margin' => '10',
    ], 'https://api.qrserver.com/v1/create-qr-code/');

    $resp = wp_remote_get($qr_png_url, [
      'timeout' => 20,
      'headers' => [
        'Accept' => 'image/png',
        'User-Agent' => 'WordPress; ' . home_url('/'),
      ],
    ]);

    if (is_wp_error($resp)) {
      $msg = 'QR fetch failed: ' . $resp->get_error_message();
      update_post_meta($coupon_id, '_pp_coupon_qr_last_error', $msg);
      wp_send_json_error(['message' => $msg], 500);
    }

    $http = (int) wp_remote_retrieve_response_code($resp);
    $body = wp_remote_retrieve_body($resp);

    if ($http !== 200 || !$body) {
      $msg = 'QR fetch returned HTTP ' . $http . '.';
      update_post_meta($coupon_id, '_pp_coupon_qr_last_error', $msg);
      wp_send_json_error(['message' => $msg], 500);
    }

    // Optional: verify we actually got a PNG
    $ctype = wp_remote_retrieve_header($resp, 'content-type');
    if ($ctype && stripos($ctype, 'image/png') === false) {
      $msg = 'QR response was not a PNG (content-type: ' . $ctype . ').';
      update_post_meta($coupon_id, '_pp_coupon_qr_last_error', $msg);
      wp_send_json_error(['message' => $msg], 500);
    }

    $uploads = wp_upload_dir();
    if (!empty($uploads['error'])) {
      $msg = 'Upload dir error: ' . $uploads['error'];
      update_post_meta($coupon_id, '_pp_coupon_qr_last_error', $msg);
      wp_send_json_error(['message' => $msg], 500);
    }

    $filename = 'coupon-qr-' . sanitize_file_name($coupon_code) . '-' . time() . '.png';
    $filepath = trailingslashit($uploads['path']) . $filename;

    if (@file_put_contents($filepath, $body) === false) {
      $msg = 'Failed to write QR file to uploads. Check permissions.';
      update_post_meta($coupon_id, '_pp_coupon_qr_last_error', $msg);
      wp_send_json_error(['message' => $msg], 500);
    }

    // Add site logo in center (if enabled)
    if ($logo_enabled === '1') {
      ppqr_add_logo_to_qr($filepath);
    }

    $filetype = wp_check_filetype($filename, null);

    $attachment = [
      'post_mime_type' => $filetype['type'] ?: 'image/png',
      'post_title'     => 'QR - ' . $coupon_code,
      'post_content'   => '',
      'post_status'    => 'inherit',
    ];

    $attach_id = wp_insert_attachment($attachment, $filepath);
    if (is_wp_error($attach_id) || !$attach_id) {
      @unlink($filepath);
      $msg = 'wp_insert_attachment failed.';
      update_post_meta($coupon_id, '_pp_coupon_qr_last_error', $msg);
      wp_send_json_error(['message' => $msg], 500);
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attach_data = wp_generate_attachment_metadata($attach_id, $filepath);
    wp_update_attachment_metadata($attach_id, $attach_data);

    // Replace old attachment
    $old = (int) get_post_meta($coupon_id, '_pp_coupon_qr_attachment_id', true);
    if ($old && $old !== $attach_id) {
      wp_delete_attachment($old, true);
    }

    update_post_meta($coupon_id, '_pp_coupon_qr_attachment_id', (int) $attach_id);

    $image_url = wp_get_attachment_url($attach_id);
    if (!$image_url) {
      $image_url = trailingslashit($uploads['url']) . $filename;
    }

    wp_send_json_success(['image_url' => $image_url]);
  });
});
