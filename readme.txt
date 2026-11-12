=== Codereyes Mobile Shop ===
Contributors: codereyes
Tags: mobile shop, pos, imei, phone repair, elementor
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Run a phone shop in store and online: IMEI stock, POS, installments, trade-ins, repairs, suppliers, cashiers and WooCommerce sync.

== Description ==

Codereyes Mobile Shop is a complete back office for mobile phone shops that sell at the counter and on their website.

**Stock**

* Phones are tracked one by one by IMEI (Luhn-checked, dual-SIM supported), with brand, model, storage, colour, condition grade and battery health.
* Accessories (chargers, cases, cables) are tracked by quantity with an optional SKU or barcode.
* A phone that is already in stock cannot be added twice by mistake.
* "Add another like this" copies a phone's model and prices, so adding five of the same model means scanning five IMEIs.
* Cost price and selling price for every item, with stock value on the dashboard.
* Photos for every item from your Media Library: a main photo and up to 12 more, shown in the stock list and on your website.
* Price stickers with a scannable barcode (the IMEI for phones, the SKU or an item code for accessories) on 38 × 25 mm or 50 × 30 mm label rolls or A4 sheets of 21.

**Suppliers and purchases**

* Record a whole delivery at once: scan the IMEIs of every phone of a model (one per line) and add accessories by quantity, or restock one you already sell.
* IMEIs are checked as you scan: wrong digits, the same phone scanned twice, or a phone already in stock are flagged before anything is saved.
* Pay the supplier now or later. Each supplier's account shows what you owe; payments settle the oldest deliveries first.
* Print price stickers for everything that came in, straight from the purchase.

**Point of sale (in store)**

* A full-screen till: a product grid with brand tabs, a colour and storage picker for each model, and scanning by IMEI, barcode or sticker.
* Keyboard shortcuts (F2 search, F8 pay, F9 hold, Esc close) and held sales to serve the next customer.
* Split one sale across cash, card, mobile banking (bKash, Nagad, Rocket) and bank transfer, with quick-cash buttons and the change worked out.
* Trade-ins: take the customer's old phone as part payment. It is checked, valued and added to stock as a used phone.
* Sell on credit or in installments (weekly or monthly). Dues shows who owes what, what is overdue, and lets you collect against the oldest sale first.
* Returns and exchanges: refund in cash or as store credit, with stock put back.
* Print a thermal receipt (80 mm or 58 mm roll) or a full A4 invoice for any sale, or send the receipt on WhatsApp.
* Two tills can never sell the same phone: each sale locks its stock.

**Cash register**

* Each cashier opens a shift with the cash in the drawer, records petty cash in and out, and closes by counting notes. The plugin works out what should be there and shows any shortage, then prints an end-of-shift report.

**WhatsApp**

* One tap opens WhatsApp with the message already written: receipts, repair updates, "ready to collect" alerts and payment reminders. You check it and press send; no API account or fees.
* Write the messages in Bangla or English in Settings. Local numbers get your country code (+880 by default).

**Staff**

* The "Shop Cashier" role sells, takes repairs, collects dues and runs their own cash register, but never sees cost prices, profit, suppliers or other people's takings, and cannot void, refund or change stock.
* Staff commission as a percentage of sales or of profit, with a report per person and each cashier's own figure on their dashboard.

**Repairs**

* Book in a repair with the customer, device, IMEI, fault, estimate and deposit.
* Every job gets a short ticket code (for example K7MQ2XRT) and a printable job ticket with a customer copy and a shop copy.
* Move jobs through Received, Diagnosing, Waiting for parts, Repairing, Ready to collect and Collected.
* Customers check progress on your website with the `[cmshop_repair_status]` shortcode, using their ticket code and the last 4 digits of their phone number.
* Customers can book a repair online with `[cmshop_repair_request]`. The job appears as "Booked online" with a ticket code, you get an email, and the customer gets the code by email too. Spam protection: a hidden trap field and a limit of 5 bookings an hour per visitor.

**On your website: blocks, Elementor widgets and shortcodes**

Every website part is available three ways: as a block in the block editor (in the "Mobile Shop" category), as an Elementor widget (in the "Mobile Shop" category), and as a shortcode. Blocks and widgets let you set the accent colour and corner radius; Elementor widgets also set card colours and heading typography.

* `[cmshop_stock]` shows the phones in your shop now: photo, price, new or used (with battery health), colours and storage, with brand and new/used filters. New phones of one model share one card. Cost prices and IMEIs are never shown. Options: `type="phone|accessory|all"`, `brand="Samsung"`, `condition="new|used"`, `per_page="12"`, `filters="no"`.
* `[cmshop_warranty]` lets a customer enter an IMEI to see when they bought the phone from you and whether it is still under warranty. Set the warranty for new and used phones in Settings. The buyer's name and the price are never shown.

**Online shop (WooCommerce, optional)**

* Publish any stock item to your WooCommerce shop in one click. The IMEI is never published.
* New phones of the same model become one product where customers choose storage and colour. Each option shows how many matching phones you have, and a paid order takes the phone of that colour that has been in stock longest (its IMEI is added to the order as a private note).
* Used phones are listed one by one with their condition and battery health, because no two are the same.
* Photos go to the website with the product. For a model, each colour's photo appears when the customer picks that colour.
* Brands sync with WooCommerce Brands: each product is filed under its brand (created the first time it is used), and the stock form suggests the brands you already have.
* When you sell a phone at the counter, it goes out of stock on the website straight away.
* Paid website orders are recorded as online sales and taken off shop stock. Cancelled or refunded orders put the stock back.
* Compatible with WooCommerce High-Performance Order Storage.

**Customers and reports**

