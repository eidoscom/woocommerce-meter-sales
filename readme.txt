=== WooCommerce Meter Sales ===
Contributors: yourname
Tags: woocommerce, dtf, meter, linear, pricing, nesting
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.1
License: GPLv2 or later

Sell products by linear meter with DTF nesting calculator for WooCommerce.

== Description ==

WooCommerce Meter Sales adds a "Sell by meter" option to simple products. It is designed for DTF (Direct to Film) printing sales, where customers purchase film by the linear meter.

Key features:

* **Per-product activation** — Enable meter sales via a checkbox on any simple product.
* **Configurable film width** — Default 57 cm, adjustable per product for future needs.
* **Fixed + variable pricing** — Set a fixed base cost per order plus a price per linear meter.
* **Tiered pricing** — Configure price breaks: the more meters, the lower the price per meter (e.g. 1-5m: $15/m, 6-20m: $12/m, etc.).
* **Nesting calculator** — Customers upload an image (or enter dimensions manually) and specify how many copies they need. The plugin calculates the optimal arrangement on the film, trying both orientations (0° and 90°) to minimize waste.
* **Interactive preview** — A canvas shows the nesting layout directly on the product page.
* **Real-time pricing** — Total price updates instantly as the customer changes parameters.
* **Cart & order integration** — Meter data (image size, copies, film consumption, arrangement) is stored in cart items and order meta.

== Installation ==

1. Upload the `woocommerce-meter-sales` folder to `/wp-content/plugins/` or install via WordPress plugin upload.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Ensure WooCommerce is installed and active.

== Usage ==

=== Admin: configuring a product ===

1. Edit a WooCommerce **simple product**.
2. Check **"Sell by meter"** under the product's general settings.
3. Configure the meter sales fields:

   * **Film width (cm)** — the width of the printing film (default 57).
   * **Fixed base price ($)** — a one-time cost added to every order.
   * **Gap between copies (cm)** — spacing between each copy on the film (default 0.5).
   * **Waste percentage (%)** — extra material to account for borders and waste (default 5).
   * **Price tiers** — define from/to meter ranges and the price per meter for each range.

4. Save the product. The regular price is automatically set to "From: [lowest price]" for shop display.

=== Frontend: customer experience ===

On the product page, the standard quantity input is replaced by the DTF Meter Calculator:

1. Upload an image file, or enter image width and height in centimeters.
2. Enter the number of copies needed.
3. The calculator shows:

   * A visual canvas preview of how copies are arranged on the film.
   * Total meters consumed.
   * Arrangement details (copies across × rows).
   * Price breakdown (fixed base, rate per meter, total).

4. Click "Add to cart". The cart item displays image size, copies, film consumption, and the calculated price.

=== Cart and checkout ===

Each meter-sales product is a separate cart item with its own configuration. Order item meta stores:

* Image size (e.g. "10x15")
* Number of copies
* Film consumption in meters
* Arrangement (e.g. "3x4")
* Whether the image was rotated

== Frequently Asked Questions ==

= Can I sell non-DTF products with this? =

Yes. The "Sell by meter" feature works for any product sold by linear meter — fabrics, cables, films, etc. Adjust the film width and pricing accordingly.

= Can the customer add multiple different print jobs to the same cart? =

Yes. Each configuration (image size + copies) creates a unique cart item, so customers can add multiple jobs.

= Does this work with variable products? =

Currently only simple products are supported.

= Can I change the currency symbol? =

The plugin uses whatever currency is configured in WooCommerce settings.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
