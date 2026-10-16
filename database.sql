-- ========================================================
-- FindIt - Lost & Found Portal Database Schema (MySQL)
-- Compatible with XAMPP, WAMP, Laragon, MySQL 5.7+ / 8.0+
-- ========================================================

CREATE DATABASE IF NOT EXISTS `lost_and_found` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `lost_and_found`;

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(20) DEFAULT 'user',
  `phone` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `items`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `type` VARCHAR(10) NOT NULL COMMENT 'lost or found',
  `title` VARCHAR(255) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `description` TEXT NOT NULL,
  `location` VARCHAR(255) NOT NULL,
  `item_date` DATE NOT NULL,
  `image_path` VARCHAR(255) DEFAULT NULL,
  `contact_name` VARCHAR(100) NOT NULL,
  `contact_email` VARCHAR(100) NOT NULL,
  `contact_phone` VARCHAR(50) DEFAULT NULL,
  `status` VARCHAR(20) DEFAULT 'active' COMMENT 'active, claimed, returned',
  `security_secret` VARCHAR(64) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_items_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `claims`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `claims` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `item_id` INT NOT NULL,
  `claimant_id` INT DEFAULT NULL,
  `claimant_name` VARCHAR(100) NOT NULL,
  `claimant_email` VARCHAR(100) NOT NULL,
  `claimant_phone` VARCHAR(50) DEFAULT NULL,
  `claim_message` TEXT NOT NULL,
  `status` VARCHAR(20) DEFAULT 'pending' COMMENT 'pending, approved, rejected',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_claims_items` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_claims_users` FOREIGN KEY (`claimant_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Seed Initial Users
-- Passwords:
-- admin@findit.local -> admin123
-- john@example.com  -> password123
-- --------------------------------------------------------
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `phone`) VALUES
(1, 'System Administrator', 'admin@findit.local', '$2y$10$eE.l1kO/t.iVq6rXm6e0Z.N2N4aWJm1vN7uB3yL8Z9kX4n2v9V0eO', 'admin', '+1 (555) 000-1111'),
(2, 'John Doe', 'john@example.com', '$2y$10$wT3wYtN7Oq5E5V2L.0Gj0uO/h1P2Y3Z4A5B6C7D8E9F0G1H2I3J4K', 'user', '+1 (555) 234-5678')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- --------------------------------------------------------
-- Seed Sample Items
-- --------------------------------------------------------
INSERT INTO `items` (`id`, `user_id`, `type`, `title`, `category`, `description`, `location`, `item_date`, `image_path`, `contact_name`, `contact_email`, `contact_phone`, `status`, `security_secret`) VALUES
(1, 2, 'found', 'Apple iPhone 13 (Midnight Blue)', 'electronics', 'Found on a wooden bench near the central courtyard gazebo. Has a transparent silicone case with a holographic sticker. Battery is charged, currently stored safely.', 'Central Courtyard - East Pavilion', CURDATE() - INTERVAL 2 DAY, NULL, 'John Doe', 'john@example.com', '+1 (555) 234-5678', 'active', MD5(RAND())),
(2, 2, 'lost', 'Brown Leather Bi-fold Wallet', 'wallets', 'Lost my vintage brown leather wallet during lunch hour. Contains driver license under name John Doe, metro pass, and some family photos. Huge sentimental value.', 'University Cafeteria, Booth 12', CURDATE() - INTERVAL 3 DAY, NULL, 'John Doe', 'john@example.com', '+1 (555) 234-5678', 'active', MD5(RAND())),
(3, 1, 'found', 'Set of Car Keys with Honda Keychain', 'keys', 'Found black key fob with Honda logo and two silver keys attached to a red lanyard in parking sector B.', 'North Parking Garage Level 2', CURDATE() - INTERVAL 1 DAY, NULL, 'Security Front Desk', 'admin@findit.local', '+1 (555) 000-1111', 'active', MD5(RAND())),
(4, 1, 'lost', 'Golden Retriever Puppy (Buddy)', 'pets', 'Buddy is 8 months old, wearing a blue collar with a bell. Last seen near Oakridge Park entrance. Very energetic and friendly.', 'Oakridge Neighborhood / Pine St', CURDATE() - INTERVAL 1 DAY, NULL, 'Elena Rostova', 'elena@example.com', '+1 (555) 012-7788', 'active', MD5(RAND())),
(5, 1, 'found', 'Black Ray-Ban Aviator Sunglasses', 'clothing', 'Found in a protective hard case on table 4 at the main cafe. Gold frames with polarized dark lenses.', 'Main Street Coffee Bar', CURDATE() - INTERVAL 4 DAY, NULL, 'Cafe Manager', 'cafe@example.com', '+1 (555) 018-9900', 'active', MD5(RAND())),
(6, 2, 'found', 'Calculus & Linear Algebra Textbook (9th Ed)', 'books', 'Hardcover university math textbook left on row 3 desk. Has yellow highlighter markings in Chapter 4.', 'Science Complex, Lecture Hall B', CURDATE() - INTERVAL 5 DAY, NULL, 'Department Helpdesk', 'helpdesk@example.com', '+1 (555) 011-3322', 'claimed', MD5(RAND()))
ON DUPLICATE KEY UPDATE `id`=`id`;

-- --------------------------------------------------------
-- Seed Sample Claim
-- --------------------------------------------------------
INSERT INTO `claims` (`id`, `item_id`, `claimant_id`, `claimant_name`, `claimant_email`, `claimant_phone`, `claim_message`, `status`) VALUES
(1, 1, 1, 'Alex Morgan', 'alex.morgan@example.com', '+1 (555) 998-1122', 'I lost my blue iPhone 13 there! The lock screen wallpaper is a picture of a sunset in Maui, and the passcode starts with 49.', 'pending')
ON DUPLICATE KEY UPDATE `id`=`id`;
