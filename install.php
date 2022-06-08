<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!$CI->db->table_exists(db_prefix() . 'telegrams')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'telegrams` (
  `id` int(11) NOT NULL,
  `staffid` int(11) DEFAULT NULL,
  `messages` text NOT NULL,
  `dateadded` datetime NOT NULL DEFAULT current_timestamp()

) ENGINE=InnoDB DEFAULT CHARSET=' . $CI->db->char_set . ';');

    $CI->db->query('ALTER TABLE `' . db_prefix() . 'telegrams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staffid` (`staffid`);');

$CI->db->query('ALTER TABLE `' . db_prefix() . 'telegrams`

MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;');
   
}


