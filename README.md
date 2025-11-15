# Novac for Give

Accept donations via Novac (hosted checkout) in GiveWP.

## Description

This plugin integrates Novac payment gateway with GiveWP, allowing nonprofit organizations to accept donations through Novac's hosted checkout system. Novac supports multiple currencies and provides a secure payment processing solution for WordPress donation forms.

## Requirements

- **WordPress:** 6.0 or higher
- **PHP:** 7.4 or higher
- **GiveWP Plugin:** Required (must be active)

## Supported Currencies

- NGN (Nigerian Naira)
- GHS (Ghanaian Cedi)
- USD (US Dollar)
- EUR (Euro)
- GBP (British Pound)

## Directory Structure

```
novac-give/
├── assets/
│   └── js/
│       ├── novac-inline.js      # Novac inline checkout script
│       └── gateway.js             # Novac integration logic
├── includes/
│   ├── admin/
│   │   └── settings.php           # Admin settings configuration
│   └── class-novac-give-gateway.php  # Main gateway class
├── novac-give.php              # Main plugin file
├── index.php                      # Directory access protection
└── README.md                      # This file
```

## Installation

1. Upload the `novac-give` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure GiveWP is installed and activated
4. Navigate to **GiveWP → Settings → Payment Gateways → Novac** to configure the gateway

## Configuration

After installation, configure the plugin:

1. Go to **Donations → Settings → Payment Gateways → Novac**
2. Enter your Novac API credentials
3. Configure your webhook settings
4. Enable Novac for specific donation forms or globally

## Features

- Hosted checkout integration with Novac
- Support for multiple currencies (NGN, GHS, USD, EUR, GBP)
- Webhook support for payment verification
- Per-form gateway customization
- Secure payment processing

## Webhook Configuration

The plugin includes webhook verification with a whitelisted IP address (`23.23.23.99`) for enhanced security. Ensure your Novac webhook is configured to send notifications to your WordPress site.

## Developer Information

- **Author:** Novac
- **Developer URI:** https://www.app.novacpayment.com
- **Documentation:** https://developer.novacpayment.com

## License

GNU General Public License v3.0 or later
http://www.gnu.org/licenses/gpl-3.0.html

## Support

For support and more information, visit:

- [Novac Developer Portal](https://developer.novacpayment.com)
- [Novac Dashboard](https://www.app.novacpayment.com)

## Changelog

### 1.0.0

- Initial release
- Novac hosted checkout integration
- Support for NGN, GHS, USD, EUR, GBP currencies
- Webhook payment verification
- Per-form gateway customization