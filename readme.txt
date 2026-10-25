=== GlocalSaino Automations Based on Triggers ===
Contributors: glocalsaino, rafammoo
Tags: automation, webhook, contact form 7, csv import, data table
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

No-code automations: forms, files, links, buttons, widgets, webhooks or sign-ups trigger emails, webhooks, tables, calculations and redirects.

== Description ==

**GlocalSaino Automations Based on Triggers** lets you build automations without writing code. Each automation has a **trigger** (the event that starts it), optional **conditions**, and one or more **actions** that run when the trigger fires. Everything is free: no license key, no locked features, no usage caps.

**Documentation:** step-by-step guides for every trigger, condition and action, shortcode reference and FAQ at [glocalsaino.com/automationsbasedontriggers/documentacion](https://glocalsaino.com/automationsbasedontriggers/documentacion/).

= Triggers =

* **Click on a link** – the plugin gives you a tracked link (and a shortcode). Each click runs the automation and takes the visitor to the destination you set in the back end. Values added to the link (e.g. `&email=…`) become fields.
* **Click on a button** – insert a button with a shortcode; its text, style, message after the click and destination are set in the back end.
* **WordPress user events** – a user registers, logs in or changes their password.
* **CSV or Excel file uploaded** – upload a CSV (any separator, any common encoding) or Excel .xlsx file from the back end, or let your users upload it from the website with the [glocalsaino_aat_upload] shortcode (logged-in users, specific roles or anyone, with anti-spam). Run the automation once for the whole file or once for each row. Files are read and discarded, never stored.
* **Embeddable widget on other websites** – a button, link or form with fields that other websites show by pasting a small piece of code. When a visitor uses it there, the automation runs on your site.
* **Contact Form 7 submission** – fire when a specific form is submitted, or only when a specific field of that form is submitted with a specific value (or with any value).
* **Data received through a webhook** – the plugin generates a unique webhook URL for the automation. Define the keys you expect, and the values the external system sends under those keys (as query string, form body or JSON body, via GET, POST or PUT) become available to the actions.

= Tables =

Create tables under Automations → Tables: give them columns (text, number, date, link or email), or read the columns from a CSV / Excel file. Show them anywhere with the [glocalsaino_aat_table id="…"] shortcode, with search, sorting by column, pagination and an optional CSV download. Choose who can see each table: everyone, logged-in users, specific roles or only administrators. Browse, export or delete the rows from the back end. Exported values are protected against spreadsheet formula injection.

= Embeddable widget for other websites =

Give a partner, client or another site of yours a widget instead of a webhook URL:

* **Button, link or form** with text, email, number, phone, long text, drop-down, checkbox and privacy-consent fields, validated on your server.
* **Your design**: colors, font and corner radius, plus, optionally, your site's own Additional CSS (written in WordPress, not in the plugin). It lives in its own iframe, so the other site's styles never break it.
* **Easy to paste**: a one-line script that adjusts the height automatically and can take the visitor to another page after sending, or a plain iframe where scripts are not allowed.
* **Protected**: only the websites you allow can show it; every submission needs a single-use code issued when the widget loads; minimum time to send, hidden anti-spam field, per-connection hourly limit and an optional hCaptcha. Rejected submissions are logged with the reason.
* **Statistics** per website: views, submissions, conversion and rejected submissions over the last 30 days, plus a live preview in the editor.

= Webhook security =

The webhook URL contains a random key, and you can add up to three extra protections, all free:

* **Authentication token in a header** – you generate a token and give it to the sender together with the URL; calls without it are rejected (`Authorization: Bearer …` is supported too).
* **IP allowlist** – only accept calls from specific IPv4 / IPv6 addresses or CIDR ranges. Works behind proxies and Cloudflare (configurable in Settings).
* **HMAC signature verification** – ready-made presets for **Stripe**, **Shopify**, **WooCommerce** and **GitHub**, plus a custom mode (header, SHA-256 / SHA-512 / SHA-1, hex or Base64, optional timestamp). Timestamped signatures also reject replayed calls.

Every rejected call is recorded in Webhook Logs with its error code and reason.

= Conditions =

* **Field conditions** – compare any trigger value: equals, contains, starts / ends with, is empty, greater / less than…
* **WordPress user conditions** – the visitor has (or has not) logged in; the user is a registered user; the user is a specific registered user (searchable list). The user can be the logged-in visitor, or the user whose email or username was sent in a field.

Combine them with "all the conditions" or "any of the conditions".

= Actions =

* **Calculator** – write operations with the trigger values or your own numbers, e.g. `({{price}} * {{quantity}}) * 1.21`, with rounding and functions (round, min, max, abs…). The result can be used by the next actions, e.g. in an email.
* **Format data** – first or last word, first letter, initials, upper / lower case, shorten, extract or replace text, keep only digits, round or format a number (1.234,56), format a date, default value… The result can be used by the next actions.
* **Redirect the user** – send the visitor to a page after clicking a link or button, sending a Contact Form 7 form, logging in or registering.
* **Add rows to a table** – store values from any trigger (a whole uploaded file, a Contact Form 7 form, a webhook, a widget…) in a table you define, adding rows or replacing them all.
* **Wait** – schedule the actions that follow it to run minutes, hours, days or weeks later (for example, a reminder email two days after a form is sent). Scheduled actions can be run now or cancelled from Logs → Scheduled actions.

* **Send data to an external webhook** – call any API with GET, POST, PUT, PATCH or DELETE. Edit the URL, the key / value pairs to send (as JSON or form data) and optional headers. Values can include any field received by the trigger.
* **Send an email** – configure the recipient, Reply-To, subject and message. All of them can include any field received by the trigger.

Each automation can run several actions, in the order you choose; the results of calculations and formats are passed on to the actions below them. Insert trigger values anywhere with the **Insert field…** picker; they are written as `{{field_name}}`.

= Admin screens =

* **Automations** – list of all automations with their name, trigger, actions, number of completed runs, status (active / inactive) and creation date. Activate, deactivate, duplicate or delete them individually or in bulk.
* **New Automation** – name, trigger (with optional webhook security), actions, field and WordPress user conditions, status, maximum number of runs or unlimited, and start / end dates or never expires.
* **Logs** – every execution: the event, the executed actions, whether each one succeeded, and the date. Click an entry to see the event data, the data sent and the external server's response. **Retry** the failed actions of an execution (one by one or in bulk): only the actions that failed run again, with the original event data.
* **Webhook Logs** – every call received by your webhook URLs and widgets. Click an entry to see the URL used, the HTTP method, the caller IP and the received field / value pairs, or the error message and code when the call was rejected (for example, when no data was received).
* **Settings** – email notifications to the administrator when an automation fails (recipients, also on partial failures, at most one email per automation every N minutes), how the caller IP is detected (direct, proxy or Cloudflare), how many days to keep the automation logs and the webhook logs (older entries are deleted automatically every day), and whether to delete all data when the plugin is deleted.

= Failure notifications =

When an automation fails (or, optionally, when only some of its actions fail), the plugin emails the site administrator — or the addresses you choose — with the failed actions, the error messages and a link to retry them. A configurable pause per automation prevents a flood of emails when an external service is down.

= For developers =

The plugin is extensible: add-ons can register new trigger and action types, add settings, editor cards and admin pages, and hook into every execution. The built-in triggers and actions use the same API. See "For Developers" in the [plugin documentation](https://glocalsaino.com/automationsbasedontriggers/documentacion/#developers).

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install it from the Plugins screen.
2. Activate the plugin.
3. Go to **Automations → New Automation**.
4. To use the Contact Form 7 trigger, install and activate [Contact Form 7](https://wordpress.org/plugins/contact-form-7/).

== Frequently Asked Questions ==

= Where is the documentation? =

At [glocalsaino.com/automationsbasedontriggers/documentacion](https://glocalsaino.com/automationsbasedontriggers/documentacion/): installation, every trigger, condition and action explained, fields and placeholders, tables, shortcodes, logs and retries, settings and more answers.

= Is Contact Form 7 required? =

Only for the Contact Form 7 trigger. The webhook trigger works without it.

= How do I send data to the webhook URL? =

Copy the URL shown in the automation's Trigger section. Send the keys you configured as query string parameters (`?email=...&name=...`), as a form body or as a JSON body, using GET, POST or PUT. Nested JSON keys are available with dots, for example `customer.email`.

= Is the webhook URL secret? =

The URL contains a random 32-character key that identifies the automation. Treat it like a password. You can generate a new URL at any time from the automation screen; the old one stops working immediately. For stronger protection, enable the header token, the IP allowlist and / or the HMAC signature in the automation's Trigger section.

= Which webhook protection should I use? =

If the sender is Stripe, Shopify, WooCommerce or GitHub, use the signature with its preset. If the sender is a generic tool or a developer, the header token is the easiest one to set up. Use the IP allowlist when the sender calls from fixed IP addresses.

= How does the plugin know whether the visitor has logged in, with Contact Form 7? =

Contact Form 7 sends forms without the WordPress session check, so by default every visitor looks logged out. For forms whose automations use a condition on the logged-in visitor, the plugin adds the standard WordPress REST nonce to the form so the session is recognised. If you cache pages for logged-in users, exclude the pages with those forms from the cache.

= Can a webhook trigger check the logged-in visitor? =

No: webhook calls come from an external server, not from a visitor's browser. Use a user condition identified by a field that contains the user's email or username instead.

= What counts as a "completed run"? =

Every time the trigger fires and the conditions are met, the automation's actions run and the counter increases by one. When a maximum number of runs is set, the automation stops after reaching it.

= How reliable are the "Wait" steps? =

Scheduled actions are run by WP-Cron, which WordPress triggers on page visits. On sites with little traffic they may run some minutes late. For exact timing, set up a real cron job for wp-cron.php (most hosts offer this).

= Do link clicks from bots or email scanners count? =

Search engines and link previews are ignored by default (you can turn this off), and repeated clicks of the same visitor within 10 seconds count once. Some corporate email scanners open links like a normal browser and cannot be detected.

= Which files can be uploaded? =

CSV files (comma, semicolon, tab or pipe separated; UTF-8 or Windows / Latin-1 encoding) and Excel .xlsx files (the first sheet). The first row must contain the column titles; each title becomes a field name ("Fecha de alta" → fecha_de_alta). Old .xls files must be saved as .xlsx or .csv first. Reading .xlsx files needs the PHP zip and XML extensions, which almost every host has.

= Once per file or once per row? =

"Once for the whole file" runs the automation one time: the "Add rows to a table" action adds every row of the file, and the other actions get the file name and the number of rows. "Once for each row" runs it for every row, so each action (an email, a webhook, a calculation…) gets the columns of that row.

= The widget does not appear on the other website. Why? =

Check that the website is listed in the widget's "Allowed websites" (or leave the list empty to allow any site), and that no security plugin or server rule on your site sends an "X-Frame-Options" header for every page, which forbids showing any page inside an iframe.

= Do widget visitors count as logged-in users? =

No. Inside an iframe on another website, browsers do not send your site's login cookies, so widget visitors are always anonymous. To identify a user, ask for their email in a field and use a user condition identified by that field.

= What does "Retry" do exactly? =

It runs again only the actions that failed in that execution, with the same event data. Calculations and formats are recomputed so their results are available again; waits are skipped. Actions that succeeded are not repeated, the run counter does not change and the conditions are not evaluated again. The retry is recorded as a new log entry linked to the original. If you edited or removed a failed action after the event, that action is not retried.

= When the trigger fires but the conditions are not met, is anything logged? =

Yes. The Logs screen shows the event with the result "Conditions not met", and the run counter does not increase.

== Screenshots ==

1. The automations list: trigger, actions, completed runs, status and creation date of each automation.
2. Creating an automation: choose the trigger among Contact Form 7, webhooks, links, buttons, embeddable widgets, file uploads and WordPress user events.
3. Adding actions (webhook, email, calculator, format data, redirect, tables, wait) and setting the number of runs and the dates when the automation is active.
4. Logs: every execution with its event, the actions run and their result, plus the scheduled actions.
5. Tables: the data collected by your automations, ready to show anywhere with a shortcode.
6. Settings: log retention and email notifications when an automation fails.
7. Settings: caller IP detection for webhook protection, optional hCaptcha for widgets and uninstall options.

== External services ==

This plugin does not connect to any external service by itself.

Only when you configure a **"Send data to an external webhook"** action does the plugin send an HTTP request, at the moment the automation's trigger fires, to the URL **you** entered. The request contains exactly the key / value pairs and headers you configured for that action, which may include data submitted by your visitors (for example, Contact Form 7 field values) or data received by your webhook URL. The terms of use and privacy policy of that destination are those of the service you choose to send the data to.

The **webhook trigger** works the other way around: external systems you choose send data to a URL on your own site.

**hCaptcha (optional).** Only when you add hCaptcha keys in Settings and choose the captcha for an embeddable widget, the widget loads the hCaptcha script, which shows the challenge to the visitor and may collect information about their browser and device. When the visitor sends the widget, your site sends the challenge response and the visitor's IP address to hCaptcha to verify it. Nothing is sent to hCaptcha otherwise. [Terms of Service](https://www.hcaptcha.com/terms), [Privacy Policy](https://www.hcaptcha.com/privacy).

== Changelog ==

= 1.3.3 =
* Suggested text for the site's privacy policy (Settings → Privacy).
* The automation editor only reads its own known fields when saving.
* Screenshots and plugin website.

= 1.3.2 =
* The plugin is now called GlocalSaino Automations Based on Triggers (slug and text domain: glocalsaino-automations-based-on-triggers).

= 1.3.1 =
* The embeddable widget no longer has its own custom CSS field. It can use the site's Additional CSS from WordPress instead (Customizer or Site Editor).

= 1.3.0 =
* New trigger: CSV or Excel (.xlsx) file uploaded, from the back end or from the website with the [glocalsaino_aat_upload] shortcode; once per file or once per row.
* New action: Add rows to a table (from files, Contact Form 7, webhooks, widgets and any other trigger).
* New Tables screen and [glocalsaino_aat_table] shortcode with search, sorting, pagination, visibility rules and CSV export.

= 1.2.0 =
* New trigger: embeddable widget for other websites (button, link or form with fields), with design options, allowed websites, single-use tokens, anti-spam, optional hCaptcha, statistics and a live preview.
* Webhook Logs also record widget submissions and can be filtered by source.

= 1.1.0 =
* New trigger: click on a link (tracked URL and shortcode, destination set in the back end, link parameters as fields, bots and repeated clicks ignored).
* New trigger: click on a button ([glocalsaino_aat_button] shortcode with text, style, message and destination set in the back end).
* New trigger: WordPress user events (registration, login, password change).
* New action: Calculator, whose result can be used by the following actions.
* New action: Format data (words, letters, case, text, numbers and dates).
* New action: Redirect the user (links, buttons, Contact Form 7, login and registration).
* New action: Wait, to schedule the following actions; pending ones can be run now or cancelled from Logs → Scheduled actions.
* Retries recompute calculations and formats so the retried actions get their values.

= 1.0.0 =
* First release: Contact Form 7 and webhook triggers; webhook security (header token, IP allowlist, HMAC signatures with Stripe, Shopify, WooCommerce and GitHub presets); external webhook and email actions; field and WordPress user conditions; failure email notifications; retry of failed actions from the logs; extension API for add-ons; run limits and dates; logs, webhook logs and log retention settings.

== Upgrade Notice ==

= 1.3.3 =
Privacy policy text and small improvements.

= 1.3.2 =
New plugin name.

= 1.3.1 =
The widget custom CSS field was replaced by an option to use the site's Additional CSS.

= 1.3.0 =
New file upload trigger, data tables and "Add rows to a table" action.

= 1.2.0 =
New embeddable widget trigger for other websites.

= 1.1.0 =
New link, button and user event triggers; calculator, format data, redirect and wait actions.

= 1.0.0 =
First release.
