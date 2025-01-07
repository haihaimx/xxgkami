-- 创建卡密表
CREATE TABLE IF NOT EXISTS `cards` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `card_key` varchar(32) NOT NULL,
    `status` tinyint(1) NOT NULL DEFAULT '0',
    `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `use_time` datetime DEFAULT NULL,
    `expire_time` datetime DEFAULT NULL,
    `duration` int(11) NOT NULL DEFAULT '0',
    `verify_method` enum('web','post','get') DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `card_key` (`card_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建管理员表
CREATE TABLE IF NOT EXISTS `admins` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL,
    `password` varchar(255) NOT NULL,
    `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_login` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建系统设置表
CREATE TABLE IF NOT EXISTS `settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(50) NOT NULL,
    `value` text NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 插入默认设置
INSERT INTO `settings` (`name`, `value`) VALUES
('site_title', '卡密验证系统'),
('site_subtitle', '专业的卡密验证解决方案'),
('copyright_text', '小小怪卡密系统 - All Rights Reserved'),
('contact_qq_group', '123456789'),
('contact_wechat_qr', 'assets/images/wechat-qr.jpg'),
('contact_email', 'support@example.com'),
('api_enabled', '0'),
('welcome_message', '欢迎使用卡密验证系统'),
('welcome_duration', '3000'),
('welcome_enabled', '1'),
('api_key', SUBSTRING(MD5(RAND()) FROM 1 FOR 32)) 
ON DUPLICATE KEY UPDATE `value`=`value`;

-- 创建轮播图表
CREATE TABLE IF NOT EXISTS `slides` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `title` varchar(100) NOT NULL,
    `description` varchar(255) NOT NULL,
    `image_url` varchar(255) NOT NULL,
    `sort_order` int(11) NOT NULL DEFAULT '0',
    `status` tinyint(1) NOT NULL DEFAULT '1',
    `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 插入默认轮播图数据
INSERT INTO `slides` (`title`, `description`, `image_url`, `sort_order`) VALUES 
('安全可靠的验证系统', '采用先进的加密技术，确保您的数据安全', 'assets/images/slide1.jpg', 1),
('便捷高效的验证流程', '支持多种验证方式，快速响应', 'assets/images/slide2.jpg', 2),
('完整的API接口', '提供丰富的接口，便于集成', 'assets/images/slide3.jpg', 3); 

-- 创建系统特点表
CREATE TABLE IF NOT EXISTS `features` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `icon` varchar(50) NOT NULL,
    `title` varchar(100) NOT NULL,
    `description` text NOT NULL,
    `sort_order` int(11) NOT NULL DEFAULT '0',
    `status` tinyint(1) NOT NULL DEFAULT '1',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 插入默认系统特点
INSERT INTO `features` (`icon`, `title`, `description`, `sort_order`) VALUES 
('fas fa-shield-alt', '安全可靠', '采用先进的加密技术，确保卡密数据安全\n数据加密存储\n防暴力破解\n安全性验证', 1),
('fas fa-code', 'API接口', '提供完整的API接口，支持多种验证方式\nRESTful API\n多种验证方式\n详细接口文档', 2),
('fas fa-tachometer-alt', '高效稳定', '系统运行稳定，响应迅速\n快速响应\n稳定运行\n性能优化', 3),
('fas fa-chart-line', '数据统计', '详细的数据统计和分析功能\n实时统计\n数据分析\n图表展示', 4); 