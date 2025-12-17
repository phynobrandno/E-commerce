-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 17, 2025 at 10:41 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `user_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `color_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`color_data`)),
  `size_data` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`id`, `user_id`, `product_id`, `quantity`, `color_data`, `size_data`, `created_at`) VALUES
(29, 3, 75, 2, '[\"#FF0000\"]', '', '2025-11-27 12:27:30'),
(30, 3, 74, 1, NULL, NULL, '2025-11-27 12:27:59'),
(31, 3, 71, 1, NULL, NULL, '2025-11-28 16:03:40'),
(37, 3, 48, 1, NULL, NULL, '2025-12-01 22:18:26'),
(40, 3, 79, 1, '[\"#0000FF\"]', '42', '2025-12-17 18:49:14');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`) VALUES
(2, 'Clothes'),
(6, 'Computer'),
(3, 'Hair'),
(4, 'Perfume'),
(5, 'Phone'),
(8, 'Shoes'),
(9, 'Socks'),
(7, 'Watch');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','processing','completed','cancelled') DEFAULT 'pending',
  `shipment_status` enum('pending','processed','shipped','in_transit','out_for_delivery','delivered','cancelled') DEFAULT 'pending',
  `tracking_number` varchar(255) DEFAULT NULL,
  `carrier` varchar(100) DEFAULT NULL,
  `estimated_delivery` date DEFAULT NULL,
  `shipped_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `payment_method` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `delivery_address` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `total_amount`, `status`, `shipment_status`, `tracking_number`, `carrier`, `estimated_delivery`, `shipped_at`, `delivered_at`, `payment_method`, `phone`, `delivery_address`, `created_at`, `updated_at`) VALUES
(1, 3, 560.00, 'processing', 'pending', NULL, NULL, NULL, NULL, NULL, 'Credit/Debit Card', '11', 'hgfdx', '2025-11-06 22:43:00', '2025-11-21 15:44:45'),
(2, 3, 249.92, 'pending', 'pending', NULL, NULL, NULL, NULL, NULL, 'Credit/Debit Card', '4552', 'jhgfd', '2025-11-06 22:43:28', '2025-11-06 22:43:28'),
(3, 3, 119.98, 'pending', 'pending', NULL, NULL, NULL, NULL, NULL, 'PayPal', 'ww', 'dcfvg', '2025-11-06 22:59:03', '2025-11-06 22:59:03'),
(4, 3, 249.92, 'pending', 'pending', NULL, NULL, NULL, NULL, NULL, 'Stripe', 'mnbvcxz', 'dfg', '2025-11-06 23:00:36', '2025-11-06 23:00:36'),
(5, 3, 869.92, 'pending', 'pending', NULL, NULL, NULL, NULL, NULL, 'bank_transfer', '08142591169', '][pokjhgv', '2025-11-19 20:32:04', '2025-11-19 20:32:04'),
(6, 3, 120.00, 'pending', 'pending', NULL, NULL, NULL, NULL, NULL, 'bank_transfer', '08142591169', '][pokjhgv', '2025-11-19 20:42:21', '2025-11-19 20:42:21'),
(7, 3, 120.00, 'pending', 'pending', NULL, NULL, NULL, NULL, NULL, 'bank_transfer', '08142591169', '][pokjhgv', '2025-11-19 20:48:17', '2025-11-19 20:48:17'),
(8, 3, 249.92, 'pending', 'pending', NULL, NULL, NULL, NULL, NULL, 'bank_transfer', '08142591169', '][pokjhgv', '2025-11-19 20:56:06', '2025-11-19 20:56:06'),
(9, 3, 249.92, 'pending', 'pending', NULL, NULL, NULL, NULL, NULL, 'bank_transfer', '08142591169', '][pokjhgv', '2025-11-19 20:59:50', '2025-11-19 20:59:50'),
(10, 3, 120.00, 'completed', 'in_transit', 'FDX987654321US', 'UPS', '2025-11-22', '2025-11-19 08:00:55', '2025-11-22 08:00:55', 'bank_transfer', '08142591169', '][pokjhgv', '2025-11-19 21:04:47', '2025-11-22 08:10:15'),
(11, 3, 10029.94, 'completed', 'pending', 'UPS123456789US', 'UPS', '2025-11-24', '2025-11-21 08:00:55', NULL, 'bank_transfer', '08142591169', '][pokjhgv', '2025-11-20 10:24:34', '2025-11-22 08:10:37'),
(12, 7, 360.00, 'completed', 'pending', NULL, NULL, NULL, NULL, NULL, 'bank_transfer', '08142591169', '][pokjhgv', '2025-11-29 05:32:01', '2025-11-29 05:37:53');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`, `created_at`) VALUES
(1, 1, 39, 4, 140.00, '2025-11-06 22:43:00'),
(2, 2, 48, 1, 249.92, '2025-11-06 22:43:28'),
(3, 3, 35, 2, 59.99, '2025-11-06 22:59:03'),
(4, 4, 48, 1, 249.92, '2025-11-06 23:00:36'),
(5, 5, 47, 2, 120.00, '2025-11-19 20:32:04'),
(6, 5, 41, 2, 190.00, '2025-11-19 20:32:04'),
(7, 5, 48, 1, 249.92, '2025-11-19 20:32:04'),
(8, 6, 47, 1, 120.00, '2025-11-19 20:42:21'),
(9, 7, 47, 1, 120.00, '2025-11-19 20:48:17'),
(10, 8, 48, 1, 249.92, '2025-11-19 20:56:06'),
(11, 9, 48, 1, 249.92, '2025-11-19 20:59:50'),
(12, 10, 47, 1, 120.00, '2025-11-19 21:04:47'),
(13, 11, 49, 1, 10000.00, '2025-11-20 10:24:34'),
(14, 11, 42, 1, 29.94, '2025-11-20 10:24:34'),
(15, 12, 71, 3, 120.00, '2025-11-29 05:32:01');

-- --------------------------------------------------------

--
-- Table structure for table `order_tracking_history`
--

CREATE TABLE `order_tracking_history` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_tracking_history`
--

