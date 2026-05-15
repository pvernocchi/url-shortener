CREATE INDEX IF NOT EXISTS `idx_links_owner` ON `{prefix}links` (`owner_id`);
CREATE INDEX IF NOT EXISTS `idx_clicks_link_time` ON `{prefix}click_events` (`link_id`, `clicked_at`);
CREATE INDEX IF NOT EXISTS `idx_clicks_link` ON `{prefix}click_events` (`link_id`);
