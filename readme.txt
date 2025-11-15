=== Novac for Give ===
Contributors: engineeringnovac
Tags: give, payments, novac, donations, charity
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Short Description
Integrates Novac as a payment gateway for GiveWP to accept donations via Novac's API'.

== Description ==
Novac Gateway for GiveWP adds an official Novac payment gateway to GiveWP, allowing donations to be processed via the Novac API. Designed for use in development and production with full support for sandbox and live environments, webhook handling, and secure API key configuration.

Features:
* Support for Novac API (sandbox & production)
* Webhook handler for asynchronous payment notifications
* Manual and automatic capture modes
* Mapping of Novac statuses to GiveWP payment statuses
* Configurable API key and environment via settings or environment variables
* Tests, linting, and CI-ready configuration

== Requirements ==
* PHP 8.1 or greater
* GiveWP plugin (compatible version noted in plugin data)
* Composer for PHP dependencies (if modifying plugin)
* WordPress 5.9+

== Installation ==
1. Upload the plugin folder to wp-content/plugins/ or install via zip.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to Donations → Settings → Payment Gateways and enable "Novac".
4. Configure your API credentials and environment on the settings page.
5. (Optional) Configure webhook endpoint in the Novac dashboard (see Webhooks section).

== Configuration ==
Settings available in GiveWP → Settings → Payment Gateways → Novac:
* API Key
* Environment: test | production
* Webhook secret (configure to validate incoming webhooks)
* Test mode toggle (mirror environment selection)


== Webhooks ==
Recommended webhook endpoint: /wp-json/novac/v1/webhook
Register the endpoint URL in the Novac dashboard for the desired events.

Supported webhook events (examples):
* payment.successful — mark donation as completed
* payment.failed  — mark donation as failed

Security:
* Validate webhook signatures using the webhook secret configured in settings.
* Ensure the webhook endpoint accepts only POST and checks content type.
* Respond quickly (HTTP 200) and process heavier tasks asynchronously.

== External Services ==
This plugin integrates with:
* Novac API — payment processing and webhooks
* GiveWP — donation management in WordPress

== Contribution Guidelines ==
Contributions welcome. Please:
1. Fork the repository.
2. Create feature branches from main.
3. Write tests for new features.
4. Follow PSR-12 for PHP and project linting rules.
5. Open PRs with clear description and testing notes.

== Screenshots ==
1. screenshot-1.png — Novac gateway settings in GiveWP
2. screenshot-2.png — Donation checkout with Novac option
3. screenshot-3.png — Webhook logs / admin view

== Frequently Asked Questions ==
Q: How do I enable test mode?
A: select 'test' in plugin settings.

Q: Where do I set the webhook secret?
A: In the Novac gateway settings in GiveWP. Use the same secret in the Novac dashboard webhook configuration.

== Changelog ==
= 1.0.0 =
* Initial release: basic payment processing, webhook handling, settings UI.

== Upgrade Notice ==
= 1.0.0 =
Initial public release. Follow upgrade/testing notes in CHANGELOG.md if present.

== Notes ==
- Add a LICENSE file (MIT recommended) at repository root.
- Replace placeholder contributor usernames and screenshots with real assets.
- Update Tested up to match target WordPress versions before release.

== Support ==
For issues and support, open an issue in the source repository and include reproduction steps and environment details.

== Source Code ==
Repository: https://github.com/your-org/novac-givewp
(Replace with actual remote URL)