INSERT INTO `order_tracking_history` (`id`, `order_id`, `status`, `location`, `description`, `updated_at`) VALUES
(1, 10, 'pending', 'Warehouse', 'Order received and being processed', '2025-11-22 07:53:08'),
(2, 10, 'processed', 'Warehouse', 'Order processed and ready to ship', '2025-11-22 07:53:08'),
(3, 10, 'shipped', 'Distribution Center', 'Package shipped out', '2025-11-22 07:53:08'),
(4, 10, 'in_transit', 'Regional Hub', 'Package in transit to destination', '2025-11-22 07:53:08'),
(5, 10, 'delivered', 'Customer Location', 'Package delivered successfully', '2025-11-22 07:53:08'),
(6, 11, 'pending', 'Warehouse', 'Order received and being processed', '2025-11-22 07:53:08'),
(7, 11, 'processed', 'Warehouse', 'Order processed and ready to ship', '2025-11-22 07:53:08'),
(8, 10, 'pending', 'Warehouse', 'Order received and being processed', '2025-11-17 08:00:55'),
(9, 10, 'processed', 'Warehouse', 'Order verified and ready to ship', '2025-11-18 08:00:55'),
(10, 10, 'shipped', 'Distribution Center', 'Package shipped out', '2025-11-19 08:00:55'),
(11, 10, 'in_transit', 'Regional Hub', 'Package in transit', '2025-11-20 08:00:55'),
(12, 10, 'out_for_delivery', 'Local Delivery Center', 'Out for delivery', '2025-11-21 08:00:55'),
(13, 10, 'delivered', 'Customer Location', 'Package delivered successfully', '2025-11-22 08:00:55'),
(14, 11, 'pending', 'Warehouse', 'Order received and being processed', '2025-11-19 08:00:55'),
(15, 11, 'processed', 'Warehouse', 'High-value order verified and ready', '2025-11-20 08:00:55'),
(16, 11, 'shipped', 'Distribution Center', 'Package shipped with signature confirmation', '2025-11-21 08:00:55'),
(17, 11, 'in_transit', 'Regional Transit Hub', 'Package in transit to your area', '2025-11-22 08:00:55'),
(18, 10, 'processed', 'Famagusa', '', '2025-11-22 08:03:07'),
(19, 10, 'processed', 'Famagusa', '', '2025-11-22 08:03:13'),
(20, 10, 'processed', '', '', '2025-11-22 08:06:46'),
(21, 10, 'processed', '', '', '2025-11-22 08:07:02'),
(22, 10, 'processed', '', '', '2025-11-22 08:07:18'),
(23, 10, 'processed', '', '', '2025-11-22 08:09:47'),
(24, 10, 'in_transit', 'Famagusa', '', '2025-11-22 08:10:15'),
(25, 11, 'pending', '', '', '2025-11-22 08:10:37');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `payment_method` enum('card','bank_transfer','paypal') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `card_number` varchar(255) DEFAULT NULL,
  `card_name` varchar(255) DEFAULT NULL,
  `account_number` varchar(255) DEFAULT NULL,
  `account_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `delivery_address` text DEFAULT NULL,
  `receipt_image` varchar(500) DEFAULT NULL,
  `status` enum('pending','completed','failed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `user_id`, `order_id`, `payment_method`, `amount`, `transaction_id`, `card_number`, `card_name`, `account_number`, `account_name`, `email`, `phone`, `delivery_address`, `receipt_image`, `status`, `created_at`, `updated_at`) VALUES
(1, 3, 5, 'bank_transfer', 869.92, 'TXN17635843241715', NULL, NULL, 'FXQq8nNOeLoID6KEVL4R4ZrNbSgY1MIGQeVXU7qa', 'dfdss', NULL, '08142591169', '][pokjhgv', 'uploads/receipts/receipt_3_1763584324_8286.jpg', 'pending', '2025-11-19 20:32:04', '2025-11-19 20:32:04'),
(2, 3, 6, 'bank_transfer', 120.00, 'TXN17635849416189', NULL, NULL, 'J9CUNj2blYq3c8ZOQn3a2Cga+f4okEOdcRft1uVE', 'dfdss', NULL, '08142591169', '][pokjhgv', 'uploads/receipts/receipt_3_1763584941_5571.jpg', 'pending', '2025-11-19 20:42:21', '2025-11-19 20:42:21'),
(3, 3, 7, 'bank_transfer', 120.00, 'TXN17635852976714', NULL, NULL, 'suBGowGdtwL5nU6Mc9OQKH+iuHIZdRpS2g1JATT6', 'dfdss', NULL, '08142591169', '][pokjhgv', 'uploads/receipts/receipt_3_1763585297_3332.jpg', 'pending', '2025-11-19 20:48:17', '2025-11-19 20:48:17'),
(4, 3, 8, 'bank_transfer', 249.92, 'TXN17635857669247', NULL, NULL, 'h/WUPTMuzMTKQAC5w+iocHSBJx4upKCOOAmR7diQqM4=', 'dfdss', NULL, '08142591169', '][pokjhgv', 'uploads/receipts/receipt_3_1763585766_1711.jpg', 'pending', '2025-11-19 20:56:06', '2025-11-19 20:56:06'),
(5, 3, 9, 'bank_transfer', 249.92, 'TXN17635859907223', NULL, NULL, 'S+ZDd16Cp1kDDcZ8UlNxQgQYOM9mDzSpz664r8AS4QU=', 'dfdss', NULL, '08142591169', '][pokjhgv', 'uploads/receipts/receipt_3_1763585990_8842.jpg', 'pending', '2025-11-19 20:59:50', '2025-11-19 20:59:50'),
(6, 3, 10, 'bank_transfer', 120.00, 'TXN17635862877463', NULL, NULL, 'sxKiyGdc8wq+QtFUaJGX56Pl9p05A36y+kp4w/2ofJY=', 'dfdss', NULL, '08142591169', '][pokjhgv', 'uploads/receipts/receipt_3_1763586287_2068.jpg', 'completed', '2025-11-19 21:04:47', '2025-11-19 21:13:24'),
(7, 3, 11, 'bank_transfer', 10029.94, 'TXN17636342744447', NULL, NULL, 'F2BmNiMGNQVKjIUgTOB3jUIrXIZyAgYhdNllRwlMcQLCI/o=', '000000', NULL, '08142591169', '][pokjhgv', 'uploads/receipts/receipt_3_1763634274_8174.jpeg', 'completed', '2025-11-20 10:24:34', '2025-11-20 10:25:27'),
(8, 7, 12, 'bank_transfer', 360.00, 'TXN17643943218006', NULL, NULL, 'wf0SQsopGmevp530EliOAK+4bA9mloD/lmt66wLNtZn2A88=', '000000', NULL, '08142591169', '][pokjhgv', 'uploads/receipts/receipt_7_1764394321_1904.jpg', 'completed', '2025-11-29 05:32:01', '2025-11-29 05:37:53');

-- --------------------------------------------------------

--
-- Table structure for table `payment_settings`
--

CREATE TABLE `payment_settings` (
  `id` int(11) NOT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `account_name` varchar(255) DEFAULT NULL,
  `account_number` varchar(255) DEFAULT NULL,
  `card_number` varchar(255) DEFAULT NULL,
  `card_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `paypal_email` varchar(255) DEFAULT NULL,
  `stripe_publishable_key` varchar(255) DEFAULT NULL,
  `stripe_secret_key` varchar(255) DEFAULT NULL,
  `payment_instructions` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_settings`
--

INSERT INTO `payment_settings` (`id`, `bank_name`, `account_name`, `account_number`, `card_number`, `card_name`, `created_at`, `updated_at`, `paypal_email`, `stripe_publishable_key`, `stripe_secret_key`, `payment_instructions`) VALUES
(1, 'Fidelity bank', 'Godwill chinemerem okorie', 'NjMyNjE1MTk2Mg==', NULL, 'james', '2025-11-06 22:47:41', '2025-11-21 15:20:45', '', NULL, NULL, '');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `category_id` int(11) DEFAULT NULL,
  `colors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`colors`)),
  `sizes` varchar(255) DEFAULT NULL,
  `materials` varchar(255) DEFAULT NULL,
  `brief_details` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `price`, `image_url`, `stock`, `created_at`, `updated_at`, `category_id`, `colors`, `sizes`, `materials`, `brief_details`) VALUES
