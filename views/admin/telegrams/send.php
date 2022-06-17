<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
   <div class="content">
      <div class="row">
         <div class="col-md-12">
            <div class="panel_s">
               <div class="panel-body">
                  <?php 
                     echo '<pre>';
                     $insert_id = 292;
                     $message = telegrams_after_add_project($insert_id);
                     
                     if(!empty($message)){
                        json_encode($message);
                        var_dump( $message );
                     }
                     
                     echo '</pre>';
                     

                  ?>
                  <hr />
                  Something went wrong...
                  <?php if($this->session->has_userdata('not_found_equiptment_model')){ ?>
                     <div class="alert alert-danger">
                       <strong>Danger!</strong> <?php echo $this->session->userdata('not_found_equiptment_model'); ?>.
                     </div>
                     <?php $this->session->unset_userdata('not_found_equiptment_model');?>
                  <?php } ?>
               </div>
            </div>
         </div>
      </div>
   </div>
</div>
<?php init_tail(); ?>
</body>
</html>