* Customers are matched by phone number, with their full purchase and repair history.
* Dashboard with today's sales, profit and cash, a 30-day chart, how customers paid, best sellers, slow stock and a to-do list (repairs to call about, dues to collect, accessories to reorder, suppliers to pay).
* Export stock, sales, repairs and customers to CSV (opens correctly in Excel, including Bengali text and long IMEI numbers).

Bengali digits (০-৯) are accepted in IMEI, phone and price fields.

== Installation ==

1. Install and activate the plugin.
2. Go to **Mobile Shop → Settings** and enter your shop name, address and phone number for receipts.
3. Add your stock under **Mobile Shop → Stock**.
4. Ring up sales under **Mobile Shop → New sale (POS)**.
5. Optional: install WooCommerce to sell online, then open a stock item and click **Publish to website**.
6. Optional: add the shortcodes to pages: `[cmshop_stock]` (phones in stock), `[cmshop_warranty]` (warranty check), `[cmshop_repair_request]` (book a repair) and `[cmshop_repair_status]` (track a repair). **Mobile Shop → Settings** lists them all.

Administrators and WooCommerce Shop Managers can use every shop screen. Give counter staff the "Shop Cashier" role. Only administrators can change settings.

== Frequently Asked Questions ==

= Do I need WooCommerce? =

No. Stock, the point of sale, repairs, customers and reports all work without it. WooCommerce is only needed to sell online.

= Which printers are supported? =

Any printer your browser can print to. Thermal receipt printers with 80 mm or 58 mm rolls use the receipt format (set the width in Settings and choose no margins in the print dialog). Office and inkjet printers use the A4 invoice.

= Can I use a barcode scanner? =

Yes. Scanners that type the code and press Enter work in the POS search box and in the IMEI fields.

= What happens to my data if I delete the plugin? =

Your sales, stock and repair records are kept. To remove them, add `define( 'CMSHOP_DELETE_DATA', true );` to wp-config.php before deleting the plugin.

= I have the same phone in several colours. How does it show online? =

New phones are grouped by brand and model. For example, three Galaxy A15 128GB Black and two Blue become one "Samsung Galaxy A15" product with a Colour choice: Black (3 in stock) and Blue (2 in stock). New phones you add later join the listing on their own. Used phones keep one product each.

= Will publishing replace photos I added in WooCommerce? =

No. Photos are only sent when the stock item has them. A product whose stock item has no photo keeps the photos you added in WooCommerce.

= Do cashiers see my cost prices and profit? =

No. The Shop Cashier role never sees cost prices, profit, suppliers or the shop's total takings. Cashiers see their own sales and commission.

= Does WhatsApp sending cost anything? =

No. The buttons open WhatsApp (app or web) with the message written; you press send yourself. Nothing is sent without you.

= Can customers see my cost prices or IMEIs? =

No. Cost prices are only shown to shop staff, and IMEIs are never published to the website.

== Screenshots ==

1. Dashboard with today's sales, stock and the repair queue.
2. Point of sale with IMEI search and cart.
3. Thermal receipt and A4 invoice.
4. Adding a phone to stock.
5. One website product for a model, with colour and storage choices.
6. Repair job ticket.
7. Customer repair tracker on the website.

== Changelog ==

= 1.3.0 =
* New: full-screen point of sale with a product grid, model colour/storage picker, held sales, keyboard shortcuts, split payments and quick cash.
* New: dashboard with today's KPIs, a 30-day sales and profit chart, payment split, best sellers, slow stock and a to-do list.
* New: credit sales and weekly or monthly installment plans, with a Dues screen to collect payments.
* New: trade-ins as part payment; the phone joins stock as a used phone.
* New: returns and exchanges with refunds in cash or store credit.
* New: cash register shifts with petty cash, note counting and an end-of-shift report.
* New: price stickers with Code 128 barcodes for label rolls and A4 sheets.
* New: WhatsApp receipts, repair updates and payment reminders with editable Bangla or English templates.
* New: suppliers and bulk purchases with IMEI scanning, supplier dues and payments.
* New: Shop Cashier role that never sees costs or profit, and a staff commission report.
* Database update to 1.6.0 runs automatically.

= 1.2.0 =
* New: Gutenberg blocks for Phones in Stock, Warranty Check, Book a Repair and Repair Tracker, with live previews and colour, corner and column settings.
* New: Elementor widgets for the same four parts, with content options and a Style tab (accent, card colours, corner radius, heading typography).
* New: modern design for the website parts: layered cards, colour swatches, pill filters, progress steps for repairs, and styles that adapt to light and dark themes.
* New: refreshed admin design across the dashboard, point of sale, stock, sales, repairs, customers and settings.
* Shortcodes accept accent="#hex" and radius="16" too; [cmshop_stock] also accepts columns="3".

= 1.1.0 =
* New: `[cmshop_stock]` shortcode, a grid of phones (or accessories) in stock with brand and new/used filters.
* New: `[cmshop_warranty]` shortcode, a warranty check by IMEI, with warranty length for new and used phones in Settings.
* New: `[cmshop_repair_request]` shortcode, online repair booking with email notifications and a new "Booked online" repair status.
* Settings now list every shortcode with its options.

= 1.0.0 =
* First release: IMEI stock, point of sale, thermal receipts and A4 invoices, repairs with website tracker, customers, CSV export, item photos and WooCommerce stock and brand sync with one product per model for new phones.

== Upgrade Notice ==

= 1.3.0 =
A new full-screen POS and dashboard, dues and installments, trade-ins, returns, cash register shifts, price stickers, WhatsApp messages, suppliers and a cashier role. The database updates automatically.

= 1.2.0 =
Adds blocks and Elementor widgets, and a modern design for your website and admin.

= 1.1.0 =
Adds shortcodes for phones in stock, warranty checks and online repair booking.

= 1.0.0 =
First release.
