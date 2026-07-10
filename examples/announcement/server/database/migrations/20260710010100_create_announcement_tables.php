<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateAnnouncementTables extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE `sm_announcement` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `organization` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '机构编号，0 表示平台公告',
  `title` varchar(200) NOT NULL COMMENT '公告标题',
  `summary` varchar(500) NOT NULL DEFAULT '' COMMENT '公告摘要',
  `content` longtext NOT NULL COMMENT '公告内容',
  `display_mode` varchar(20) NOT NULL DEFAULT 'list' COMMENT '展示方式:list,popup,both',
  `priority` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '优先级',
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态:1草稿,2已发布,3已下线',
  `start_time` datetime NULL DEFAULT NULL COMMENT '开始展示时间',
  `end_time` datetime NULL DEFAULT NULL COMMENT '结束展示时间',
  `published_at` datetime NULL DEFAULT NULL COMMENT '最近发布时间',
  `created_by` int(11) UNSIGNED NULL DEFAULT NULL COMMENT '创建人',
  `updated_by` int(11) UNSIGNED NULL DEFAULT NULL COMMENT '更新人',
  `create_time` datetime NULL DEFAULT NULL COMMENT '创建时间',
  `update_time` datetime NULL DEFAULT NULL COMMENT '更新时间',
  `delete_time` datetime NULL DEFAULT NULL COMMENT '软删除时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_organization_status_window` (`organization`, `status`, `start_time`, `end_time`) USING BTREE,
  KEY `idx_organization_priority` (`organization`, `priority`, `id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='公告表' ROW_FORMAT=DYNAMIC;
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE `sm_announcement_read` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `organization` int(11) UNSIGNED NOT NULL COMMENT '机构编号',
  `announcement_id` bigint(20) UNSIGNED NOT NULL COMMENT '公告主键',
  `user_id` varchar(64) NOT NULL COMMENT '用户编号',
  `read_time` datetime NOT NULL COMMENT '已读时间',
  `create_time` datetime NULL DEFAULT NULL COMMENT '创建时间',
  `update_time` datetime NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uni_organization_announcement_user` (`organization`, `announcement_id`, `user_id`) USING BTREE,
  KEY `idx_organization_user_read` (`organization`, `user_id`, `read_time`) USING BTREE,
  KEY `idx_organization_announcement` (`organization`, `announcement_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='公告已读表' ROW_FORMAT=DYNAMIC;
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `sm_announcement_read`');
        $this->execute('DROP TABLE IF EXISTS `sm_announcement`');
    }
}