(35, 'Short Sleeves Printed Lapel Maxi Dresses', 'iconic sparkle from every angle. The Yarina maxi dress is crafted from premium glitter mesh in a chevron design and adorned with three-dimensional beads that catch the light. Cut to a flattering scoop neckline with fixed shoulder', 59.99, 'uploads/1762000227_6905fd63492f0.webp', 0, '2025-11-01 12:30:27', '2025-11-28 13:58:53', 2, '[]', NULL, NULL, NULL),
(38, 'Short Sleeves Printed Lapel Maxi Dresses', 'Iconic sparkle from every angle. The Yarina maxi dress is crafted from premium glitter mesh in a chevron design and adorned with three-dimensional beads that catch the light. Cut to a flattering scoop neckline with fixed shoulder straps, a thigh-high split, and in a slim silhouette, it\'s perfect for your most opulent events.', 75.00, 'uploads/1762000595_6905fed363f13.avif', 4, '2025-11-01 12:36:35', '2025-11-28 13:58:53', 2, '[]', NULL, NULL, NULL),
(39, 'Solara', 'Backless Halterneck Maxi Dress in Bronze', 140.00, 'uploads/1762001294_6906018ef3489.avif', 1, '2025-11-01 12:48:14', '2025-11-28 13:58:53', 2, '[]', NULL, NULL, NULL),
(40, 'Solara 2', 'Ruched Bikini Top in Bronze Gold in  swiming wear', 90.00, 'uploads/1762001412_69060204b7770.avif', 6, '2025-11-01 12:50:12', '2025-11-28 13:58:53', 2, '[]', NULL, NULL, NULL),
(41, 'BIG3 4.0 Quick \"Reverse\"', 'ENRG-X+ midsole material for high responsiveness, and Qu!kBALANCE TPU effectively prevents rollover.\r\nQu!klock lacing system ensures good lockdown, stability, and wrap-up feel of forefoot.\r\nAnti-torsion ARCHLOCK piece for good stability and support, and rubber outsole for strong traction and durability.', 190.00, 'uploads/1762378432_690bc2c051bfd.webp', 7, '2025-11-05 21:33:52', '2025-11-28 13:58:53', 8, '[]', NULL, NULL, NULL),
(42, '3(Pair ) white sock', 'Very nice and quality brand of socks', 29.94, 'uploads/1762378656_690bc3a0086f4.webp', 5, '2025-11-05 21:37:36', '2025-11-28 13:58:53', 9, '[]', NULL, NULL, NULL),
(44, '4 Pair socks', 'Quality socks', 40.00, 'uploads/1762379050_690bc52a4a585.avif', -1, '2025-11-05 21:44:10', '2025-11-28 13:58:53', NULL, '[]', NULL, NULL, NULL),
(45, 'X Elite  Desktop PC', 'SAMSUNG Galaxy Book4 Edge Blauw- 16 inch - Qualcomm X Elite - 16 GB - 512 GB - Copilot+ PC', 900.00, 'uploads/1762465122_690d156216489.jpeg', 2, '2025-11-06 21:38:42', '2025-11-28 13:58:53', 6, '[]', NULL, NULL, NULL),
(46, 'Human Hair Bundles', 'Body Wave Raw Brazilian Human Hair Bundles', 150.00, 'uploads/1762465428_690d169439321.avif', 5, '2025-11-06 21:43:48', '2025-11-28 13:58:53', 3, '[]', NULL, NULL, NULL),
(47, 'Jean Paul Gaultier', 'Jean Paul Gaultier\r\nLe Male Elixir\r\nparfum spray', 120.00, 'uploads/1762465628_690d175c90462.jpeg', 6, '2025-11-06 21:47:08', '2025-11-28 13:58:53', 4, '[]', NULL, NULL, NULL),
(48, 'CLOUD WHITE', '', 249.92, 'uploads/1762466153_690d19697888f.webp', 2, '2025-11-06 21:55:53', '2025-11-28 13:58:53', 7, '[]', NULL, NULL, NULL),
(49, 'Refurbished Laptops Compare to New laptops', '', 10000.00, 'uploads/1762466564_690d1b04c392f.png', 2, '2025-11-06 22:02:44', '2025-11-28 13:58:53', 6, '[]', NULL, NULL, NULL),
(50, 'OWIN Women\'s Sexy Summer Casual Mock Neck', 'OWIN Women\'s Sexy Summer Casual Mock Neck Dresses Sleeveless Ruched Bodycon Cocktail Party Mini Dress', 35.00, 'uploads/1763800061_692173fdaccd3.jpg', 9, '2025-11-22 08:27:41', '2025-11-28 13:58:53', 2, '[]', NULL, NULL, NULL),
(51, 'Kaximil Women\'s Square Neck Ruffle', 'Kaximil Women\'s Square Neck Ruffle Hem Mini Dress Ruched Waist Long Sleeve Corset Short Party Dresses', 35.00, 'uploads/1763801074_692177f24b5e2.jpg', 4, '2025-11-22 08:44:34', '2025-11-28 13:58:53', 2, '[]', NULL, NULL, NULL),
(52, 'GLNEGE Women\'s Summer V-Neck Long Dress', 'GLNEGE Women\'s Summer V-Neck Long Dress Casual Halter Wedding Guest Dresses Boho Flowy Smocked Sundress 2025\r\ncolors available : white, black, pink, red, yellow', 30.00, 'uploads/1763802697_69217e495242e.jpg', 9, '2025-11-22 09:11:37', '2025-11-28 13:58:53', 2, '[]', NULL, NULL, NULL),
(53, 'Women’s Fleece-Lined Tights', 'Women’s Fleece-Lined Tights - Ultra-Warm Fake Sheer Look Pantyhose Thick Leggings for Winter', 25.00, 'uploads/1763803849_692182c9ddc72.jpg', 2, '2025-11-22 09:30:49', '2025-11-28 13:58:53', 2, '[]', NULL, NULL, NULL),
(54, 'Women Sexy V Neck Sleeveless', 'Women Sexy V Neck Sleeveless Mesh Ruffle Hem Bodycon Maxi Casual Backless High Slit Cocktail Party Dress', 50.00, 'uploads/1763803981_6921834dc096f.jpg', 4, '2025-11-22 09:33:01', '2025-11-28 13:58:53', 2, '[]', NULL, NULL, NULL),
(55, 'Acer Nitro V Gaming Laptop', 'Acer Nitro V Gaming Laptop | Intel Core 9 Processor 270H | NVIDIA GeForce RTX 5070 Laptop GPU | 16\" WUXGA IPS 180Hz Display | 32GB DDR5 | 1TB Gen 4 SSD | Wi-Fi 6 | Backlit KB | ANV16-72-933F', 1700.00, 'uploads/1763804147_692183f3a4e75.webp', 5, '2025-11-22 09:35:47', '2025-11-28 13:58:53', 6, '[]', NULL, NULL, NULL),
(58, 'Apple 2025 MacBook Air 13-inch Laptop', 'Apple 2025 MacBook Air 13-inch Laptop with M4 chip: Built for Apple Intelligence, 13.6-inch Liquid Retina Display, 16GB Unified Memory, 256GB SSD Storage, 12MP Center Stage Camera, Touch ID; Midnight', 1200.00, 'uploads/1763804384_692184e0acc56.jpg', 2, '2025-11-22 09:39:44', '2025-11-28 13:58:53', 6, '[]', NULL, NULL, NULL),
(59, 'Acer Aspire Go 15 AI Ready Laptop', 'Acer Aspire Go 15 AI Ready Laptop | 15.6\" FHD (1920 x 1080) IPS Display | Intel Core 3 Processor N355 | Intel Graphics | 8GB DDR5 | 128GB UFS | Wi-Fi 6 | Windows 11 Home in S Mode | AG15-32P-39R2', 1500.00, 'uploads/1763804470_69218536e7e79.jpg', 3, '2025-11-22 09:41:10', '2025-11-28 13:58:53', 6, '[]', NULL, NULL, NULL),
(60, 'HP 17.3 inch Laptop', 'HP 17.3 inch Laptop, FHD Display, Intel Core i5-1334U, 16 GB RAM, 512 GB SSD, Intel Iris Xe Graphics, Windows 11 Home, Natural Silver, 17-cn3399nr', 700.00, 'uploads/1763821086_6921c61ec5f64.jpg', 4, '2025-11-22 14:18:06', '2025-11-28 13:58:53', 6, '[]', NULL, NULL, NULL),
(61, 'AOC Laptop Computer 15.6', 'AOC Laptop Computer 15.6\" Full HD IPS Display Laptop with N95 Processor(Up to 3.4GHz) Work Laptops 16GB RAM 512GB SSD Laptop Business Computer Light&Thin, Metal Shell, Webcam, Type-C, USB3.2', 500.00, 'uploads/1763821304_6921c6f81340c.jpg', 2, '2025-11-22 14:21:44', '2025-11-28 13:58:53', 6, '[]', NULL, NULL, NULL),
(62, 'ASUS Vivobook Go 15.6', 'ASUS Vivobook Go 15.6” FHD Slim Laptop, AMD Ryzen 3 7320U Quad Core Processor, 8GB DDR5 RAM, 128GB SSD, Windows 11 Home, Fast Charging, Webcam Sheild, Military Grade Durability, Black, E1504FA-AS33', 500.00, 'uploads/1763821410_6921c7624c45c.jpg', 2, '2025-11-22 14:23:30', '2025-11-28 13:58:53', 6, '[]', NULL, NULL, NULL),
(63, '32 Inch Water Wave', '32 Inch Water Wave 13x6 HD Lace Front Wigs Human Hair Pre Plucked 200 Density Deep Part Curly Wig for Women Water Wave Frontal Wigs Human Hair', 249.95, 'uploads/1763821962_6921c98ad06b5.webp', 15, '2025-11-22 14:32:42', '2025-11-28 13:58:53', 3, '[]', NULL, NULL, NULL),
(65, 'Lancôme La Vie Est Belle Eau de Parfum', 'Lancôme La Vie Est Belle Eau de Parfum - Long Lasting Fragrance with Notes of Iris, Earthy Patchouli, Warm Vanilla & Spun Sugar - Floral & Sweet Women\'s Perfume', 90.00, 'uploads/1763822185_6921ca692c4a8.jpg', 4, '2025-11-22 14:36:25', '2025-11-28 13:58:53', 4, '[]', NULL, NULL, NULL),
(66, 'WENNALIFE Wire Hair Extensions', 'WENNALIFE Wire Hair Extensions (Increase 50% Lifespan) Real Human Hair 14 inch 75g Balayage Dark Brown to Chestnut Remy Invisible Transparent Fish Line', 150.00, 'uploads/1763822282_6921cacacc1da.jpg', 6, '2025-11-22 14:38:02', '2025-11-28 13:58:53', 3, '[]', NULL, NULL, NULL),
(67, 'issme Clip In Hair Extensions Real Human Hairissme Clip In Hair Extensions Real Human Hair', 'issme Clip In Hair Extensions Real Human Hair,18in 120g 7pcs Balayage Dark Brown Mixed Chestnut Brown Invisible Straight Seamless Clip Ins Hair Extensions For Women', 300.00, 'uploads/1763822369_6921cb2173aa4.jpg', 6, '2025-11-22 14:39:29', '2025-11-28 13:58:53', 3, '[]', NULL, NULL, NULL),
(68, 'Versace Bright Crystal', 'Versace Bright Crystal by Versace for Women 6.7 oz Eau de Toilette Spray', 150.00, 'uploads/1763822425_6921cb5916cd6.jpg', 6, '2025-11-22 14:40:25', '2025-11-28 13:58:53', 4, '[]', NULL, NULL, NULL),
(69, 'Samsung Galaxy S25', 'Samsung Galaxy S25 FE Cell Phone (2025), 256GB AI Smartphone, Unlocked Android, Large Display, 4900mAh Battery, High Res-Camera, AI Photo Edits, Durable, US 1 Yr Warranty, Navy', 700.00, 'uploads/1763822579_6921cbf3b0982.webp', 4, '2025-11-22 14:42:59', '2025-11-28 13:58:53', 5, '[]', NULL, NULL, NULL),
(70, 'GIFENNSE Men\'s Handmade', 'GIFENNSE Men\'s Handmade Leather Modern Classic Lace up Leather Lined Perforated Dress Oxfords Shoes', 35.00, 'uploads/1763822653_6921cc3ddef4d.jpg', 16, '2025-11-22 14:44:13', '2025-11-28 13:58:53', 8, '[]', NULL, NULL, NULL),
(71, 'GIFENNSE Men\'s', 'GIFENNSE Men\'s Handmade Leather Modern Classic Lace up Leather Lined Perforated Dress Oxfords Shoes', 120.00, 'uploads/1763822727_6921cc8725cbd.jpg', 6, '2025-11-22 14:45:27', '2025-11-28 16:03:17', 8, '[\"#FF0000\",\"#A52A2A\"]', '', '', ''),
(72, 'Samsung Galaxy Z Flip7', 'Samsung Galaxy Z Flip7 Cell Phone, 512GB AI Smartphone, Unlocked Android, high-res 50 MP Camera, Powerful Processor, Long Battery Life, 2025, US 1 Yr Manufacturer Warranty, Coral Red', 400.00, 'uploads/1763822788_6921ccc4460b7.jpg', 5, '2025-11-22 14:46:28', '2025-11-28 13:58:53', 5, '[]', NULL, NULL, NULL),
(73, 'Flats for Womens', 'Flats for Womens Pointed Toe Ballet Flats with Bow Comfortable Knit Dressy Flats', 1500.00, 'uploads/1763822927_6921cd4ff1db8.jpg', 15, '2025-11-22 14:48:47', '2025-11-28 13:58:53', 8, '[]', NULL, NULL, NULL),
(74, 'Apple iPhone 14 Pro', 'Apple iPhone 14 Pro (Renewed), 128GB, Deep Purple - Unlocked', 1000.00, 'uploads/1763823032_6921cdb8008f1.webp', 5, '2025-11-22 14:50:32', '2025-11-28 13:58:53', 5, '[]', NULL, NULL, NULL),
(75, 'Apple iPhone 16 Pro Max', 'Apple iPhone 16 Pro Max, US Version, 256GB, Black Titanium - Unlocked (Renewed)', 1600.00, 'uploads/1763823102_6921cdfec81d6.jpg', 3, '2025-11-22 14:51:42', '2025-11-28 14:43:24', 5, '[\"#FF0000\",\"#0000FF\",\"#FFFF00\"]', '', '', ''),
(76, 'Sports socks', 'Merino Everyday Crew Socks - Sports socks', 20.00, 'uploads/1764247805_692848fd08c96.jpg', 13, '2025-11-27 12:50:05', '2025-11-28 14:36:38', 9, '0', 'S, M, X, XX', '', 'Buy three get one free'),
(78, 'Men\'s Quartz Watch', 'Invicta Coalition Forces 46533', 600.00, 'uploads/1765996546_6942f80278d51.webp', 15, '2025-12-17 18:35:46', '2025-12-17 18:35:46', 7, '[\"#FF0000\",\"#0000FF\",\"#FFFF00\",\"#000000\",\"#FFFFFF\",\"#808080\",\"#A52A2A\"]', '', '', 'Buy three get one free'),
(79, 'grand-step-shoes', 'Grand Step Shoes-Rio - Sneakers', 120.00, 'uploads/1765996802_6942f9021acb0.jpg', 4, '2025-12-17 18:40:02', '2025-12-17 18:40:02', 8, '[\"#0000FF\",\"#FFFF00\",\"#000000\",\"#FFFFFF\"]', '42, 43, 44, 45', '', ''),
(80, 'Invicta Angel 48249', 'Women\'s Quartz Watch', 70.00, 'uploads/1765997022_6942f9de5364c.webp', 6, '2025-12-17 18:43:42', '2025-12-17 18:43:42', 7, '[\"#0000FF\",\"#FFFFFF\"]', '', '', 'Buy three get one free'),
(81, 'Doghammer', 'Local Wool Commuter - Sneakers', 80.00, 'uploads/1765997235_6942fab38d14d.jpg', 4, '2025-12-17 18:47:15', '2025-12-17 18:47:15', 8, '[\"#FFFF00\",\"#000000\",\"#FFFFFF\"]', '', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `support_messages`
--

CREATE TABLE `support_messages` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_admin` tinyint(1) DEFAULT 0,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `support_messages`
--

INSERT INTO `support_messages` (`id`, `ticket_id`, `user_id`, `message`, `is_admin`, `is_read`, `created_at`) VALUES
(7, 2, 3, 'Why is my product not yet delivered??', 0, 1, '2025-11-21 17:34:32'),
(12, 5, 7, 'wefgh', 0, 1, '2025-11-29 06:13:09'),
(13, 5, 6, 'hey', 1, 1, '2025-11-29 06:13:38'),
(14, 5, 6, 'hey', 1, 0, '2025-11-29 06:14:23');

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `status` enum('open','pending','closed') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `support_tickets`
--

INSERT INTO `support_tickets` (`id`, `user_id`, `subject`, `category`, `priority`, `status`, `created_at`, `updated_at`) VALUES
(2, 3, 'delay delivery', 'Shipping', 'high', 'open', '2025-11-21 17:34:32', '2025-11-29 05:58:27'),
(5, 7, 'deley delivery', 'Payment', 'medium', 'pending', '2025-11-29 06:13:09', '2025-11-29 06:14:23');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `profile_pic` varchar(500) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `profile_pic`, `phone`, `address`, `role`, `created_at`) VALUES
(1, 'admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, NULL, 'admin', '2025-10-31 11:11:19'),
(3, 'godwillchinemere44@gmail.com', 'godwillchinemere44@gmail.com', '$2y$10$ri.ghc4yb4NTtp/DmvEl4evTAX3UWvBzpJmr2wJygJwJoUpdtX0g6', 'uploads/profiles/user_3_1763794884.png', NULL, NULL, 'user', '2025-10-31 11:16:17'),
(4, 'godwillchinemere@gmail.com', 'godwillchinemere@gmail.com', '$2y$10$lTBsqVuYdoxxJJMkChPNJeSk1vcDVbTWKr1V962Ws2BhayWM1kGDu', NULL, NULL, NULL, 'admin', '2025-10-31 12:35:57'),
(5, 'chinemeregodwill@gmail.com', 'chinemeregodwill@gmail.com', '$2y$10$odO4ncXB4E2fWt3U.W3q6.4xfADCQWlC3Rd7V232KT5jtNWr4bOrm', 'uploads/profiles/user_5_1764604791.jpg', NULL, NULL, 'admin', '2025-11-05 18:48:39'),
(6, 'sale', 'sale@gmail.com', '$2y$10$0Db9W4EsipdjpMIpHSz6fuybvnQAEryD/FEHmUvCtGLIOmOT4qX9m', 'uploads/profiles/user_6_1764241647.jpg', NULL, NULL, 'admin', '2025-11-17 09:59:03'),
(7, 'buy1', 'buy1@gmail.com', '$2y$10$m49g2OCgBRfQNWrJt08fUuA.Ecx0XT5xlGBQ.xBbiMwZKiLZ1/Qau', 'uploads/profiles/user_7_1763678223.jpg', NULL, NULL, 'user', '2025-11-20 22:14:13');

-- --------------------------------------------------------

--
-- Table structure for table `user_activity_log`
--

CREATE TABLE `user_activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action_type` enum('profile_update','role_change','password_reset','account_deletion') NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_activity_log`
--

INSERT INTO `user_activity_log` (`id`, `user_id`, `admin_id`, `action_type`, `old_value`, `new_value`, `description`, `created_at`) VALUES
(1, 4, 4, 'profile_update', '{\"username\":\"godwillchinemere@gmail.com\",\"email\":\"godwillchinemere@gmail.com\"}', '{\"username\":\"godwillchinemere@gmail.com\",\"email\":\"godwillchinemere@gmail.com\"}', 'Profile updated: ', '2025-11-03 19:13:47');

-- --------------------------------------------------------

--
-- Table structure for table `user_notifications`
--

CREATE TABLE `user_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','warning','success','danger') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_notifications`
--

INSERT INTO `user_notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 3, 'Welcome!', 'Your account settings page is now ready with notification support.', 'success', 1, '2025-11-03 19:12:28'),
(2, 4, 'Profile Updated', 'Your profile information was updated by an administrator. Please review your account settings.', 'info', 0, '2025-11-03 19:13:47'),
(3, 1, 'New Payment Receipt - Order #5', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $869.92. Transaction: TXN17635843241715', 'warning', 0, '2025-11-19 20:32:04'),
(4, 4, 'New Payment Receipt - Order #5', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $869.92. Transaction: TXN17635843241715', 'warning', 0, '2025-11-19 20:32:04'),
(5, 5, 'New Payment Receipt - Order #5', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $869.92. Transaction: TXN17635843241715', 'warning', 0, '2025-11-19 20:32:04'),
(6, 6, 'New Payment Receipt - Order #5', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $869.92. Transaction: TXN17635843241715', 'warning', 0, '2025-11-19 20:32:04'),
(7, 1, 'New Payment Receipt - Order #6', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $120.00. Transaction: TXN17635849416189', 'warning', 0, '2025-11-19 20:42:21'),
(8, 4, 'New Payment Receipt - Order #6', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $120.00. Transaction: TXN17635849416189', 'warning', 0, '2025-11-19 20:42:21'),
(9, 5, 'New Payment Receipt - Order #6', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $120.00. Transaction: TXN17635849416189', 'warning', 0, '2025-11-19 20:42:21'),
(10, 6, 'New Payment Receipt - Order #6', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $120.00. Transaction: TXN17635849416189', 'warning', 0, '2025-11-19 20:42:21'),
(11, 1, 'New Payment Receipt - Order #7', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $120.00. Transaction: TXN17635852976714', 'warning', 0, '2025-11-19 20:48:17'),
(12, 4, 'New Payment Receipt - Order #7', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $120.00. Transaction: TXN17635852976714', 'warning', 0, '2025-11-19 20:48:17'),
(13, 5, 'New Payment Receipt - Order #7', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $120.00. Transaction: TXN17635852976714', 'warning', 0, '2025-11-19 20:48:17'),
(14, 6, 'New Payment Receipt - Order #7', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $120.00. Transaction: TXN17635852976714', 'warning', 0, '2025-11-19 20:48:17'),
(15, 1, 'New Payment Receipt - Order #8', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $249.92. Transaction: TXN17635857669247', 'warning', 0, '2025-11-19 20:56:06'),
(16, 4, 'New Payment Receipt - Order #8', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $249.92. Transaction: TXN17635857669247', 'warning', 0, '2025-11-19 20:56:06'),
(17, 5, 'New Payment Receipt - Order #8', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $249.92. Transaction: TXN17635857669247', 'warning', 0, '2025-11-19 20:56:06'),
(18, 6, 'New Payment Receipt - Order #8', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $249.92. Transaction: TXN17635857669247', 'warning', 0, '2025-11-19 20:56:06'),
(19, 1, 'New Payment Receipt - Order #9', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $249.92. Transaction: TXN17635859907223', 'warning', 0, '2025-11-19 20:59:50'),
(20, 4, 'New Payment Receipt - Order #9', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $249.92. Transaction: TXN17635859907223', 'warning', 0, '2025-11-19 20:59:50'),
(21, 5, 'New Payment Receipt - Order #9', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $249.92. Transaction: TXN17635859907223', 'warning', 0, '2025-11-19 20:59:50'),
(22, 6, 'New Payment Receipt - Order #9', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $249.92. Transaction: TXN17635859907223', 'warning', 0, '2025-11-19 20:59:50'),
(23, 1, 'New Payment Receipt - Order #10', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $120.00. Transaction: TXN17635862877463', 'warning', 0, '2025-11-19 21:04:47'),
(24, 4, 'New Payment Receipt - Order #10', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $120.00. Transaction: TXN17635862877463', 'warning', 0, '2025-11-19 21:04:47'),
(25, 5, 'New Payment Receipt - Order #10', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $120.00. Transaction: TXN17635862877463', 'warning', 0, '2025-11-19 21:04:47'),
(26, 6, 'New Payment Receipt - Order #10', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $120.00. Transaction: TXN17635862877463', 'warning', 0, '2025-11-19 21:04:47'),
(27, 3, '✅ Payment Approved!', 'Your payment for Order #10 has been verified and approved! Your order will be processed shortly.', 'success', 0, '2025-11-19 21:13:24'),
(28, 1, 'New Payment Receipt - Order #11', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $10,029.94. Transaction: TXN17636342744447', 'warning', 0, '2025-11-20 10:24:34'),
(29, 4, 'New Payment Receipt - Order #11', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $10,029.94. Transaction: TXN17636342744447', 'warning', 0, '2025-11-20 10:24:34'),
(30, 5, 'New Payment Receipt - Order #11', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $10,029.94. Transaction: TXN17636342744447', 'warning', 0, '2025-11-20 10:24:34'),
(31, 6, 'New Payment Receipt - Order #11', 'User godwillchinemere44@gmail.com submitted Bank transfer payment of $10,029.94. Transaction: TXN17636342744447', 'warning', 0, '2025-11-20 10:24:34'),
(32, 3, '✅ Payment Approved!', 'Your payment for Order #11 has been verified and approved! Your order will be processed shortly.', 'success', 0, '2025-11-20 10:25:27'),
(33, 3, '✅ Payment Approved!', 'Your payment for Order #11 has been verified and approved! Your order will be processed shortly.', 'success', 0, '2025-11-20 11:28:59'),
(34, 3, 'receive', 'HI', 'info', 0, '2025-11-21 18:03:13'),
(35, 1, 'New Payment Receipt - Order #12', 'User buy1 submitted Bank transfer payment of $360.00. Transaction: TXN17643943218006', 'warning', 0, '2025-11-29 05:32:01'),
(36, 4, 'New Payment Receipt - Order #12', 'User buy1 submitted Bank transfer payment of $360.00. Transaction: TXN17643943218006', 'warning', 0, '2025-11-29 05:32:01'),
(37, 5, 'New Payment Receipt - Order #12', 'User buy1 submitted Bank transfer payment of $360.00. Transaction: TXN17643943218006', 'warning', 0, '2025-11-29 05:32:01'),
(38, 6, 'New Payment Receipt - Order #12', 'User buy1 submitted Bank transfer payment of $360.00. Transaction: TXN17643943218006', 'warning', 0, '2025-11-29 05:32:01'),
(39, 7, '✅ Payment Approved!', 'Your payment for Order #12 has been verified and approved! Your order will be processed shortly.', 'success', 0, '2025-11-29 05:37:53');

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wishlist`
--

INSERT INTO `wishlist` (`id`, `user_id`, `product_id`, `created_at`) VALUES
(7, 3, 48, '2025-11-17 16:26:38'),
(8, 7, 38, '2025-11-22 08:33:48'),
(9, 7, 55, '2025-11-22 14:13:38'),
(10, 3, 74, '2025-11-27 12:28:23'),
(12, 3, 38, '2025-12-01 22:02:40'),
(13, 3, 76, '2025-12-02 13:15:22');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `order_tracking_history`
--
ALTER TABLE `order_tracking_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `payment_settings`
--
ALTER TABLE `payment_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `support_messages`
--
ALTER TABLE `support_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`),
  ADD KEY `priority` (`priority`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_activity_log`
--
ALTER TABLE `user_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_wishlist` (`user_id`,`product_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `order_tracking_history`
--
ALTER TABLE `order_tracking_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `payment_settings`
--
ALTER TABLE `payment_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT for table `support_messages`
--
ALTER TABLE `support_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user_activity_log`
--
ALTER TABLE `user_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_notifications`
--
ALTER TABLE `user_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_order_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_tracking_history`
--
ALTER TABLE `order_tracking_history`
  ADD CONSTRAINT `order_tracking_history_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_order_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payments_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `support_messages`
--
ALTER TABLE `support_messages`
  ADD CONSTRAINT `messages_ticket_fk` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD CONSTRAINT `tickets_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_activity_log`
--
ALTER TABLE `user_activity_log`
  ADD CONSTRAINT `activity_log_admin_fk` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `activity_log_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD CONSTRAINT `notifications_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `wishlist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wishlist_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
