=== GlocalSaino Actions Automation Based on Trigger ===
Contributors: glocalsaino, rafammoo
Tags: automation, webhook, contact form 7, email, no-code
Requires at least: 6.2
Tested up to: 7.1.3
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

No-code automations: when a Contact Form 7 form is submitted or a webhook receives data, send that data to an external API or by email.

== Description ==

**GlocalSaino Actions Automation Based on Trigger** lets you build automations without writing code. Each automation has a **trigger** (the event that starts it), optional **conditions**, and one or more **actions** that run when the trigger fires. Everything is free: no license key, no locked features, no usage caps.

= Triggers =

* **Contact Form 7 submission** – fire when a specific form is submitted, or only when a specific field of that form is submitted with a specific value (or with any value).
* **Data received through a webhook** – the plugin generates a unique webhook URL for the automation. Define the keys you expect, and the values the external system sends under those keys (as query string, form body or JSON body, via GET, POST or PUT) become available to the actions.

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

* **Send data to an external webhook** – call any API with GET, POST, PUT, PATCH or DELETE. Edit the URL, the key / value pairs to send (as JSON or form data) and optional headers. Values can include any field received by the trigger.
* **Send an email** – configure the recipient, Reply-To, subject and message. All of them can include any field received by the trigger.

Each automation can run several actions, in the order you choose. Insert trigger values anywhere with the **Insert field…** picker; they are written as `{{field_name}}`.

= Admin screens =

* **Automations** – list of all automations with their name, trigger, actions, number of completed runs, status (active / inactive) and creation date. Activate, deactivate, duplicate or delete them individually or in bulk.
* **New Automation** – name, trigger (with optional webhook security), actions, field and WordPress user conditions, status, maximum number of runs or unlimited, and start / end dates or never expires.
* **Logs** – every execution: the event, the executed actions, whether each one succeeded, and the date. Click an entry to see the event data, the data sent and the external server's response.
* **Webhook Logs** – every call received by your webhook URLs. Click an entry to see the URL used, the HTTP method, the caller IP and the received field / value pairs, or the error message and code when the call was rejected (for example, when no data was received).
* **Settings** – how the caller IP is detected (direct, proxy or Cloudflare), how many days to keep the automation logs and the webhook logs (older entries are deleted automatically every day), and whether to delete all data when the plugin is deleted.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install it from the Plugins screen.
2. Activate the plugin.
3. Go to **Automations → New Automation**.
4. To use the Contact Form 7 trigger, install and activate [Contact Form 7](https://wordpress.org/plugins/contact-form-7/).

== Frequently Asked Questions ==

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

= When the trigger fires but the conditions are not met, is anything logged? =

Yes. The Logs screen shows the event with the result "Conditions not met", and the run counter does not increase.

== External services ==

This plugin does not connect to any external service by itself.

Only when you configure a **"Send data to an external webhook"** action does the plugin send an HTTP request, at the moment the automation's trigger fires, to the URL **you** entered. The request contains exactly the key / value pairs and headers you configured for that action, which may include data submitted by your visitors (for example, Contact Form 7 field values) or data received by your webhook URL. The terms of use and privacy policy of that destination are those of the service you choose to send the data to.

The **webhook trigger** works the other way around: external systems you choose send data to a URL on your own site.

== Changelog ==

= 1.0.0 =
* First release: Contact Form 7 and webhook triggers; webhook security (header token, IP allowlist, HMAC signatures with Stripe, Shopify, WooCommerce and GitHub presets); external webhook and email actions; field and WordPress user conditions; run limits and dates; logs, webhook logs and log retention settings.

== Upgrade Notice ==

= 1.0.0 =
First release.
