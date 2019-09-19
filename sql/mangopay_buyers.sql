CREATE TABLE `mangopay_buyers` (
  `id` int(9) unsigned NOT NULL auto_increment,
  `user_id` int(9) unsigned NOT NULL default 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `name` varchar(128) NOT NULL default '',
  `email` varchar(128) NOT NULL default '',
  `ukey` varchar(128) NOT NULL default '',
  `wkey` varchar(128) NOT NULL default '',
  `bkey` varchar(128) NOT NULL default '',
  `status` varchar(128) NOT NULL default '',
  PRIMARY KEY  (`id`),
  KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;