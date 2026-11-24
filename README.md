# Paystack Addon for Gravity Forms

## Introduction
Paystack Addon for Gravity Forms allows you to quickly and easily implement credit card payments with WordPress. With the Paystack Add-On you can capture one time credit card payments or setup recurring payment subscriptions.

## Pre-Requisites
- Gravity Forms v2.5+
- WordPress v5.1+
- SSL Certificate Installed and - Configured
- Download and install the add-on
- A Paystack account

## Paystack Settings
The Paystack Settings will allow you to connect and authenticate with your paystack account and it will allow you to setup webhooks which are used to communicate events from Paystack back to your Gravity Forms environment.

Navigate to your Paystack Add-On Settings page:

1. Log into your WordPress admin dashboard.
2. On the left side navigation menu, hover over Forms and click on Settings.
3. Click the Paystack tab.

![Paystack Settings](./images/screenshot-paystack-settings.png)

### Mode
Allows you to specify the default environment you want to send your form submission to. Choosing Live will initiate real transactions, while Test will run the form entry into your paystack test environment, which should not generate any real transactions.

### Restricted Key
Paste the `rk_test_...` or `rk_live_...` restricted key for the selected mode. Create it in your Paystack Dashboard with Transactions: read and write, plus Plans: write if you sell subscriptions. Keys that can move money (transfers, refunds, settlements, payouts and similar) are rejected. The key's permissions are checked with Paystack every time you save the settings.

### Webhook Signing Secret
Paste the `whsec_...` signing secret for the same mode, from Settings > API Keys & Webhooks in your Paystack Dashboard. It is required with a restricted key. Point your Paystack webhook URL at the address shown under View Instructions, and make it listen to `charge.success`. Forms that sell subscriptions also need `subscription.create`, `subscription.disable`, `invoice.create`, `invoice.update` and `invoice.payment_failed`.
