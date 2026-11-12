=== Codereyes Mobile Shop ===
Contributors: codereyes
Tags: mobile shop, pos, imei, phone repair, inventory
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Run a mobile phone shop in store and online: IMEI stock, point of sale, receipts, repair tickets with a status tracker, and WooCommerce stock sync.

== Description ==

Codereyes Mobile Shop is a complete back office for mobile phone shops that sell at the counter and on their website.

**Stock**

* Phones are tracked one by one by IMEI (Luhn-checked, dual-SIM supported), with brand, model, storage, colour, condition grade and battery health.
* Accessories (chargers, cases, cables) are tracked by quantity with an optional SKU or barcode.
* A phone that is already in stock cannot be added twice by mistake.
* Cost price and selling price for every item, with stock value on the dashboard.

**Point of sale (in store)**

* Scan an IMEI or barcode, or type a name, and press Enter to add it to the cart.
* Change prices at the counter, add a discount, take part payments and see the change due.
* Cash, card, mobile banking (bKash, Nagad, Rocket) and bank transfer.
* Print a thermal receipt (80 mm or 58 mm roll) or a full A4 invoice for any sale, with your shop name, address and warranty note. Choose the default in Settings and switch on any sale.
* Void a sale (returns) and its items go back into stock.
* Two tills can never sell the same phone: each sale locks its stock.

**Repairs**

* Book in a repair with the customer, device, IMEI, fault, estimate and deposit.
* Every job gets a short ticket code (for example K7MQ2XRT) and a printable job ticket with a customer copy and a shop copy.
* Move jobs through Received, Diagnosing, Waiting for parts, Repairing, Ready to collect and Collected.
* Customers check progress on your website with the `[cmshop_repair_status]` shortcode, using their ticket code and the last 4 digits of their phone number.

**Online shop (WooCommerce, optional)**

* Publish any stock item to your WooCommerce shop in one click. Brand, storage, colour, condition and battery health are added as product details. The IMEI is never published.
* When you sell a phone at the counter, it goes out of stock on the website straight away.
* Paid website orders are recorded as online sales and taken off shop stock. Cancelled or refunded orders put the stock back.
* Compatible with WooCommerce High-Performance Order Storage.

**Customers and reports**

* Customers are matched by phone number, with their full purchase and repair history.
* Dashboard with today's and this month's sales and profit, stock count and value, low-stock accessories and the repair queue.
* Export stock, sales, repairs and customers to CSV (opens correctly in Excel, including Bengali text and long IMEI numbers).

Bengali digits (০-৯) are accepted in IMEI, phone and price fields.

== Installation ==

1. Install and activate the plugin.
2. Go to **Mobile Shop → Settings** and enter your shop name, address and phone number for receipts.
3. Add your stock under **Mobile Shop → Stock**.
4. Ring up sales under **Mobile Shop → New sale (POS)**.
5. Optional: install WooCommerce to sell online, then open a stock item and click **Publish to website**.
6. Optional: add `[cmshop_repair_status]` to a page so customers can track repairs.

Administrators and WooCommerce Shop Managers can use the shop screens. Only administrators can change settings.

== Frequently Asked Questions ==

= Do I need WooCommerce? =

No. Stock, the point of sale, repairs, customers and reports all work without it. WooCommerce is only needed to sell online.

= Which printers are supported? =

Any printer your browser can print to. Thermal receipt printers with 80 mm or 58 mm rolls use the receipt format (set the width in Settings and choose no margins in the print dialog). Office and inkjet printers use the A4 invoice.

= Can I use a barcode scanner? =

Yes. Scanners that type the code and press Enter work in the POS search box and in the IMEI fields.

= What happens to my data if I delete the plugin? =

Your sales, stock and repair records are kept. To remove them, add `define( 'CMSHOP_DELETE_DATA', true );` to wp-config.php before deleting the plugin.

= Can customers see my cost prices or IMEIs? =

No. Cost prices are only shown to shop staff, and IMEIs are never published to the website.

== Screenshots ==

1. Dashboard with today's sales, stock and the repair queue.
2. Point of sale with IMEI search and cart.
3. Thermal receipt and A4 invoice.
4. Adding a phone to stock.
5. Repair job ticket.
6. Customer repair tracker on the website.

== Changelog ==

= 1.0.0 =
* First release: IMEI stock, point of sale, thermal receipts and A4 invoices, repairs with website tracker, customers, CSV export and WooCommerce stock sync.

== Upgrade Notice ==

= 1.0.0 =
First release.
