-- Add `admin` column to all admin_ prefixed tables (0 = not admin, 1 = admin)
-- Use MySQL 8+: `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`

ALTER TABLE `admin_menu` ADD COLUMN IF NOT EXISTS `admin` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `admin_orders` ADD COLUMN IF NOT EXISTS `admin` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `admin_order_items` ADD COLUMN IF NOT EXISTS `admin` TINYINT(1) NOT NULL DEFAULT 0;

-- To apply: mysql -u <user> -p restaurant < add_admin.sql
