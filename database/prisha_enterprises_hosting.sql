/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-12.3.2-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: prisha_enterprises
-- ------------------------------------------------------
-- Server version	12.3.2-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `admins` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` VALUES
(1,'admin','admin@prishaenterprises.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Administrator','Active','2026-08-09 18:15:12','2026-08-09 18:15:12');
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `bulk_enquiries`
--

DROP TABLE IF EXISTS `bulk_enquiries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bulk_enquiries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `business_name` varchar(150) DEFAULT NULL,
  `phone` varchar(15) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `product` varchar(200) DEFAULT NULL,
  `quantity` varchar(50) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('New','Contacted','Closed') NOT NULL DEFAULT 'New',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bulk_status` (`status`),
  KEY `idx_bulk_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bulk_enquiries`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `bulk_enquiries` DISABLE KEYS */;
INSERT INTO `bulk_enquiries` VALUES
(1,'Anmol wadhwa',NULL,'8360676343','Anmolwadhwa816@gmail.com','General Contact',NULL,'i need   disposible container','Closed','2026-08-09 18:55:27'),
(2,'divya',NULL,'9646448082','CHAWALAD09@GMAIL.COM','General Contact',NULL,'i need container 500ml','New','2026-08-30 17:02:51'),
(3,'divya',NULL,'9646448082','CHAWALAD09@GMAIL.COM','General Contact',NULL,'i need container 500ml','New','2026-08-30 17:04:32');
/*!40000 ALTER TABLE `bulk_enquiries` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_categories_slug` (`slug`),
  KEY `idx_categories_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES
(1,'Meal Trays','meal-trays','Disposable meal trays with multiple compartments for restaurants and catering.','prod_eb043832affb9802.webp','Active','2026-08-09 18:15:12'),
(2,'Disposable Containers','disposable-containers','Food containers with lids in multiple sizes for takeaway and storage.','prod_3346e5f9c9925b33.jpg','Active','2026-08-09 18:15:12'),
(3,'Disposable Glasses','disposable-glasses','Ripple and plain disposable glasses for hot and cold beverages.','prod_99c47f3eb19e2cea.jpg','Active','2026-08-09 18:15:12'),
(5,'Butter Paper','butter-paper','Butter paper sheets and rolls for food wrapping and baking.','prod_4c1df2e83893bdb0.webp','Active','2026-08-09 18:15:12'),
(6,'Food Packaging','food-packing','Assorted food packaging solutions for retail and wholesale.','prod_9052d82c9f9a13db.webp','Active','2026-08-09 18:15:12');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_clients_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES
(1,'Fine Dine Restaurant','Premium dining partner using our meal trays, containers and packaging for dine-in and takeaway service.',1,'Active','2026-08-30 17:15:50','2026-08-30 17:16:50'),
(2,'Chandu Chat','Popular chat and snack outlet supplied with disposable plates, glasses and food packaging for daily service.',2,'Active','2026-08-30 17:15:50','2026-08-30 17:15:50'),
(3,'Agra Chat Bhandar','Trusted chat bhandar partner supplied with disposable plates, glasses and packaging for everyday service.',2,'Active','2026-08-30 17:15:50','2026-08-30 17:17:15');
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `customers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `password` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(80) DEFAULT NULL,
  `state` varchar(80) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_customers_email` (`email`),
  KEY `idx_customers_phone` (`phone`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES
(1,'Demo Customer','customer@example.com','9876501234','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','123 Demo Street','Mumbai','Maharashtra','400001','2026-08-09 18:15:12','2026-08-09 18:15:12'),
(2,'anmol','Anmolwadhwa816@gmail.com','8360676343','$2y$10$09GAch6mHsITMb.2oDTAHeYEMMo.DTrPw89SwFzC0tqclreYs3FK.','12 street','Rajpura','punjab','140401','2026-08-10 05:26:14','2026-08-10 05:27:25'),
(3,'test','test@gmal.com','7777777777','$2y$10$Qkb2N5Gnjp6J5ss.Tr09ROaOyrsttQ1VjqON3i/1SUF3C2snaMTP2',NULL,NULL,NULL,NULL,'2026-08-15 05:35:49','2026-08-15 05:35:49');
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned DEFAULT NULL,
  `product_name` varchar(200) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order_id` (`order_id`),
  KEY `idx_order_items_product_id` (`product_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES
(1,1,10,'Butter Paper Roll','PE-BP-ROLL',1,130.00,130.00,'2026-08-09 18:38:31'),
(2,2,10,'Butter Paper Roll','PE-BP-ROLL',1,130.00,130.00,'2026-08-10 05:29:15'),
(3,2,NULL,'Eco-Friendly Paper Plates','PE-EP-PLT',1,160.00,160.00,'2026-08-10 05:29:15'),
(4,2,8,'Ripple Glass 250ml','PE-RG-250',1,110.00,110.00,'2026-08-10 05:29:16'),
(5,3,10,'Butter Paper Roll','PE-BP-ROLL',2,130.00,260.00,'2026-08-15 05:36:42'),
(6,3,NULL,'Eco-Friendly Paper Plates','PE-EP-PLT',1,160.00,160.00,'2026-08-15 05:36:43'),
(7,4,10,'Butter Paper Roll','PE-BP-ROLL',1,130.00,130.00,'2026-08-30 17:41:11'),
(8,5,10,'Butter Paper Roll','PE-BP-ROLL',1,160.00,160.00,'2026-08-30 17:55:04');
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(30) NOT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(15) NOT NULL,
  `address` text NOT NULL,
  `city` varchar(80) NOT NULL,
  `state` varchar(80) NOT NULL,
  `pincode` varchar(10) NOT NULL,
  `landmark` varchar(150) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `shipping` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(30) NOT NULL DEFAULT 'COD',
  `payment_status` enum('Pending','Paid','Failed','Refunded') NOT NULL DEFAULT 'Pending',
  `order_status` enum('Pending','Confirmed','Processing','Shipped','Out for Delivery','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_orders_order_number` (`order_number`),
  KEY `idx_orders_phone` (`phone`),
  KEY `idx_orders_email` (`email`),
  KEY `idx_orders_order_status` (`order_status`),
  KEY `idx_orders_created_at` (`created_at`),
  KEY `idx_orders_customer_id` (`customer_id`),
  CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES
(1,'PE202608100001',NULL,'Anmol wadhwa','Anmolwadhwa816@gmail.com','8360676343','H.no 290 near scholar public school bachitter nagar dhamoli rajpura','RAjpura','Punjab','140401',NULL,130.00,60.00,0.00,0.00,190.00,'COD','Pending','Pending',NULL,'2026-08-09 18:38:31','2026-08-09 18:38:31'),
(2,'PE202608100002',2,'anmol','Anmolwadhwa816@gmail.com','8360676343','H.no 290 near scholar public school bachitter nagar dhamoli rajpura','RAjpura','Punjab','140401',NULL,400.00,0.00,0.00,0.00,400.00,'COD','Pending','Pending',NULL,'2026-08-10 05:29:15','2026-08-10 05:29:15'),
(3,'PE202608150001',3,'test','test@gmal.com','7777777777','H.no 290 near scholar public school bachitter nagar dhamoli rajpura','RAjpura','Punjab','140401',NULL,420.00,0.00,0.00,0.00,420.00,'COD','Paid','Pending',NULL,'2026-08-15 05:36:41','2026-08-15 05:38:18'),
(4,'PE202608300001',NULL,'DIVYA WADHWA','CHAWALAD09@GMAIL.COM','8054798966','SCO 290/1 near scholar public school bachitter nagar dhamoli','delhi','Punjab','140401',NULL,130.00,0.00,0.00,0.00,130.00,'COD','Pending','Pending',NULL,'2026-08-30 17:41:11','2026-08-30 17:41:11'),
(5,'PE202608300002',NULL,'DIVYA WADHWA','CHAWALAD09@GMAIL.COM','8054798966','SCO 290/1 near scholar public school bachitter nagar dhamoli','RAJPURA','Punjab','140401',NULL,160.00,0.00,28.80,0.00,188.80,'COD','Pending','Pending',NULL,'2026-08-30 17:55:04','2026-08-30 17:55:04');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned NOT NULL,
  `name` varchar(200) NOT NULL,
  `slug` varchar(220) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `mrp` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(5,2) NOT NULL DEFAULT 0.00,
  `gst` decimal(5,2) NOT NULL DEFAULT 18.00,
  `stock` int(11) NOT NULL DEFAULT 0,
  `pack_size` varchar(100) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_products_sku` (`sku`),
  UNIQUE KEY `uk_products_slug` (`slug`),
  KEY `idx_products_category_id` (`category_id`),
  KEY `idx_products_status` (`status`),
  KEY `idx_products_created_at` (`created_at`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES
(1,1,'3 Compartment Meal Tray','3-compartment-meal-tray','PE-MT-3C','Durable 3 compartment disposable meal tray ideal for thali-style meals, catering and takeaway. Leak-resistant design suitable for restaurants and events.','Sturdy 3-compartment tray for everyday meals and catering.',180.00,220.00,18.18,18.00,250,'Pack of 50','prod_0a6f59311c99bf0c.webp','Active',1,'2026-08-09 18:15:12','2026-08-30 16:32:17'),
(2,1,'5 Compartment Meal Tray','5-compartment-meal-tray','PE-MT-5C','Premium 5 compartment disposable meal tray for complete meals. Perfect for restaurants, mess halls and bulk catering orders.','5-compartment tray for full meal servings.',240.00,300.00,20.00,18.00,200,'Pack of 50','prod_eb043832affb9802.webp','Active',1,'2026-08-09 18:15:12','2026-08-30 16:29:17'),
(3,1,'8 Compartment Meal Tray','8-compartment-meal-tray','PE-MT-8C','Large 8 compartment disposable meal tray designed for elaborate meals and wedding catering. Strong and stackable.','Spacious 8-compartment tray for bulk catering.',320.00,400.00,20.00,18.00,150,'Pack of 25','prod_dae4e62c850bb6d1.webp','Active',1,'2026-08-09 18:15:12','2026-08-30 16:38:20'),
(4,2,'500ml Disposable Container with Lid','500ml-disposable-container-with-lid','PE-DC-500','500ml round disposable container with secure lid. Ideal for curries, desserts and takeaway portions.','500ml container with leak-resistant lid.',145.00,180.00,19.44,18.00,300,'Pack of 100','prod_3346e5f9c9925b33.jpg','Active',1,'2026-08-09 18:15:12','2026-08-30 16:44:05'),
(5,2,'750ml Disposable rectangular Container with Lid','750ml-disposable-container-with-lid','PE-DC-750','750ml disposable food container with tight-fit lid for medium portions. Microwave-safe options available on request.','750ml takeaway container with lid.',175.00,220.00,20.45,18.00,280,'Pack of 100','prod_9052d82c9f9a13db.webp','Active',1,'2026-08-09 18:15:12','2026-08-30 16:45:19'),
(6,2,'500ml  rectangular Disposable Container with Lid','1000ml-disposable-container-with-lid','PE-DC-1000','1 litre disposable container with lid for family portions and bulk takeaway. Strong walls and secure snap lid.','1L container perfect for large portions.',210.00,260.00,19.23,18.00,220,'Pack of 50','prod_6f7226c3e5c1b210.webp','Active',1,'2026-08-09 18:15:12','2026-08-30 16:44:48'),
(7,3,'Ripple Glass 200ml','ripple-glass-200ml','PE-RG-200','200ml ripple wall disposable glass for hot beverages. Heat-insulated design keeps drinks warm and hands cool.','Insulated 200ml ripple paper glass.',95.00,120.00,20.83,18.00,400,'Pack of 100','prod_99c47f3eb19e2cea.jpg','Active',1,'2026-08-09 18:15:12','2026-08-30 16:46:31'),
(8,3,'Ripple Glass 250ml','ripple-glass-250ml','PE-RG-250','250ml ripple disposable glass suitable for tea, coffee and soft drinks. Ideal for cafes, stalls and events.','250ml ripple glass for cafes and events.',110.00,140.00,21.43,18.00,349,'Pack of 100','prod_e4d701c65abfd5fb.jpg','Active',1,'2026-08-09 18:15:12','2026-08-30 16:57:01'),
(10,5,'Butter Paper Roll','butter-paper-roll','PE-BP-ROLL','Food-grade butter paper roll for wrapping, baking and packing. Non-stick surface suitable for kitchens and bakeries.','Food-grade butter paper roll for wrapping.',200.00,250.00,20.00,18.00,114,'1KG','prod_4c1df2e83893bdb0.webp','Active',1,'2026-08-09 18:15:12','2026-09-04 03:47:16');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_settings_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES
(1,'business_name','Prisha Enterprises'),
(2,'phone','+918054798966'),
(3,'email','info@prishaenterprises.com'),
(4,'address','Sco 290/1 near Scholar Public School, Bachitter Nagar, Dhamoli, Rajpura'),
(5,'shipping_charge','60'),
(6,'free_shipping_minimum','999'),
(7,'whatsapp_number','918054798966'),
(8,'low_stock_threshold','10');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `user_type` enum('customer','admin') NOT NULL DEFAULT 'customer',
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pwd_resets_token` (`token_hash`),
  KEY `idx_pwd_resets_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-09-03 20:48:16
