<?php 

use Anam\PhantomMagick\Converter;

defined('BASEPATH') or exit('No direct script access allowed');

class Mytelegram extends ClientsController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('telegrams_model');
        $this->load->model('clients_model');
        $this->load->model('projects_model');
    }


    public function html2image($id = '')
    {
        $hash = 'skjq989l8888---kjkjkjjjj';


        $data['title']                 = _l('telegrams_message');
        
        $conv = new \Anam\PhantomMagick\Converter();
        $conv->source('http://crm.local/telegrams/Mytelegram/html2image/3')
        ->toJpg()
    //    ->download('file-'.date('d-m-y-H-i-s').'.jpg');
        ->save('/media/Data/website/crm/public_html/uploads/telegrams/3/file-'.date('d-m-y-H-i-s').'.jpg');

        $this->view('themes/'. active_clients_theme() .'/views/telegrams/html2image');
        $this->disableNavigation();
        $this->disableSubMenu();
        no_index_customers_area();
        $this->layout();


    }

}
