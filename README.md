# Thinkbeat Smart Order Delete

## Overview
Safe, scalable, and production-ready Magento 2 module to delete test or unwanted orders. Features a Trash Bin (Soft Delete) mechanism to safely archive orders before permanent deletion, ensuring database integrity.

## Features
- **Mass Delete Orders**: Integrates directly into the Sales Order Grid.
- **Soft Delete (Trash Bin)**: Moves orders to a "Trash Bin" first.
- **Hard Delete**: Option to permanently delete immediately or purge from Trash.
- **Audit Logging**: Tracks who deleted what and when.
- **CLI Support**: Command line interface for deleting orders.
- **Auto-Purge**: Cron job to automatically clean up old trash.
- **Relations Handling**: Safely handles Invoices, Shipments, and Creditmemos.

## Installation
1. Copy the module to `app/code/Thinkbeat/SmartOrderDelete`.
2. Run the following commands:
   ```bash
   bin/magento module:enable Thinkbeat_SmartOrderDelete
   bin/magento setup:upgrade
   bin/magento setup:di:compile
   bin/magento cache:flush
   ```

## Configuration
Go to **Stores > Configuration > Thinkbeat > Smart Order Delete**.
- **Module Enabled**: Turn on/off.
- **Enable Trash Bin**: If Yes, orders are moved to Trash first. If No, they are deleted permanently immediately.
- **Auto-Purge**: Enable automatic cleanup of Trash.
- **Purge Days**: Number of days to keep items in Trash.

## Usage

### Admin Panel
1. **Delete Orders**: Go to **Sales > Orders**. Select orders, choose **Delete Orders** from the Mass Actions dropdown.
2. **Trash Bin**: Go to **Sales > Deleted Orders > Trash Bin**. View archived orders. Select and **Restore** (View Only/Log) or **Delete Permanently**.
3. **Logs**: Go to **Sales > Deleted Orders > Deletion Logs** to view the audit trail.

### CLI
Delete orders via command line:
```bash
bin/magento order:delete --status=canceled
bin/magento order:delete --id=000000001,000000002
```

## Technical Details
- **Namespace**: Thinkbeat\SmartOrderDelete
- **Tables**:
    - `thinkbeat_smart_order_delete_trash`: Stores serialized order data.
    - `thinkbeat_smart_order_delete_log`: Stores audit logs.
- **Service Contract**: `Thinkbeat\SmartOrderDelete\Service\OrderDelete` handles the core logic.

## Security
- Requires `Thinkbeat_SmartOrderDelete::delete` permission.
- Soft Delete preserves a serialized JSON copy of the Order, Items, Addresses, Payments, Invoices, Shipments, and Creditmemos.

## Compatibility
- Magento 2.4.x
- PHP 7.4, 8.1, 8.2

## Cron Setup
To ensure the **Auto Delete** and **Auto-Purge** features work automatically, Magento's master cron job must be configured on your server to run every minute.

### 1. Server Cron Configuration (cPanel / aaPanel / Ubuntu)
Set up a daily cron (or minute-by-minute shell task) for the user that owns the web files (usually `www-data` or `www`). **Do not run this as ROOT!**

**Example crontab entry:**
```bash
* * * * * /usr/bin/php /path/to/your/magento2/bin/magento cron:run 2>&1 | grep -v "Ran jobs by schedule" >> /path/to/your/magento2/var/log/magento.cron.log
```
*In a tool like **aaPanel**: Create a "Shell Script" task, set Execute Cycle to "N Minutes: 1", select the `www` user, and use the script `cd /your/magento/dir && php bin/magento cron:run`.*

### 2. Module Cron Configuration
The module comes with two built-in cron jobs:
- **Auto Delete** (`thinkbeat_smartdelete_auto_delete`): Can be scheduled via Admin Panel.
- **Auto Purge** (`thinkbeat_smartdelete_auto_purge`): Runs automatically at `0 2 * * *` (2:00 AM daily).

**Configure Auto Delete via Admin:**
1. Go to **Stores > Configuration > Thinkbeat > Smart Order Delete**.
2. Expand the **Auto Delete** section.
3. Update the **Cron Expression** field (e.g., `0 1 * * *` for 1:00 AM daily) and click **Save Config**.
4. Flush cache: `bin/magento cache:clean config`

*(Optional) Run Manually via CLI for Testing:*
```bash
bin/magento cron:run --group=default
```

## Support
Thinkbeat
