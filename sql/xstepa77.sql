CREATE TABLE `attributes` (
                              `id` int(11) NOT NULL AUTO_INCREMENT,
                              `category_id` int(11) NOT NULL,
                              `name` varchar(255) NOT NULL,
                              `type` enum('text','number','date') NOT NULL,
                              `is_required` tinyint(1) NOT NULL DEFAULT 0,
                              PRIMARY KEY (`id`),
                              KEY `category_id` (`category_id`)
);

CREATE TABLE `categories` (
                              `id` int(11) NOT NULL AUTO_INCREMENT,
                              `name` varchar(255) NOT NULL,
                              `parent_id` int(11) DEFAULT NULL,
                              PRIMARY KEY (`id`),
                              KEY `parent_id` (`parent_id`)
);

CREATE TABLE `categoryproposals` (
                                     `id` int(11) NOT NULL AUTO_INCREMENT,
                                     `name` varchar(255) NOT NULL,
                                     `parent_id` int(11) DEFAULT NULL,
                                     `user_id` int(11) NOT NULL,
                                     `status` enum('pending','approved','rejected') DEFAULT 'pending',
                                     PRIMARY KEY (`id`),
                                     KEY `user_id` (`user_id`)
);

CREATE TABLE `eventapplications` (
                                     `id` int(11) NOT NULL AUTO_INCREMENT,
                                     `event_id` int(11) NOT NULL,
                                     `application_date` datetime DEFAULT CURRENT_TIMESTAMP,
                                     `reviewed_date` datetime DEFAULT NULL,
                                     `reviewer_id` int(11) DEFAULT NULL,
                                     PRIMARY KEY (`id`),
                                     KEY `event_id` (`event_id`)
);

CREATE TABLE `eventimages` (
                               `id` int(11) NOT NULL AUTO_INCREMENT,
                               `event_id` int(11) NOT NULL,
                               `image_path` varchar(255) NOT NULL,
                               `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                               PRIMARY KEY (`id`),
                               KEY `event_id` (`event_id`)
);

CREATE TABLE `events` (
                          `id` int(11) NOT NULL AUTO_INCREMENT,
                          `name` varchar(255) NOT NULL,
                          `location` varchar(255) NOT NULL,
                          `date` date NOT NULL,
                          `organizer_id` int(11) NOT NULL,
                          `description` text DEFAULT NULL,
                          PRIMARY KEY (`id`)
);

CREATE TABLE `farmerapplications` (
                                      `id` int(11) NOT NULL AUTO_INCREMENT,
                                      `user_id` int(11) NOT NULL,
                                      `status` enum('pending','approved','rejected') DEFAULT 'pending',
                                      `application_date` datetime DEFAULT CURRENT_TIMESTAMP,
                                      `reviewed_date` datetime DEFAULT NULL,
                                      `reviewer_id` int(11) DEFAULT NULL,
                                      `application_text` text NOT NULL,
                                      `comments` text DEFAULT NULL,
                                      PRIMARY KEY (`id`),
                                      KEY `user_id` (`user_id`)
);

CREATE TABLE `notifications` (
                                 `id` int(11) NOT NULL AUTO_INCREMENT,
                                 `user_id` int(11) NOT NULL,
                                 `event_id` int(11) NOT NULL,
                                 `is_read` tinyint(1) NOT NULL DEFAULT 0,
                                 `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                 PRIMARY KEY (`id`),
                                 KEY `user_id` (`user_id`),
                                 KEY `event_id` (`event_id`)
);

CREATE TABLE `orderitems` (
                              `id` int(11) NOT NULL AUTO_INCREMENT,
                              `order_id` int(11) NOT NULL,
                              `product_id` int(11) NOT NULL,
                              `quantity` decimal(10,2) NOT NULL,
                              `quantity_unit` varchar(50) NOT NULL,
                              `price_per_unit` decimal(10,2) NOT NULL,
                              `price_unit` varchar(50) NOT NULL,
                              PRIMARY KEY (`id`),
                              KEY `order_id` (`order_id`),
                              KEY `product_id` (`product_id`)
);

CREATE TABLE `orders` (
                          `id` int(11) NOT NULL AUTO_INCREMENT,
                          `customer_id` int(11) NOT NULL,
                          `total_price` decimal(10,2) NOT NULL,
                          `status` enum('pending','approved','declined','completed') DEFAULT 'pending',
                          `order_date` datetime DEFAULT CURRENT_TIMESTAMP,
                          PRIMARY KEY (`id`),
                          KEY `customer_id` (`customer_id`)
);

CREATE TABLE `productattributes` (
                                     `id` int(11) NOT NULL AUTO_INCREMENT,
                                     `product_id` int(11) NOT NULL,
                                     `attribute_id` int(11) NOT NULL,
                                     `value` text NOT NULL,
                                     PRIMARY KEY (`id`),
                                     KEY `product_id` (`product_id`),
                                     KEY `attribute_id` (`attribute_id`)
);

CREATE TABLE `productimages` (
                                 `id` int(11) NOT NULL AUTO_INCREMENT,
                                 `product_id` int(11) NOT NULL,
                                 `image_path` varchar(255) NOT NULL,
                                 PRIMARY KEY (`id`),
                                 KEY `product_id` (`product_id`)
);

CREATE TABLE `products` (
                            `id` int(11) NOT NULL AUTO_INCREMENT,
                            `name` varchar(255) NOT NULL,
                            `category_id` int(11) NOT NULL,
                            `farmer_id` int(11) NOT NULL,
                            `price` decimal(10,2) NOT NULL,
                            `price_unit` enum('per_unit','per_kg','per_liter','per_meter','per_pack','other') DEFAULT 'per_unit',
                            `quantity` decimal(10,2) NOT NULL,
                            `quantity_unit` varchar(50) NOT NULL,
                            `description` text DEFAULT NULL,
                            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            PRIMARY KEY (`id`),
                            KEY `category_id` (`category_id`),
                            KEY `farmer_id` (`farmer_id`)
);

CREATE TABLE `reviews` (
                           `id` int(11) NOT NULL AUTO_INCREMENT,
                           `product_id` int(11) NOT NULL,
                           `user_id` int(11) NOT NULL,
                           `rating` int(11) DEFAULT NULL,
                           `comment` text DEFAULT NULL,
                           PRIMARY KEY (`id`),
                           KEY `product_id` (`product_id`),
                           KEY `user_id` (`user_id`)
);

CREATE TABLE `userinterests` (
                                 `id` int(11) NOT NULL AUTO_INCREMENT,
                                 `user_id` int(11) NOT NULL,
                                 `event_id` int(11) NOT NULL,
                                 `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                 PRIMARY KEY (`id`),
                                 KEY `user_id` (`user_id`),
                                 KEY `event_id` (`event_id`)
);

CREATE TABLE `users` (
                         `id` int(11) NOT NULL AUTO_INCREMENT,
                         `name` varchar(255) NOT NULL,
                         `email` varchar(255) NOT NULL UNIQUE,
                         `phone` varchar(15) DEFAULT NULL,
                         `photo_path` varchar(255) DEFAULT NULL,
                         `password` varchar(255) NOT NULL,
                         `role` enum('admin','moderator','farmer','customer') DEFAULT 'customer',
                         PRIMARY KEY (`id`)
);
