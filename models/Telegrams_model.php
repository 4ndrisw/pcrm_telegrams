<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Telegrams_model extends App_Model
{
    private $statuses;

    private $shipping_fields = ['shipping_street', 'shipping_city', 'shipping_city', 'shipping_state', 'shipping_zip', 'shipping_country'];

    public function __construct()
    {
        parent::__construct();

        $this->statuses = hooks()->apply_filters('before_set_telegram_statuses', [
            1,
            2,
            5,
            3,
            4,
        ]);   
    }
}
