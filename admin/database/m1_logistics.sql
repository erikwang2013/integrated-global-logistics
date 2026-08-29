-- M1 物流聚合管理后台增量 SQL
-- 依赖 install.sql 已执行（logistics_admin_permission / logistics_admin_role / logistics_admin_role_permission）
-- 执行方式：mysql -u<user> -p <db> < m1_logistics.sql

SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------
-- 物流承运商表
-- -------------------------------------------
DROP TABLE IF EXISTS `logistics_carrier`;

CREATE TABLE `logistics_carrier` (
  `id` bigint unsigned NOT NULL COMMENT '主键ID，由snowflake生成',
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '承运商代码（对应global-logistics包内代码，如 sf/dhl）',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '承运商名称',
  `channel` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'domestic' COMMENT '通道: domestic=国内 international=国际',
  `country` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '所属国家/地区',
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Logo URL',
  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态: 0=禁用 1=启用',
  `timeout_ms` int unsigned NOT NULL DEFAULT '5000' COMMENT '查询超时（毫秒）',
  `cache_ttl` int unsigned NOT NULL DEFAULT '300' COMMENT '轨迹缓存时间（秒）',
  `sort` int unsigned NOT NULL DEFAULT '0' COMMENT '排序值，越小越靠前',
  `remark` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '备注',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_channel` (`channel`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='物流承运商表';

-- -------------------------------------------
-- 承运商凭证表（app_key/app_secret 加密存储，Encryptable 自动加解密）
-- -------------------------------------------
DROP TABLE IF EXISTS `logistics_carrier_credential`;

CREATE TABLE `logistics_carrier_credential` (
  `id` bigint unsigned NOT NULL COMMENT '主键ID，由snowflake生成',
  `carrier_id` bigint unsigned NOT NULL COMMENT '承运商ID',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '凭证名称',
  `app_key` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'App Key（加密存储）',
  `app_secret` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'App Secret（加密存储）',
  `extra` json DEFAULT NULL COMMENT '扩展参数（JSON，如 endpoint/partner_id 等）',
  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态: 0=禁用 1=启用',
  `weight` int unsigned NOT NULL DEFAULT '100' COMMENT '路由权重，加权随机选择凭证',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_carrier_id` (`carrier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='承运商凭证表';

-- -------------------------------------------
-- 轨迹查询记录表
-- -------------------------------------------
DROP TABLE IF EXISTS `logistics_tracking_query`;

CREATE TABLE `logistics_tracking_query` (
  `id` bigint unsigned NOT NULL COMMENT '主键ID，由snowflake生成',
  `query_no` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '查询流水号',
  `carrier_id` bigint unsigned NOT NULL DEFAULT '0' COMMENT '承运商ID',
  `carrier_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '承运商代码',
  `tracking_no` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '运单号',
  `credential_id` bigint unsigned NOT NULL DEFAULT '0' COMMENT '使用的凭证ID',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'success' COMMENT '查询状态: success=成功 fail=失败',
  `result` json DEFAULT NULL COMMENT '查询结果（统一Tracking结构）',
  `raw_response` text COLLATE utf8mb4_unicode_ci COMMENT '承运商原始响应',
  `query_source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'api' COMMENT '查询来源: api=接口 admin=后台 webhook=回调',
  `cost_ms` int unsigned NOT NULL DEFAULT '0' COMMENT '查询耗时（毫秒）',
  `error_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '错误码',
  `error_message` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '错误信息',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_query_no` (`query_no`),
  KEY `idx_carrier_code` (`carrier_code`),
  KEY `idx_tracking_no` (`tracking_no`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='轨迹查询记录表';

-- -------------------------------------------
-- 回调订阅表
-- -------------------------------------------
DROP TABLE IF EXISTS `logistics_callback_subscription`;

CREATE TABLE `logistics_callback_subscription` (
  `id` bigint unsigned NOT NULL COMMENT '主键ID，由snowflake生成',
  `carrier_id` bigint unsigned NOT NULL COMMENT '承运商ID',
  `callback_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '回调URL',
  `secret` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '回调签名密钥',
  `event_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tracking.update' COMMENT '事件类型: tracking.update=轨迹更新',
  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态: 0=禁用 1=启用',
  `max_retry` tinyint unsigned NOT NULL DEFAULT '3' COMMENT '最大重试次数',
  `last_push_at` datetime DEFAULT NULL COMMENT '上次推送时间',
  `last_success_at` datetime DEFAULT NULL COMMENT '上次成功推送时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_carrier_id` (`carrier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='回调订阅表';

-- -------------------------------------------
-- M7 客户端表
-- -------------------------------------------
DROP TABLE IF EXISTS `logistics_client`;

CREATE TABLE `logistics_client` (
  `id` bigint unsigned NOT NULL COMMENT '主键ID，由snowflake生成',
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '用户名',
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '密码（bcrypt哈希）',
  `contact_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '联系人姓名',
  `contact_phone` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '联系电话',
  `contact_email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '联系邮箱',
  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态: 0=禁用 1=启用',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客户端表';

-- -------------------------------------------
-- 客户端应用表
-- -------------------------------------------
DROP TABLE IF EXISTS `logistics_client_app`;

CREATE TABLE `logistics_client_app` (
  `id` bigint unsigned NOT NULL COMMENT '主键ID，由snowflake生成',
  `client_id` bigint unsigned NOT NULL COMMENT '所属客户端ID',
  `appid` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '应用公开标识（服务端生成，对外展示）',
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '应用名称',
  `purpose` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '申请用途说明',
  `api_key_sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'API密钥sha256哈希（hex），明文仅创建时返回一次',
  `plan_id` bigint unsigned NOT NULL DEFAULT '0' COMMENT '当前套餐ID（0=未开通）',
  `valid_days` int unsigned NOT NULL DEFAULT '0' COMMENT '审核通过后有效期天数',
  `expire_at` datetime DEFAULT NULL COMMENT '有效期截止时间（UTC，审核通过时=now+valid_days）',
  `review_remark` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '审核备注（驳回原因等）',
  `reviewed_by` bigint unsigned NOT NULL DEFAULT '0' COMMENT '审核人管理用户ID',
  `reviewed_at` datetime DEFAULT NULL COMMENT '审核时间',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态: pending=待审核 approved=已通过 rejected=已驳回 disabled=已禁用',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_appid` (`appid`),
  KEY `idx_client_id` (`client_id`),
  KEY `idx_status` (`status`),
  KEY `idx_expire_at` (`expire_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客户端应用表';

-- -------------------------------------------
-- 套餐表（种子 3 档：体验 7天 / 基础 30天 / 专业 365天）
-- -------------------------------------------
DROP TABLE IF EXISTS `logistics_plan`;

CREATE TABLE `logistics_plan` (
  `id` bigint unsigned NOT NULL COMMENT '主键ID，由snowflake生成',
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '套餐名称',
  `price` int unsigned NOT NULL DEFAULT '0' COMMENT '价格（分）',
  `valid_days` int unsigned NOT NULL DEFAULT '0' COMMENT '有效期（天）',
  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态: 0=停售 1=在售',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='套餐表';

INSERT INTO `logistics_plan` (`id`, `name`, `price`, `valid_days`, `status`, `created_at`, `updated_at`) VALUES
('22000000000000001', '体验套餐', 0, 7, 1, NOW(), NOW()),
('22000000000000002', '基础套餐', 9900, 30, 1, NOW(), NOW()),
('22000000000000003', '专业套餐', 39900, 365, 1, NOW(), NOW());

-- -------------------------------------------
-- 订单表
-- -------------------------------------------
DROP TABLE IF EXISTS `logistics_order`;

CREATE TABLE `logistics_order` (
  `id` bigint unsigned NOT NULL COMMENT '主键ID，由snowflake生成',
  `order_no` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '订单号',
  `client_id` bigint unsigned NOT NULL COMMENT '客户端ID',
  `app_id` bigint unsigned NOT NULL COMMENT '应用ID',
  `plan_id` bigint unsigned NOT NULL COMMENT '套餐ID',
  `amount` int unsigned NOT NULL DEFAULT '0' COMMENT '订单金额（分）',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态: pending=待支付 paid=已支付 cancelled=已取消',
  `paid_at` datetime DEFAULT NULL COMMENT '支付时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_client_id` (`client_id`),
  KEY `idx_app_id` (`app_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单表';

-- -------------------------------------------
-- M1 权限种子（菜单 type=1 + API type=3，ID 沿用 install.sql 的 snowflake 分段约定）
-- -------------------------------------------
INSERT INTO `logistics_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
('21000000000000101', '0', '物流服务商', 'carrier', 1, 'truck', '/admin/carrier', 7, NOW(), NOW()),
('21000000000000102', '0', '查询记录', 'tracking-query', 1, 'search', '/admin/tracking/query', 8, NOW(), NOW()),
('21000000000000103', '0', '回调订阅', 'callback-subscription', 1, 'link', '/admin/callback/subscription', 9, NOW(), NOW()),
('21000000000000104', '0', '统计报表', 'tracking-statistics', 1, 'chart', '/admin/tracking/statistics', 10, NOW(), NOW()),
('21000000000000111', '21000000000000101', '查看承运商', 'get.admin/carrier', 3, '', '', 1, NOW(), NOW()),
('21000000000000112', '21000000000000101', '创建承运商', 'post.admin/carrier', 3, '', '', 2, NOW(), NOW()),
('21000000000000113', '21000000000000101', '更新承运商', 'put.admin/carrier', 3, '', '', 3, NOW(), NOW()),
('21000000000000114', '21000000000000101', '删除承运商', 'delete.admin/carrier', 3, '', '', 4, NOW(), NOW()),
('21000000000000121', '21000000000000101', '查看凭证', 'get.admin/carrier/credential', 3, '', '', 5, NOW(), NOW()),
('21000000000000122', '21000000000000101', '创建凭证', 'post.admin/carrier/credential', 3, '', '', 6, NOW(), NOW()),
('21000000000000123', '21000000000000101', '更新凭证', 'put.admin/carrier/credential', 3, '', '', 7, NOW(), NOW()),
('21000000000000124', '21000000000000101', '删除凭证', 'delete.admin/carrier/credential', 3, '', '', 8, NOW(), NOW()),
('21000000000000131', '21000000000000102', '查看查询记录', 'get.admin/tracking/query', 3, '', '', 1, NOW(), NOW()),
('21000000000000141', '21000000000000103', '查看订阅', 'get.admin/callback/subscription', 3, '', '', 1, NOW(), NOW()),
('21000000000000142', '21000000000000103', '创建订阅', 'post.admin/callback/subscription', 3, '', '', 2, NOW(), NOW()),
('21000000000000143', '21000000000000103', '更新订阅', 'put.admin/callback/subscription', 3, '', '', 3, NOW(), NOW()),
('21000000000000144', '21000000000000103', '删除订阅', 'delete.admin/callback/subscription', 3, '', '', 4, NOW(), NOW()),
('21000000000000151', '21000000000000104', '查看统计', 'get.admin/tracking/statistics', 3, '', '', 1, NOW(), NOW());

-- 超级管理员授予 M1 新权限（沿用 install.sql 的显式授权方式）
INSERT INTO `logistics_admin_role_permission` (`role_id`, `permission_id`) VALUES
('10000000000000001', '21000000000000101'),
('10000000000000001', '21000000000000102'),
('10000000000000001', '21000000000000103'),
('10000000000000001', '21000000000000104'),
('10000000000000001', '21000000000000111'),
('10000000000000001', '21000000000000112'),
('10000000000000001', '21000000000000113'),
('10000000000000001', '21000000000000114'),
('10000000000000001', '21000000000000121'),
('10000000000000001', '21000000000000122'),
('10000000000000001', '21000000000000123'),
('10000000000000001', '21000000000000124'),
('10000000000000001', '21000000000000131'),
('10000000000000001', '21000000000000141'),
('10000000000000001', '21000000000000142'),
('10000000000000001', '21000000000000143'),
('10000000000000001', '21000000000000144'),
('10000000000000001', '21000000000000151');

-- -------------------------------------------
-- M7 客户端管理权限种子
-- -------------------------------------------
INSERT INTO `logistics_admin_permission` (`id`, `parent_id`, `name`, `slug`, `type`, `icon`, `path`, `sort`, `created_at`, `updated_at`) VALUES
('21000000000000105', '0', '客户端管理', 'client', 1, 'people', '/admin/client', 11, NOW(), NOW()),
('21000000000000161', '21000000000000105', '查看客户端', 'get.admin/client', 3, '', '', 1, NOW(), NOW()),
('21000000000000162', '21000000000000105', '查看应用', 'get.admin/client/app', 3, '', '', 2, NOW(), NOW()),
('21000000000000163', '21000000000000105', '审核应用', 'post.admin/client/app/review', 3, '', '', 3, NOW(), NOW()),
('21000000000000164', '21000000000000105', '禁用应用', 'post.admin/client/app/disable', 3, '', '', 4, NOW(), NOW());

INSERT INTO `logistics_admin_role_permission` (`role_id`, `permission_id`) VALUES
('10000000000000001', '21000000000000105'),
('10000000000000001', '21000000000000161'),
('10000000000000001', '21000000000000162'),
('10000000000000001', '21000000000000163'),
('10000000000000001', '21000000000000164');

SET FOREIGN_KEY_CHECKS = 1;
