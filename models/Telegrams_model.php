<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Telegrams_model extends App_Model
{
    private $statuses;

    public function __construct()
    {
        parent::__construct();

        $this->load->model('projects_model');
        $this->load->model('staff_model');

        $this->statuses = hooks()->apply_filters('before_set_telegram_statuses', [
            1,
            2,
            5,
            3,
            4,
        ]);   
    }

    function get_project_members($id){

        $this->db->select([
           'CONCAT(' .db_prefix().'staff.firstname," ", ' . db_prefix().'staff.lastname) AS "staff_name"',
        ]);

        $this->db->join(db_prefix() . 'staff', db_prefix() . 'staff.staffid = ' . db_prefix() . 'project_members.staff_id');
        $this->db->where(db_prefix() . 'project_members.project_id', $id);

        //return $this->db->get_compiled_select(db_prefix() . 'project_members');

        $project_members =  $this->db->get(db_prefix() . 'project_members')->result();

        return $project_members;
    }
}
