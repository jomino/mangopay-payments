CREATE TABLE `mangopay_events` (
  `id` int(9) unsigned NOT NULL auto_increment,
  `buyer_id` int(9) unsigned NOT NULL default 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `status` varchar(32) NOT NULL default '',
  `token` varchar(256) NOT NULL default '',
  `amount` smallint unsigned NOT NULL default 0,
  `product` varchar(128) NOT NULL default '',
  `pikey` varchar(128) NOT NULL default '',
  `trkey` varchar(128) NOT NULL default '',
  `pokey` varchar(128) NOT NULL default '',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;