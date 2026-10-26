# GlocalSaino Automations Based on Triggers

No-code automations for WordPress: when a form is sent, a file is uploaded, a link, button or widget is used, a webhook arrives or a user signs up, run emails, webhooks, calculations, tables, redirects and scheduled actions.

- **Website:** https://glocalsaino.com/automationsbasedontriggers/
- **Documentation:** https://glocalsaino.com/automationsbasedontriggers/documentacion/
- **License:** GPLv2 or later

## Features

**Triggers:** Contact Form 7 submissions, incoming webhooks (with header token, IP allowlist and HMAC signatures for Stripe, Shopify, WooCommerce and GitHub), clicks on tracked links and buttons, an embeddable widget (button, link or form) for other websites, CSV / Excel uploads, and WordPress user events (registration, login, password change).

**Conditions:** compare any field (equals, contains, starts / ends with, empty, greater / less than) or check the WordPress user (logged in, registered, a specific user), combined with "all" or "any".

**Actions:** send data to any webhook or API, send emails, calculate values, format data, redirect the visitor, add rows to data tables shown with a shortcode, and wait before running the next actions.

**Also included:** run limits and active dates, detailed logs, email alerts when an automation fails, one-click retry of the failed actions, and an extension API for add-ons.

Completely free: unlimited automations, no license key and no locked features.

## Requirements

- WordPress 6.2 or later
- PHP 7.4 or later (tested up to PHP 8.5)
- Contact Form 7, only for the Contact Form 7 trigger

## Installation

Download the latest release zip, upload it in **Plugins → Add New → Upload Plugin** and activate it. Then go to **Automations → New Automation**.

## Developers

Add-ons can register their own trigger and action types, fire triggers, create and fill tables, and hook into every execution. See [`EXTENDING.md`](EXTENDING.md) for the full extension API.

## Building the release zip

```
git archive --format=zip --prefix=glocalsaino-automations-based-on-triggers/ -o ../glocalsaino-automations-based-on-triggers.zip HEAD
```

Files used only for development (this README, `EXTENDING.md`, `LICENSE`, git files) are excluded from the zip through `.gitattributes`.
