-- Migration to rename existing tables to admin_ prefix
-- Run this once on your database (make a backup first):

RENAME TABLE
    `order_items` TO `admin_order_items`,
    `orders` TO `admin_orders`,
    `menu` TO `admin_menu`;

-- After running, verify foreign keys and constraints.
-- To apply: mysql -u <user> -p restaurant < rename_tables.sql
