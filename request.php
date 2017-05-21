<?php
class APIRequest {

   //Add method name which is not required login
    var $publicMethods = array('login', 'logout', 'forgot_password', 'signup','nearest_greengrocers','search_greengrocers','states','store_detail','advsearch_greengrocers','inseason','inseason_list','inseason_detail','recipes_season','listrecipes','recipesdetail','listproduce','pushnotification','produce_detail','promotions','contactus','terms_policy','aboutus','search_greengrocers_newfunction','editmylistitems1','update_storelocator','update_seasondata','notification_setting','terms_policy','inseason_list_live','auto_empty_token');

    
    public function signup()
    {
        global $wpdb;

        if(!$this->_post('name'))
        {
            throw new Exception('Please enter your Name.',400);
        }

        if(!$this->_post('email'))
        {
            throw new Exception('Please enter your Email ID.',400);
        } else {
          if(email_exists($this->_post('email'))){
            throw new Exception('User already exist with this Email ID!',400);
          }
        }

        if(!$this->_post('password'))
        {
            throw new Exception('Please enter your Password.',400);

        } else {
                
                if( strlen($this->_post('password')) < 6 ) {
                  throw new Exception('Password must be 6 characters long and have at least one numeric character.',400);
                }

                if( !preg_match("#[0-9]+#", $this->_post('password')) ) {
                  throw new Exception('Password must be 6 characters long and have at least one numeric character.',400);
                }

                if( !preg_match("#[a-z]+#", $this->_post('password')) ) {
                    throw new Exception('Password must be 6 characters long and have at least one numeric character.',400);
                }

                
                // if( preg_match("#\W+#", $this->_post('password')) ) {
                //     throw new Exception('Password must not include any symbol!',400);
                // }
                
                //  [!@#$%^&*()\-_=+{};:,<.>]

                if( preg_match("#[!$%^&*()\-_=+{};:,<.>]#", $this->_post('password')) ) {
                    throw new Exception('Password must not include any symbol!',400);
                }

        }

        // if(!$this->_post('confirm_password'))
        // {
        //     throw new Exception('Confirm password must be required!',400);
        // }

        // if($this->_post('password') != $this->_post('confirm_password'))
        // {
        //     throw new Exception('Confirm password does not match with password!',400);
        // }

        //Create user
        $user_id = wp_create_user($this->_post('email'), $this->_post('password'), $this->_post('email'));
        if (is_wp_error($user_id)) {
            throw new Exception(strip_tags($user_id->get_error_message()),400);
        }

        //Send username and password to created user
        wp_new_user_notification($user_id, $this->_post('password'));

        //Update user details
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $this->_post('name'),
            'first_name' => $this->_post('name')
        ));

        //Update user meta
        add_user_meta($user_id, 'users_email', $this->_post('email'));
        
        add_user_meta($user_id, 'notification', 'on');

        // wp_set_current_user($user_id);
        // wp_set_auth_cookie($user_id);
        // do_action('wp_login');
      
        // $app_token = wp_generate_password(16, false, false).time();

        // $wpdb->insert($wpdb->prefix.'users_token', array(
        //     "user_id" => $user_id,
        //     "token" => $app_token
        // ));
       
        $return =  array(
            'id' => $user_id,
            'name' => get_user_meta($user_id, 'first_name', true),
            'email' => get_user_meta($user_id, 'users_email', true),
          //  'token' => $app_token
        );

        return $return;
    }


  
    public function login() {

        global $wpdb;
        if(!$this->_post('email')) {
            throw new Exception('Email must be required!',400);
        }

        if($this->_post('fbid') && $this->_post('fbid') != '') {  // FACEBOOK REGISTRATION.........
            $user_id = username_exists($this->_post('email') );
            if ( !$user_id and email_exists($this->_post('email')) == false ) {
              //$random_password = 'testing#123';
              $random_password = wp_generate_password(8, false, false).time();
              $user_id = wp_create_user($this->_post('email'), $random_password, $this->_post('email') );
              wp_set_current_user($user_id);
              update_user_meta($user_id, 'fbid',$this->_post('fbid'));
              wp_update_user( array( 'ID' => $user_id, 'display_name' =>  $this->_post('name')) );
            } 
        } else {
          if(!$this->_post('password')) {
            throw new Exception('Password must be required!',400);
          }  
        }
        

        if($this->_post('fbid') == '') {  // NORMAL USER LOGIN .........
          $user = wp_signon( array('user_login' => $this->_post('email'), 'user_password' => $this->_post('password') ), false );

        } else {   // FACEBOOK LOGIN .........

          global $current_user;
            $user_id = username_exists($this->_post('email') );
            $fbid = get_user_meta($user_id, 'fbid', true);
            if($fbid != $this->_post('fbid')){
                throw new Exception("Incorrect Email ID or Password!",400); 
            }
            wp_set_current_user($user_id);
            $user = $this->getuserbyEmail($this->_post('email'));

            wp_update_user( array( 'ID' =>$user_id, 'display_name' =>  $this->_post('name')) );
            
            $user->data->user_login = $user->user_login;
            $user->data->display_name = $user->display_name;

        }

        if(is_wp_error($user))
        {
            throw new Exception("Incorrect Email ID or Password!",400); 
        } else {
            $return = array(
                'id' => $user->ID,
                'nickname' => $user->data->display_name,
                'email' => $user->data->user_login,
          //      'token' => $app_token,
            );
            return $return;
        }
    }


    public function attach_device_id(){

          global $wpdb,$current_user;

          if(is_user_logged_in()  && $current_user->ID == $this->_post('user_id') ) {

              $device_id = $this->_post('device_id');
              $device_type = strtolower($this->_post('device_type'));
              $user_id = $this->_post('user_id');

             $data = $wpdb->get_row("SELECT id,device_os,device_id,token FROM wp_users_token WHERE user_id='$user_id' ORDER BY id DESC limit 1",ARRAY_A);

             
              $device_id_exist = $data['device_id'];


              if($device_type == 'ios'){
                $result =  $wpdb->update($wpdb->prefix."users_token", array("device_os" => strtolower($device_type),"device_id" => $device_id), array("device_id" => $device_id_exist ));
              }
              
              if($device_type == 'android'){
                $result =  $wpdb->update($wpdb->prefix."users_token", array("device_os" => strtolower($device_type),"device_id" => $device_id), array("device_id" => $device_id_exist ));
              } 
            
            



        } else {
            throw new Exception('User must be loggedin to view favourite store!',400);
        }

        $return = array(
          'message' => 'device information attached.'
        );

        return $return;

    }



     public function forgot_password()
    {
      

        $data = $this->retrieve_password($_POST['email']);
        if ( is_wp_error($data) )
        {
            $error = $data->get_error_messages();
            throw new Exception(strip_tags($error[0]),400);
        } else {

             $return = array(
                'message' => 'Check your e-mail for the confirmation link.'
            );

        }

        return  $return;
    }


        /**
     * Change Password
     * @param current_password
     * @param new_password
     * @return array
     * @throws Exception
     */
    public function change_password()
    {

       global $current_user;


        if($this->_post('fbid') == '') {

          if(!$this->_post('current_password'))
          {
              throw new Exception('Please enter your Current Password!',400);
          }
          $fbId = '';
        } else {
          $fbId = $this->_post('fbid');

        }
        if(!$this->_post('new_password'))
        {
            throw new Exception('Please enter new password!',400);
        } 


        if($fbId == '') {
        
            if(wp_check_password($this->_post('current_password'), $current_user->data->user_pass, $current_user->ID) == false)
            {
                throw new Exception('Please enter correct Current Password!',400);
            } 
 
        } 


        $name = $this->_post('name');

                /*
                if( strlen($this->_post('new_password')) < 6 ) {
                  throw new Exception('Password must be alphanumeric with at least 6 characters long!',400);
                }

                if( !preg_match("#[0-9]+#", $this->_post('new_password')) ) {
                  throw new Exception('Password must be alphanumeric with at least 6 characters long!',400);
                }

                if( !preg_match("#[a-z]+#", $this->_post('new_password')) ) {
                    throw new Exception('Password must be alphanumeric with at least 6 characters long!',400);
                }

                if( preg_match("#\W+#", $this->_post('new_password')) ) {
                    throw new Exception('Password must not include any symbol!',400);
                }
                  */

                if( strlen($this->_post('new_password')) < 6 ) {
                  throw new Exception('Password must be alphanumeric with at least 6 characters long!',400);
                }

                if( !preg_match("#[0-9]+#", $this->_post('new_password')) ) {
                  throw new Exception('Password must be alphanumeric with at least 6 characters long!',400);
                }

                if( !preg_match("#[a-z]+#", $this->_post('new_password')) ) {
                    throw new Exception('Password must be alphanumeric with at least 6 characters long!',400);
                }
                
                if( preg_match("#[!$%^&*()\-_=+{};:,<.>]#", $this->_post('new_password')) ) {
                    throw new Exception('Password must not include any symbol!',400);
                }






       

     

        if($name != ''){
          wp_update_user( array( 'ID' => $current_user->ID, 'display_name' =>   $name) );
        }

        wp_set_password($this->_post('new_password'), $current_user->ID);
       
        $return = array(
                'message' => 'Password has been changed successfully.'
        );

         return $return;

    }


   

    /*
     * @return array
     * @throws Exception
    */
    public function nearest_greengrocers()
    {
        global $wpdb; $result = array();

        if(!$this->_post('lat'))
        {
            throw new Exception('Latitude must be required!',400);
        }

        if(!$this->_post('lng'))
        {
            throw new Exception('Longitude must be required!',400);
        }

        $center_lng = $this->_post('lng');
        $center_lat = $this->_post('lat');
        $radius = 5;
        
        $num_initial_displayed= '25';
        $sl_custom_fields = '' ;
        $multiplier=3959;
        $multiplier=($sl_vars['distance_unit']=="km")? ($multiplier*1.609344) : $multiplier;
        $sl_param_where_clause="";
   
        $extra_services = $wpdb->get_results(
        "SELECT sl_id as sl_map_id ,sl_address, sl_address2, sl_store, sl_city, sl_state, sl_zip, sl_latitude,sl_longitude, sl_description, sl_url, sl_hours, sl_phone, sl_fax, sl_email, sl_image, sl_tags, IF(sl_linked_postid  IS NULL , '0' , sl_linked_postid ) as sl_id ".
        " $sl_custom_fields,".
        " ( $multiplier * acos( cos( radians($center_lat) ) * cos( radians( sl_latitude ) ) * cos( radians( sl_longitude ) - radians($center_lng) ) + sin( radians($center_lat) ) * sin( radians( sl_latitude ) ) ) ) AS sl_distance".
        " FROM wp_store_locator WHERE sl_store<>'' AND sl_longitude<>'' AND sl_latitude<>''".
        " $sl_param_where_clause".
        " HAVING sl_distance < $radius ORDER BY sl_distance LIMIT $num_initial_displayed");

        

        if(!empty($extra_services))
        {
          /**************** for store image *******************/                
            foreach ($extra_services as $key => $value) {

                if($value->sl_id != 0){
                       $logo = get_post_meta($value->sl_id, '_atl_greengrocer_logo');
                      $logoid = array_filter($logo);
                      $logoid = reset($logoid);
                      $datalogo = get_greengrocerposts($logoid);
                      $logo = $datalogo[0]->guid;
                      if ($logo) {
                        $logo = aq_resize($logo, 220);
                      } else {
                        $logo = '';
                      }
                       // $value->sl_image = $logo;

                        if($logo != ''){
                          $value->sl_image = $logo;
                        } else {
                          $value->sl_image = ""; 
                        }


                }

              
                $result[] = $value;
              

              }
                $extra_services = $result;
          /**************** for store image *******************/                


          return $extra_services;

        } else {
          
          return array();  
        
        }

        

    }





 
    #################################  START SHOPPING RELATED WEBSERVICES  ###############################

    public function addshoppinglist(){
        
        global $wpdb,$current_user;
        
        if(!$this->_post('title'))
        {
            throw new Exception('Title must be required!',400);
        }

        if(!empty($current_user)) {
     
          $res = $wpdb->insert($wpdb->prefix.'shopping_list', array(
              "title" => $this->_post('title'),
              "created_by" => $current_user->ID,
              "created_date" => date_i18n('Y-m-d H:i:s')
          ));

          if(!$res){
            throw new Exception('Insertion failed!',400);
          }

        } else {
            throw new Exception('User must be loggedin to add shoppinglist!',400);
        }

        
        $list = (array)$this->getList($wpdb->insert_id);

        // return array(
        //   'list' => $list, 
        //   'message' => "Shopping list created."
        // );

        return  $list ;

    } 

    public function addshoppinglistitem(){

        global $wpdb,$current_user;
        
        if(!$this->_post('id'))
        {
            throw new Exception('Shoppinglist id must be required!',400);
        } else {

          if(!is_numeric($this->_post('id')))
          {
            throw new Exception('Bad request',400);
          }

        }
        if(!$this->_post('title'))
        {
            throw new Exception('Title must be required!',400);
        }
        if(!$this->_post('qty'))
        {
            throw new Exception('Item quantity  must be required!',400);
        }

        if(is_user_logged_in ()) {

            $checkdata = $this->isExistindb($this->_post('id'));  //check if id exist in table....

            $list = $this->getList($this->_post('id'));

            if($checkdata != '0') {

                $res = $wpdb->insert($wpdb->prefix.'shopping_list_detail', array(
                    "title" => $this->_post('title'),
                    "qty" => $this->_post('qty'),
                    "list_id" => (int)trim($this->_post('id')),
                    "created_date" => date_i18n('Y-m-d H:i:s')
                ));

                 // $itemslist = $this->shoppingListinfotest($wpdb->insert_id);

                 //          foreach ($itemslist as $key => $value) {
                           

                 //              $resultd = $wpdb->insert($wpdb->prefix.'shopping_share_item', array(
                 //                "list_id" =>$this->_post('id'),
                 //                "shared_to" => $this->_post('email'),
                 //                "item_id" => $value->id,
                 //                "created_date" => date_i18n('Y-m-d H:i:s')
                 //              ));


                 //          }





                if(!$res){
                   throw new Exception('Insertion failed!',400);
                }

                $ws = 'addshoppinglistitem';$title =$this->_post('id');
                $actionby = $current_user->ID;$ownerid=$list->created_by;

                $p = $this->sendPush($this->_post('id'),$ws,$title,$actionby,$ownerid);

            } else {
                throw new Exception('Bad request',400);
            }

        } else {
            throw new Exception('User must be loggedin to add item in shoppinglist!',400);
        }


        $list = $this->shoppingListinfo($wpdb->insert_id);

  

        if(!empty($list[0])){
          return (array) $list[0];  
        } else {
          throw new Exception('Insertion failed!',400);
        }
        
        // return array(
        //   'message' => "Items has benn added successfully to your shopping list."
        // );

    }
      public function mylist(){
        
        global $wpdb,$current_user;
        
          if(is_user_logged_in ()) {

            $shoppingList = $this->get_mylist($current_user->ID);

            if($shoppingList != 0){
               
                foreach($shoppingList as $key => $value)
                {
                    $list[$key] =  $value;
                }

                return  $list;

            } else {

             // throw new Exception("Not Found",404);
              return array();
            }
          
          } else {

            throw new Exception('User must be loggedin to view shoppinglist!',400);

          }

    } 


  
    public function shoppinglist(){
        
        global $wpdb,$current_user;
      
      if(is_user_logged_in ()) {

        if(!$this->_post('id')) {

            throw new Exception('Shoppinglist id must be required!',400);

        } 

        if(!is_numeric($this->_post('id')))
        {
          throw new Exception('Bad request',400);
        }
            
       

              $shoppingList = $this->get_shoppingListdetail($this->_post('id'));

              if($shoppingList != 0){
                 
                  foreach($shoppingList as $key => $value)
                  {
                      $list[$key] =  $value;
                  }

                  return  $list;

              } else {

                //throw new Exception("Not Found",404);
                return array();
              }
            
      } else {

        throw new Exception('User must be loggedin to view shoppinglist!',400);

      }

    } 

    
    public function shared_shoppinglist(){

          global $wpdb,$current_user;
      
          if(is_user_logged_in ()) {

            if(!$this->_post('id')) {

                throw new Exception('Shoppinglist id must be required!',400);

            } 

            if(!is_numeric($this->_post('id')))
            {
              throw new Exception('Bad request',400);
            }
                
           

                  $shoppingList = $this->get_sharedshoppingListdetail($this->_post('id'));

                  if($shoppingList != 0){
                     
                      foreach($shoppingList as $key => $value)
                      {
                          $list[$key] =  $value;
                      }

                      return  $list;

                  } else {

                    //throw new Exception("Not Found",404);
                    return array();
                  }
                
          } else {

            throw new Exception('User must be loggedin to view shoppinglist!',400);

          }

    }



    public function itemdetails() {

       global $wpdb,$current_user;
            
          if(is_user_logged_in ()) {

            if(!$this->_post('id')) {

                throw new Exception('Shoppinglist id must be required!',400);

            } 

            if(!is_numeric($this->_post('id')))
            {
              throw new Exception('Bad request',400);
            }
                
         

              $shoppingList = $this->shoppingListinfo($this->_post('id'));

              if($shoppingList != 0){
                 
                  foreach($shoppingList as $key => $value)
                  {
                      $list[$key] =  $value;
                  }

                  return  $list;

              } else {

                return array();

              }
            
            } else {

              throw new Exception('User must be loggedin to view shoppinglist!',400);

          }
    }

    public function editmylisttitle() {
        
        global $wpdb,$current_user;

          if(is_user_logged_in ()) {

            if(!$this->_post('id')) {
                throw new Exception('Shoppinglist id must be required!',400);
            } 

            if(!is_numeric($this->_post('id')))
            {
              throw new Exception('Bad request',400);
            }

            if(!$this->_post('title')) {
                throw new Exception('Title must be required!',400);
            } 
              
            if(!is_string($this->_post('title'))) {
                throw new Exception('Please enter valid Title!',400);
            } 

         
            $checkdata = $this->isExistindbbyme($this->_post('id'));  //check if id exist in table....

            if($checkdata != '0') {

              $result =  $wpdb->update($wpdb->prefix."shopping_list", array("title" => $this->_post('title')), array("id" => $this->_post('id')));

              if(!$result){
                throw new Exception('Shoppinglist title not updated!',400);  
              }


                $ws = 'editmylisttitle'; $title=$this->_post('id');
                $actionby = $current_user->ID;$ownerid='';
                $p = $this->sendPush($this->_post('id'),$ws,$title,$actionby,$ownerid);



            } else {
              throw new Exception('Bad request',400);
            }

          } else { 

             throw new Exception('User must be loggedin to view shoppinglist!',400);
          
          }

          $return = array(
            'message' => 'Shoppinglist title has been updated successfully'
          );

        return $return;

    } 


    public function editmylistitems() {
          
        global $wpdb,$current_user;

        $content = file_get_contents('php://input');
        
        $data = json_decode($content);

        if(!empty($data)) {

          foreach ($data->data as $key => $value) {
           // print_r($value);

            $ownerid = $value->created_by;
          
            if($current_user->ID == $ownerid) {

            $result =  $wpdb->update($wpdb->prefix."shopping_list_detail", array("title" => $value->title,"qty" => $value->qty) , array("id" => $value->id,"list_id" => $value->list_id));
    
              
              $lid = $value->list_id;
                      

            } else {  

               throw new Exception('Only owner of this listing can edit listitems!',400);

             }

          }
          
            $ws = 'editmylistitems';$title=$lid;
            $actionby = $current_user->ID;

            $p = $this->sendPush($value->list_id,$ws,$title,$actionby,$ownerid);
          


        } else {

          throw new Exception('Bad request',400);

        }

        $return = array(
          'message' => 'Shoppinglistitem has been updated successfully'
        );

        return $return;
    } 





    public function sharelist(){

      global $wpdb,$current_user;

      if(is_user_logged_in ()) {

        if(!$this->_post('id')) {
            throw new Exception('Shoppinglist id must be required!',400);
        } 

        if(!is_numeric($this->_post('id')))
        {
          throw new Exception('Bad request',400);
        }

        if(!$this->_post('email')) {
            throw new Exception('Email must be required!',400);
        } 

        if (!filter_var($this->_post('email'), FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter valid email!',400);
        }

        $checkdata = $this->getList($this->_post('id'));  //check if id exist in table....
        
        if($checkdata != '0') {

          //if($checkdata->created_by == $current_user->ID){ // im logged in and also im onwner of this

            if(strtolower($this->_post('email')) != strtolower($current_user->data->user_login) ) { // same email, own email
             
              // IF EXIST THEN UPDATE ELSE INSERT

                    $shared = $this->isSharedwiththisuser($current_user->ID,$this->_post('email'),$this->_post('id'));
                    
                    $userid = $this->getuserbyEmail($this->_post('email'));

                    if($userid  != '') {
                      if($userid->ID == $checkdata->created_by){
                         throw new Exception('Can not share with yourself!',400);
                      }
                    }

                    if($shared == 0){


                          $result = $wpdb->insert($wpdb->prefix.'shopping_share', array(
                              "shared_from" =>$current_user->ID,
                              "shared_to" => $this->_post('email'),
                              "list_id" => $this->_post('id'),
                              "created_date" => date_i18n('Y-m-d H:i:s')
                          ));

                              $itemslist = $this->shoppingListinfotest($this->_post('id'));

                          // foreach ($itemslist as $key => $value) {
                           

                          //     $resultd = $wpdb->insert($wpdb->prefix.'shopping_share_item', array(
                          //       "list_id" =>$this->_post('id'),
                          //       "shared_to" => $this->_post('email'),
                          //       "item_id" => $value->id,
                          //       "created_date" => date_i18n('Y-m-d H:i:s')
                          //     ));


                          // }

                          
                          if($result){  
                           // if(email_exists( $this->_post('email') ) ) {
                                 //$userid = $this->getuserbyEmail($this->_post('email'));
                                  if(!empty($userid )){

                                    $notification = get_user_meta($userid->ID, 'notification', true);
                                    if($notification == 'on') {
                                    
                                    $data = $wpdb->get_row("SELECT id,device_os,device_id,token FROM wp_users_token WHERE user_id='$userid->ID' ORDER BY id DESC limit 1",ARRAY_A);

                                  

                                    if(!empty($data )) 
                                    {
                                      $device_os = $data['device_os'];
                                      $device_id = $data['device_id'];
                                      $token = $data['token'];
                                      if($device_os  != '' && $device_id != '') {

                                        $lst = $this->getList($this->_post('id'));
                                     
                                        if($device_os == 'android'){
                                          $notification = array (
                                            'body'   => $current_user->data->display_name." shared shopping list with you !!",
                                            'title'   => "FindFresh" 
                                          );

                                          $msg = array(
                                          'notification_type' => 'shopping_list',
                                          'event_type' => 'add',
                                          'body'   => $current_user->data->display_name." shared shopping list with you !!",
                                          'created_by' => $lst->created_by,
                                          'id' => $lst->id,
                                          'title' => $lst->title
                                          );
                                        
                                        } else {
                                           $notification = $current_user->data->display_name." shared shopping list with you !!";
                                          
                                             $msg = array(
                                          'notification_type' => 'shopping_list',
                                          'event_type' => 'add',
                                          'body'   => $current_user->data->display_name." shared shopping list with you !!",
                                          'event_details' => $lst
                                        );
                                        


                                        }

                                        $this->pushnotification($device_os,$device_id,$msg,$notification);
                                      }
                                    }


                                    } // notification settings flag
                                  } 

                                  else {

                                  

                              // send email....... with app download link.

                              $androidapp_url  = get_option( 'android_url' );
                              $iosapp_url = get_option( 'ios_url' );
                              $headers[] = 'Content-Type: text/html; charset=UTF-8';
                              $headers[] = 'From: tesst'. "\r\n";


                              $message .="Hi,<br /><br />Your friend shared his/her shopping list with you. <br /><strong>Android application download link : </strong>".$androidapp_url."
                            <strong>iOs application download link : </strong>".$iosapp_url."";
                              $message .= "\n\n\n\r\n";
                              $message .= __('If this was a mistake, just ignore this email and nothing will happen.') . "\r\n\r\n\r\n";

                             
                              if ( $message && !wp_mail( $this->_post('email'), "tesst shoppinglist", $message, $headers ) )

                                wp_die( __('The e-mail could not be sent.') . "<br />\n" . __('Possible reason: your host may have disabled the mail() function.') );




                              
                            }
                           

                          } // end of result = 0 check

                    } else {

                         throw new Exception('Already shared with this User!',400);  
                    }



              if(!$result){
                throw new Exception('Shoppinglist already shared or sharing unsuccess!',400);  
              }

            } else {
               throw new Exception('Can not share with yourself!',400);
            }

          // } else {

          //     throw new Exception('Only owner of this listing can share it with others!',400);

          // }
             
        } else {

          return array();
          
        }

      
     


        $return = array(
          'message' => 'Shopping List has been shared successfully.'
        );


        return $return;

      }
      
    }



  public function sharedlist(){

          global $wpdb,$current_user;

          if(is_user_logged_in ()) {

            $shoppingList = $this->getMysharedlist($current_user->data->user_login);
            //$shoppingList = $this->getMysharedlist_new($current_user->data->user_login);
            if($shoppingList != 0){
             

                foreach ($shoppingList as $key => $value) {
                      
                    $sharedbyID = $value->created_by;
                    $user_info  = get_userdata($sharedbyID);
                  
                    $userEmail = $this->getuserbyEmail($user_info->data->user_login);
                   
                    if( $userEmail != 0){
                      $display_name = $userEmail->display_name;
                    } else {
                      //$display_name = $user_info->data->user_login;
                      $display_name = '';
                    }

                    $list[$key] =  $shoppingList[$key];
                    $list[$key]->shared_by =  $display_name;

                }
                

            } else {

             // throw new Exception("Not Found",404);
              return array();
            }
          
          } else {

            throw new Exception('User must be loggedin to view sharedlist!',400);

          }

          return $list;
    } 

    public function deletelist(){

      global $wpdb,$current_user;

      if(is_user_logged_in ()) {

        if(!$this->_post('id')) {
            throw new Exception('List id must be required!',400);
        } 
          if(!is_numeric($this->_post('id')))
          {
            throw new Exception('Bad request',400);
          }

          $checkdata = $this->getList($this->_post('id'));  //check if list exist in db....
          
          if($checkdata != '0') {
            
            if($checkdata->created_by == $current_user->ID){ // I m logged in and also i m onwner of this , DELETE FROM MY LIST........DELETE FROM DATABASE

                $ws = 'deletelist';$title=$this->_post('id');
                $actionby = $current_user->ID;$ownerid='';
                $p = $this->sendPush($this->_post('id'),$ws,$title,$actionby,$ownerid);

                //delete....
                $result = $wpdb->delete($wpdb->prefix.'shopping_list', array("id" => $this->_post('id'), "created_by" => $current_user->ID ));

                $results = $wpdb->delete($wpdb->prefix.'shopping_list_detail', array("list_id" => $this->_post('id') ));

                $resultde = $wpdb->delete($wpdb->prefix.'shopping_share', array("list_id" => $this->_post('id') ));

                  
                // $resultde = $wpdb->delete($wpdb->prefix.'shopping_share_item', array("list_id" => $this->_post('id') ));

                $return = array(
                  'message' => 'Shoppinglist has been deleted successfully.'
                );


            } else { // I m logged in and i m NOT onwner of this , DELETE FROM SHARED LIST , JUST UPDATE FLAG


              // IF SHARED WITH YOU, THEN AND THEN ONLY YOU CAN DELETE FROM YOUR SHARLISTING.
              // IF YOUR EMAIL ID IS IN 'shared_with' field. 
               $dd = $this->isSharedwiththisuser($checkdata->created_by,$current_user->data->user_email,$this->_post('id'));


              if($dd != 0 ){

                // $ws = 'deletelist';$title=$this->_post('id');
                // $actionby = $current_user->ID;$ownerid='';
                // $p = $this->sendPush($this->_post('id'),$ws,$title,$actionby,$ownerid);

                 $result = $wpdb->delete($wpdb->prefix.'shopping_share', array("list_id" => $this->_post('id'),"shared_to" => $current_user->data->user_email ));

                   // $resultde = $wpdb->delete($wpdb->prefix.'shopping_share_item', array("list_id" => $this->_post('id'),"shared_to" => $current_user->data->user_email ));
               

                    if(!$result){
                      throw new Exception('Shoppinglist already deleted or deletion unsuccess!',400);  
                    }

                    $return = array(
                      'message' => 'Shoppinglist has been deleted successfully.'
                    );

              } else {
                    // others shared listing....
                    throw new Exception('Bad request',400);
              }

            }
               
          } else {

            throw new Exception('Bad request',400);
            
          }

      } else {

        throw new Exception('User must be loggedin to do this action!',400);
      
      }

      return $return; 
    }

    public function deletelist_item() {


      global $wpdb,$current_user;

      if(is_user_logged_in ()) {

        if(!$this->_post('id')) { 
            throw new Exception('Listitem id must be required!',400);
        } 
          if(!is_numeric($this->_post('id')))
          {
            throw new Exception('Bad request',400);
          }

        if(!$this->_post('listid')) {
            throw new Exception('List id must be required!',400);
        } 
          if(!is_numeric($this->_post('listid')))
          {
            throw new Exception('Bad request',400);
          }

          $checkdata = $this->getList($this->_post('listid'));  //check if list exist in db....
  
          $list = $this->getList($this->_post('listid'));

          if($checkdata != '0') {
           

            if($checkdata->created_by == $current_user->ID){ // I m logged in and also i m onwner of this , DELETE FROM MY LIST........DELETE FROM DATABASE

                $result = $wpdb->delete($wpdb->prefix.'shopping_list_detail', array("list_id" => $this->_post('listid'),"id" => $this->_post('id') ));

                //$result = $wpdb->delete($wpdb->prefix.'shopping_share', array("list_id" => $this->_post('id') ));
                
                 // SEND PUSH NOTIFICATION
            if($result ){
                $ws = 'deletelist_item';$title=$this->_post('listid');
                $actionby = $current_user->ID;$ownerid=$list->created_by;
                      $p = $this->sendPush($this->_post('listid'),$ws,$title,$actionby,$ownerid);
            }
            // SEND PUSH NOTIFICATION

                $return = array(
                  'message' => 'Shoppinglist item has been deleted successfully.'
                );


            } else { // I m logged in and i m NOT onwner of this , DELETE FROM SHARED LIST , JUST UPDATE FLAG
            
              
              
              // IF SHARED WITH YOU, THEN AND THEN ONLY YOU CAN DELETE FROM YOUR SHARLISTING.
              // IF YOUR EMAIL ID IS IN 'shared_with' field. 
               $dd = $this->isSharedwiththisuser($checkdata->created_by,$current_user->data->user_email,$this->_post('listid'));

              if($dd != 0 ){

                $results = $wpdb->delete($wpdb->prefix.'shopping_list_detail', array("id" => $this->_post('id') ));


                //   $ws = 'deletelist_item';$title=$this->_post('listid');
                // $actionby = $current_user->ID;$ownerid=$list->created_by;
                //       $p = $this->sendPush($this->_post('listid'),$ws,$title,$actionby,$ownerid);






                    $return = array(
                      'message' => 'Shoppinglist item has been deleted successfully.'
                    );

              } else {
                    // others shared listing....
                    throw new Exception('Bad request',400);
              }

            }
               
          } else {

            throw new Exception('Bad request',400);
            
          }

      } else {

        throw new Exception('User must be loggedin to do this action!',400);
      
      }

      return $return; 


    }

    public function purchaseitem(){

      global $wpdb,$current_user;


      if(is_user_logged_in ()) {

        if(!$this->_post('id')) {
            throw new Exception('Listitem id must be required!',400);
        } 
          if(!is_numeric($this->_post('id')))
          {
            throw new Exception('Bad request',400);
          }

        if(!$this->_post('listid')) {
            throw new Exception('List id must be required!',400);
        } 
          if(!is_numeric($this->_post('listid')))
          {
            throw new Exception('Bad request',400);
          }

        if(!$this->_post('purchase')) {

            throw new Exception('Purchase flag must be required!',400);
        } 

        $isShared = $this->isSharedwiththisuser($from,$current_user->data->user_login,$this->_post('listid'));
        // is Shared this one with buyer ?

        $mineShoppinglist = $this->isExistindb($this->_post('listid'));  // is Mine listing , i can purchase

        $lst = $this->getList($this->_post('listid'));
      
        if( $isShared != 0  || $mineShoppinglist != 0 ) {
         
          if($this->_post('purchase') == 'true' || $this->_post('purchase') == 'false' ) {

           $shoppingLisDetail = $this->shoppingListinfo($this->_post('id'));  //check if list exist ....
         
            if($shoppingLisDetail != 0) {  //check if list items exist ....

                if($this->_post('purchase') == 'true') {   // Buy......
                   
                  // Mark as purchase.....
                  $result =  $wpdb->update($wpdb->prefix."shopping_list_detail", array("purchased" => '1'), array("id" => $this->_post('id'), "list_id" => $this->_post('listid'))  );
                  

                      // SEND PUSH TO OWNER when purchased by others
                      if($current_user->ID != $lst->created_by){

                            $notification = get_user_meta($lst->created_by, 'notification', true);
                                    
                            if($notification == 'on') {

                                $data = $wpdb->get_row("SELECT id,device_os,device_id,token FROM wp_users_token WHERE user_id='$lst->created_by' ORDER BY id DESC limit 1",ARRAY_A);

                                $lst = $this->getList($this->_post('listid'));
                                 
                                  

                                      $device_os = $data['device_os'];
                                      $device_id = $data['device_id'];
                                      $token = $data['token'];

                                      if($device_os == 'android'){

                                           $msg = array(
                                          'notification_type' => 'shopping_list',
                                          'event_type' => 'update',
                                          'body'   => $shoppingLisDetail[0]->title." has been purchased!",
                                          'created_by' => $lst->created_by,
                                          'id' => $lst->id,
                                          'title' => $lst->title
                                          );


                                        $notification = array (
                                          'body'   => $shoppingLisDetail[0]->title." has been purchased!",
                                          'title'   => "FindFresh" 
                                        );
                                      } else {

                                         $msg = array(
                                          'notification_type' => 'shopping_list',
                                          'event_type' => 'update',
                                          'body'   => $shoppingLisDetail[0]->title." has been purchased!",
                                          'event_details' => $lst
                                        );


                                         $notification = $shoppingLisDetail[0]->title." has been purchased!";
                                      }


                                      if($device_os  != '' && $device_id != '') {
                                        $this->pushnotification($device_os,$device_id,$msg,$notification);
                                      }

                            } // pushnotification settings 
                      }
                       // END PUSH TO OWNER when purchased by others



                      //START SEND PUSH NOTIFICATION TO ALL SHARED USER NOT YOURSELF
                      $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}shopping_share WHERE list_id = ".$this->_post('listid')." ");



                        foreach ($results as $key => $value) {

                           $userid = $this->getuserbyEmail($value->shared_to);
                             
                                if($userid->ID !=  $current_user->ID ){

                                if(!empty($userid))  {
                                  
                                  $notification = get_user_meta($userid->ID, 'notification', true);

                                  if($notification == 'on') {

                                      $data = $wpdb->get_row("SELECT id,device_os,device_id,token FROM wp_users_token WHERE user_id='$userid->ID' ORDER BY id DESC limit 1",ARRAY_A);

                                    

                                      if(!empty($data )) 
                                      {

                                          $lst = $this->getList($this->_post('listid'));
                                          
                                          
                                        

                                          $device_os = $data['device_os'];
                                          $device_id = $data['device_id'];
                                          $token = $data['token'];

                                          if($device_os == 'android'){
                                              
                                              $msg = array(
                                              'notification_type' => 'shopping_list',
                                              'event_type' => 'update',
                                              'body'   => $shoppingLisDetail[0]->title." has been purchased!",
                                              'created_by' => $lst->created_by,
                                              'id' => $lst->id,
                                              'title' => $lst->title
                                              );



                                            $notification = array (
                                              'body'   => $shoppingLisDetail[0]->title." has been purchased!",
                                              'title'   => "FindFresh" 
                                            );
                                          } else {

                                            $msg = array(
                                            'notification_type' => 'shopping_list',
                                            'event_type' => 'update',
                                            'body'   => $shoppingLisDetail[0]->title." has been purchased!",
                                            'event_details' => $lst
                                           );


                                             $notification = $shoppingLisDetail[0]->title." has been purchased!";
                                          }


                                          if($device_os  != '' && $device_id != '') {
                                            
                                            $this->pushnotification($device_os,$device_id,$msg,$notification);
                                          }
                                      }

                                  } // pushnotification settings.... 

                                }



                              }

                        }
                    //  END SEND PUSH NOTIFICATION TO ALL SHARED USER NOT YOURSELF
                        

                  if($result){
                     $return = array(
                        'message' => 'Purchased.'
                      );
                  }else {
                    throw new Exception('Already purchased or Unsucess!',400);
                  }
                  
               }

               if($this->_post('purchase') == 'false') { // Un Buy......


                    $lst = $this->getList($this->_post('listid'));



                   $result =  $wpdb->update($wpdb->prefix."shopping_list_detail", array("purchased" => '0'), array("id" => $this->_post('id'), "list_id" => $this->_post('listid'))  );


                    // SEND PUSH TO OWNER when purchased by others
                      if($current_user->ID != $lst->created_by){

                          $notification = get_user_meta($lst->created_by, 'notification', true);
                          
                          if($notification == 'on') {

                              $data = $wpdb->get_row("SELECT id,device_os,device_id,token FROM wp_users_token WHERE user_id='$lst->created_by' ORDER BY id DESC limit 1",ARRAY_A);

                                $lst = $this->getList($this->_post('listid'));
                                     

                                      $device_os = $data['device_os'];
                                      $device_id = $data['device_id'];
                                      $token = $data['token'];

                                      if($device_os == 'android'){

                                      
                                         $msg = array(
                                        'notification_type' => 'shopping_list',
                                        'event_type' => 'update',
                                        'body'   =>  $shoppingLisDetail[0]->title." has been purchased!",
                                        'created_by' => $lst->created_by,
                                          'id' => $lst->id,
                                          'title' => $lst->title
                                      );



                                        $notification = array (
                                          'body'   => $shoppingLisDetail[0]->title." has been purchased!",
                                          'title'   => "FindFresh" 
                                        );
                                      


                                      } else {

                                         $msg = array(
                                        'notification_type' => 'shopping_list',
                                        'event_type' => 'update',
                                        'body'   => $shoppingLisDetail[0]->title." has been purchased!",
                                        'event_details' => $lst
                                      );


                                         $notification = $shoppingLisDetail[0]->title." has been purchased!";
                                      }


                                      if($device_os  != '' && $device_id != '') {
                                        
                                        $this->pushnotification($device_os,$device_id,$msg,$notification);
                                      }
                      
                        } // pushnotification settings.... 
                      }
                      // END PUSH TO OWNER when purchased by others


                      //START SEND PUSH NOTIFICATION TO ALL SHARED USER NOT YOURSELF
                      $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}shopping_share WHERE list_id = ".$this->_post('listid')." ");
                        foreach ($results as $key => $value) {
                           $userid = $this->getuserbyEmail($value->shared_to);

                            if($userid->ID !=  $current_user->ID ){

                                if(!empty($userid))  {

                                  $notification = get_user_meta($userid->ID, 'notification', true);
                                  
                                  if($notification == 'on') {

                                  $data = $wpdb->get_row("SELECT id,device_os,device_id,token FROM wp_users_token WHERE user_id='$userid->ID' ORDER BY id DESC limit 1",ARRAY_A);

                                  if(!empty($data )) 
                                  {
                                      $device_os = $data['device_os'];
                                      $device_id = $data['device_id'];
                                      $token = $data['token'];
                                      if($device_os == 'android'){
                                        $msg = array(
                                        'notification_type' => 'shopping_list',
                                        'event_type' => 'update',
                                        'body'   =>   "Cancelled to purchase ".$shoppingLisDetail[0]->title.".",
                                        'created_by' => $lst->created_by,
                                          'id' => $lst->id,
                                          'title' => $lst->title
                                      );
                                      $notification = array (
                                          'body'   => "Cancelled to purchase ".$shoppingLisDetail[0]->title.".",
                                          'title'   => "FindFresh" 
                                        );
                                      } else {

                                          $msg = array(
                                        'notification_type' => 'shopping_list',
                                        'event_type' => 'update',
                                        'body'   => "Cancelled to purchase ".$shoppingLisDetail[0]->title.".",
                                        'event_details' => $lst
                                      );
                                         $notification = "Cancelled to purchase ".$shoppingLisDetail[0]->title.".";
                                      }
                                      if($device_os  != '' && $device_id != '') {
                                        $this->pushnotification($device_os,$device_id,$msg,$notification);
                                      }
                                  }
                               

                                }// notification settings 

                                }

                              }
                        }
                    //END SEND PUSH NOTIFICATION TO ALL SHARED USER NOT YOURSELF


                  if($result){
                      $return = array(
                        'message' => 'Cancelled.'
                      );
                  } else {
                        throw new Exception('Item has been already cancelled!',400);
                  }

               }

            } else {
              throw new Exception('Bad request',400);
            }

          } else {

              throw new Exception('Bad request',400);
          }

        } else {
            throw new Exception('Bad request',400);
        } 

    } else {

      throw new Exception('User must be loggedin to do this action!',400);

    }

    return $return; 
  }







################### START PRODUCE ALERT ##############################      
  public function notification_setting() {

      global $wpdb,$current_user;

      if(is_user_logged_in ()) {
        
          if(!$this->_post('notification')) {
              throw new Exception('Please set notification value!',400);
          }

          if($this->_post('notification') == 'on'){
            update_user_meta($current_user->ID, 'notification', 'on');
          } else if($this->_post('notification') == 'off'){
            update_user_meta($current_user->ID, 'notification', 'off');         
          } else {
             throw new Exception('Bad request',400); 
          }

          $return = array(
            'message' =>  'Notification has been '.$this->_post('notification'),
          );
          
          return $return;

      } else {
        throw new Exception('User must be loggedin!',400);
      } 
  }

  public function get_notification_setting() {
      global $wpdb,$current_user;

      if(is_user_logged_in ()) {

          $uid = $current_user->ID;

          $notification = get_user_meta($uid,'notification', true);
          if($notification == ''){
            $notification = 'on';
          }

          $return = array(
            'notification_settings' =>  $notification,
          );
          
          return $return;

      } else {
        throw new Exception('User must be loggedin!',400);
      } 

  }


      /**
     * Get $_POST value by key
     * @param null $key
     * @return string|void
     */
    private function _post($key = null)
    {
        if(!$key)
        {
            return $_POST;
        } else if(isset($_POST[$key]) && $_POST[$key])
        {
            return trim($_POST[$key]);
        } else {
            return;
        }
    }

    public function isstoreExistindb1($id){
        global $wpdb;
        $result = $wpdb->get_var("SELECT `post_title` FROM {$wpdb->prefix}posts WHERE post_type = 'green_grocer' AND post_status = 'publish' AND ID = $id ");
      
        return $result;
    }

     public function isstoreExistindb($id){
        global $wpdb;
        // $result = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = 'green_grocer' AND post_status = 'publish' AND ID = $id ");
        
        // return $result;

        $result = $wpdb->get_results(
              "SELECT storeid FROM wp_store_locator WHERE storeid = ".$id." ");

        return $result;

    }

    public function isExistindbbyme($id){
        global $wpdb,$current_user;
        $result = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}shopping_list WHERE id = $id AND created_by = $current_user->ID ");


        //echo $wpdb->last_query;exit;
       
        return $result;
    }

     public function isExistindb($id){
        global $wpdb,$current_user;
        $result = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}shopping_list WHERE id = $id ");


        //echo $wpdb->last_query;exit;
       
        return $result;
    }



    public function get_shoppingList($id){
        global $wpdb,$current_user;
        
        //$result = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}shopping_list_detail WHERE list_id = $id ");
          
        $result = $wpdb->get_results( "SELECT sl.id,sl.title FROM 

          {$wpdb->prefix}shopping_list AS sl , {$wpdb->prefix}shopping_list_detail AS sld 

          WHERE sl.id = sld.list_id  AND sl.created_by = $current_user->ID AND sl.id =  $id");

        if($result){
          return $result;  
        } else {
          return 0;
        }
    }

     public function get_mylist($id){
        global $wpdb;
        $result = $wpdb->get_results("SELECT sl.id,sl.title,sl.created_by FROM {$wpdb->prefix}shopping_list AS sl 
        WHERE sl.created_by = ".$id." ORDER BY created_date DESC "); 
        if($result){
          return $result;  
        } else {
          return 0;
        }
    }

    public function get_myfavouritestore($id){
        global $wpdb;
        $result = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}favourite_store WHERE userid = ".$id." ");
        if($result){
          return $result;  
        } else {
          return 0;
        }
    }


      
       public function shoppingListinfo($id)
    {
        global $wpdb;
        $result = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}shopping_list_detail WHERE id = $id  ");
        
        if($result){
          return $result;  
        } else {
          return 0;
        }
    }  



      public function shoppingListinfotest($id)
    {
        global $wpdb;
        $result = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}shopping_list_detail WHERE list_id = $id  ");
        if($result){
          return $result;  
        } else {
          return 0;
        }
    }  


    public function isSharedwiththisuser($from=null,$to,$listid ){

       global $wpdb;

       if($from == ''){

         // $cond = 'list_id = "'.$listid.'" AND shared_to =  "'.$to.'" '; 
          $cond = 'list_id = "'.$listid.'" AND shared_to =  "'.$to.'" '; 

        } else {

          $cond = 'list_id = "'.$listid.'"  AND shared_to =  "'.$to.'"  '; 
           // $cond = 'list_id = "'.$listid.'"  AND  shared_from = "'.$from.'" AND shared_to =  "'.$to.'"  '; 
       }

       $result = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}shopping_share WHERE $cond ");


        // echo  $wpdb->last_query;exit;
        // print_r(  $result);
        // exit;

        if($result){
          return $result;  
        } else {
          return 0;
        }

    }



    public function get_shoppingListdetail($id)
    {
        global $wpdb;
        $result = $wpdb->get_results("SELECT * FROM 

          {$wpdb->prefix}shopping_list AS sl , {$wpdb->prefix}shopping_list_detail AS sld 

          WHERE sl.id = sld.list_id   AND sl.id = $id  "); 

         
        if($result){
          return $result;  
        } else {
          return 0;
        }
    }

        public function get_sharedshoppingListdetail($id)
    { 
      /*
        global $wpdb;
        $resultd = $wpdb->get_results("SELECT * FROM 

          {$wpdb->prefix}shopping_list AS sl , {$wpdb->prefix}shopping_share_item AS sld 

          WHERE sl.id = sld.list_id   AND sl.id = $id  "); 

          foreach ($resultd as $key => $value) {
              

                
          
                $result = $wpdb->get_results("SELECT * FROM 

                  {$wpdb->prefix}shopping_list AS sl , {$wpdb->prefix}shopping_list_detail AS sld 

                  WHERE sl.id = sld.list_id   AND sl.id = $id AND sld.id = $value->item_id "); 




          }
         

        if($result){
          return $result;  
        } else {
          return 0;
        }
        
        */
        
          global $wpdb;
        $result = $wpdb->get_results("SELECT * FROM 

          {$wpdb->prefix}shopping_list AS sl , {$wpdb->prefix}shopping_list_detail AS sld 

          WHERE sl.id = sld.list_id   AND sl.id = $id  "); 

         
        if($result){
          return $result;  
        } else {
          return 0;
        }
        
    }
    
    
    function getuserbyEmail($email){
        global $wpdb;
        $result = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}users WHERE `user_email` = '".$email."'");
        if($result){
          return $result[0];  
        } else {
          return 0;
        }
    }

    function sendPush($listid,$ws,$title=null,$actionby,$ownerid=null){

      global $wpdb,$current_user;
           // SEND PUSH NOTIFICATION
      $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}shopping_share WHERE list_id = ".$listid." ");
      
      if(!empty($results)) 
      {
        foreach ($results as $key => $value) 
        {
            $userid = $this->getuserbyEmail($value->shared_to);
                
            //ownerid == $list->created_by;
            //actionby ==$current_user->ID
            
            // START SEND PUSH TO OWNER
            if($ownerid != "")
            {
                if($ownerid != $actionby)
                {
                  $notification = get_user_meta($ownerid, 'notification', true);
                  if($notification == 'on') {


                    $data = $wpdb->get_row("SELECT id,device_os,device_id,token FROM wp_users_token WHERE user_id='$ownerid' ORDER BY id DESC limit 1",ARRAY_A);

                        if(!empty($data )) 
                        {

                            $lst = $this->getList($listid);
                            $device_os = $data['device_os'];
                            $device_id = $data['device_id'];
                            $token = $data['token'];

                            if($ws == 'addshoppinglistitem'){
                              $operation = 'update';  
                              $noti_body = 'Item successfully added to '.$lst->title.' !!';
                              $lst = $this->getList($title);
                            }

                            if($ws == 'deletelist_item'){
                              $operation = 'update';
                              $noti_body = 'Item has been deleted from the List '.$lst->title.' !!';
                              $lst = $this->getList($title);
                            }

                            if($ws == 'editmylistitems'){
                          
                              $operation = 'update';   
                              $noti_body = 'Items has been updated in '.$lst->title.'  !!';
                              $lst = $this->getList($title);
                            }

                            if($device_os == 'android'){

                                $notification = array (
                                  'body'    =>  $noti_body,
                                  'title'   => "FindFresh" 
                                );
                                      
                                $msg = array(
                                   'notification_type' => 'shopping_list',
                                   'event_type' => $operation,
                                   'body'   =>  $noti_body,
                                   'created_by' => $lst->created_by,
                                   'id' => $lst->id,
                                   'title' => $lst->title
                                );

                            } else {

                                $msg = array(
                                  'notification_type' =>  'shopping_list',
                                  'event_type' => $operation,
                                  'body'   =>  $noti_body,
                                  'event_details' => $lst
                                );

                                     $notification = $noti_body;
                            }

                            if($device_os  != '' && $device_id != '') {
                              $this->pushnotification($device_os,$device_id,$msg,$notification);
                            }

                        }

                  }
                }
            } 
            // END SEND PUSH TO OWNER


            // START SEND PUSH TO SHARED USER MUST NOT TO SELF....
            if($userid->ID !=  $actionby ) 
            {
                  if(!empty($userid))  
                  {

                    $notification = get_user_meta($userid->ID, 'notification', true);
                    if($notification == 'on') {

                    $data = $wpdb->get_row("SELECT id,device_os,device_id,token FROM wp_users_token WHERE user_id='$userid->ID' ORDER BY id DESC limit 1",ARRAY_A);

                    if(!empty($data )) 
                    {

                        $lst = $this->getList($listid);
                        $device_os = $data['device_os'];
                        $device_id = $data['device_id'];
                        $token = $data['token'];


                        if($ws == 'addshoppinglistitem'){
                        
                          $operation = 'update';  
                          $noti_body = 'Item successfully added to '.$lst->title.' !!';
                          $lst = $this->getList($title);
                        }
                        if($ws == 'editmylistitems'){
                        
                          $operation = 'update';   
                          $noti_body = 'Items has been updated in '.$lst->title.'  !!';
                          $lst = $this->getList($title);

                        }
                        if($ws == 'deletelist_item'){
                        
                          $operation = 'update';
                          $noti_body = 'Item has been deleted from the List '.$title->title.' !!';
                          $lst = $this->getList($title);
                        }
                        if($ws == 'deletelist'){
                        
                          $operation = 'delete';
                          $noti_body = $title->title.' shoppinglist has been deleted !!';
                          $lst = $this->getList($title);
                        }

                        if($ws == 'editmylisttitle'){
                        
                          $operation = 'update';
                          $noti_body = $lst->title.' shoppinglist title has been updated !!';
                          $lst = $this->getList($title);
                        }
                            
                        if($device_os == 'android'){

                          $msg = array(
                             'notification_type' => 'shopping_list',
                             'event_type' => $operation,
                             'body'   =>  $noti_body,
                             'created_by' => $lst->created_by,
                             'id' => $lst->id,
                             'title' => $lst->title
                          );

                          $notification = array (
                            'body'    =>  $noti_body,
                            'title'   => "FindFresh" 
                          );

                        } else {
                          
                          $msg = array(
                            'notification_type' =>  'shopping_list',
                            'event_type' => $operation,
                            'body'   =>  $noti_body,
                            'event_details' => $lst
                          );

                           $notification = $noti_body;
                        }


                        if($device_os  != '' && $device_id != '') {
                          $this->pushnotification($device_os,$device_id,$msg,$notification);
                        }
                    }

                  }// notification settings....

                  }
            }
            // END SEND PUSH TO SHARED USER MUST NOT TO SELF....

        }// END OF FOREACH
      }
    }

     function isFavourite($storeid){

        global $wpdb,$current_user;

        $result = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}favourite_store WHERE userid = ".$current_user->ID." AND storeid = ".$storeid." ");

        if($result){
          return $result;  
        } else {
          return 0;
        }
     }  


    function getList($id){
        global $wpdb;
        $result = $wpdb->get_results("SELECT sl.id,sl.title,sl.created_by FROM {$wpdb->prefix}shopping_list AS sl  WHERE sl.id = ".$id."  "); 
        if($result){
          return $result[0];  
        } else {
          return 0;
        }
    }

    
    function getMysharedlist($email){
        global $wpdb;
  
        $result = $wpdb->get_results("SELECT sl.id,sl.title,sl.created_by FROM {$wpdb->prefix}shopping_list AS sl , {$wpdb->prefix}shopping_share AS shr WHERE sl.id = shr.list_id AND shr.shared_to = '".$email."' ORDER BY sl.created_date DESC");

        if($result){
          return $result;  
        } else {
          return 0;
        }
    }
     function getMysharedlist_new($email){
        global $wpdb;
  
         $result = $wpdb->get_results("SELECT sl.id,sl.title,sl.created_by FROM {$wpdb->prefix}shopping_list AS sl , {$wpdb->prefix}shopping_share AS shr, {$wpdb->prefix}shopping_share_item AS items WHERE sl.id = shr.list_id AND shr.list_id = items.list_id AND shr.shared_to = '".$email."' AND items.shared_to = '".$email."' GROUP BY sl.id ");


        /*
          SELECT Persons.Name, Persons.SS, Fears.Fear FROM Persons
          LEFT JOIN Person_Fear ON Person_Fear.PersonID = Persons.PersonID
          LEFT JOIN Fears ON Person_Fear.FearID = Fears.FearID
        */
/*
        $result = $wpdb->get_results("SELECT sl.id,sl.title,sl.created_by FROM {$wpdb->prefix}shopping_list AS sl RIGHT JOIN {$wpdb->prefix}shopping_share AS shr ON sl.id = shr.list_id
          RIGHT JOIN {$wpdb->prefix}shopping_share_item  AS items ON shr.list_id = items.list_id
          WHERE shr.shared_to = '".$email."' AND items.shared_to = '".$email."' ORDER BY sl.created_date DESC");
*/



        if($result){
          return $result;  
        } else {
          return 0;
        }
    }

    function get_greengrocerposts($id){
  
      global $wpdb;
      $querystr = "SELECT * FROM ".$wpdb->prefix."localgreenposts WHERE ".$wpdb->prefix."localgreenposts.ID =  ".$id." ";
      return  $pageposts = $wpdb->get_results($querystr, OBJECT);

    }
  





 public function pushnotification($type,$device_id=null,$msg,$notification=null){

    if($type == 'android'){


        $key = 'AIzaSyCaE1vZ9DxNFwKAaYtMOxX8H0SvgSpGdWk';
        $device_id = array($device_id);
        // $msgs = array (
        //   'message'   => $msg,
        // );
        $fields = array (
          'registration_ids'  => $device_id,
         // 'notification'  => $notification,
          'data'      => (array)$msg
        );
        $headers = array (
          'Authorization: key=' . $key,
          'Content-Type: application/json'
        );
        $ch = curl_init();
        curl_setopt( $ch,CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send' );
        curl_setopt( $ch,CURLOPT_POST, true );
        curl_setopt( $ch,CURLOPT_HTTPHEADER, $headers );
        curl_setopt( $ch,CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch,CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch,CURLOPT_POSTFIELDS, json_encode( $fields ) );
        $result = curl_exec($ch );
        curl_close( $ch );
    }

    if($type == 'ios'){

        $sandbox = false;
        $ios_pem_file_path = plugin_dir_path(__FILE__).'tesst_pushDevelopmentCertificates.pem';
        define( 'PUSH_NOTIFI_PATH', $ios_pem_file_path );
        $sandbox = true;
        $ctx = stream_context_create();
        stream_context_set_option($ctx, 'ssl', 'local_cert', PUSH_NOTIFI_PATH);
        stream_context_set_option($ctx, 'ssl', 'passphrase', 'pushchat');
        $sandbox = $sandbox ? 'sandbox.' : '';
        $url = "ssl://gateway.sandbox.push.apple.com:2195";
        $fp = stream_socket_client($url, $err, $errstr, 60, STREAM_CLIENT_ASYNC_CONNECT, $ctx);
        if (!$fp){ return false; }
        $body = array();
        $fields = array (
          'message'      => $msg
        );
        $body['aps'] = array('sound' => 'default', 'alert' => $notification,'data' => $fields ,'badge'=>'0','content-available'=>'1');
        $payload = json_encode($body);
        $msg = chr( 0 ) . pack('n', 32) . pack('H*', $device_id) . pack('n', strlen($payload)) . $payload;
        fwrite($fp, $msg, strlen($msg));
        fclose($fp);
        return true;
    }

  }

    /**
     * Logout from WordPress
     * @return array
     */
    public function logout()
    {
        $this->destroy_current_user_token();
        wp_destroy_current_session();
        wp_clear_auth_cookie();
       // return array();
      
        $return = array(
          'message' => 'Logout successfully.'
        );

        return $return;

    }


    // Custom Image Function
    function atl_post_image($str) {
      echo $str;
      exit;
       // global $post;
        //$image = '';
        $image_id = get_post_thumbnail_id($str);
        $image = wp_get_attachment_image_src($image_id, 'full');
        $image = $image[0];
        if ($image) return $image;
        
       // return atl_get_first_image();
    }

    public function advSearchfrompost($state,$address){
        $ind = 0;
        global $wpdb;
        $cond = '';
    
        if (strpos($state, ',') !== false) {
          $citystate = explode(",", $state);
          $city = trim($citystate[0]);
          $state = trim($citystate[1]);
        } 
        
        if($city){
          $cond .= "sl_city LIKE '$city' AND ";
        }
        
        if($state){
          $cond .= "sl_state LIKE '$state'";
        }
        if($address){
          $address = "%" . $address . "%";
          if( $cond != ''){
            $cond .= ' AND';
          }
          $cond .= " (sl_address LIKE '$address' OR sl_address2 LIKE '$address' OR sl_zip LIKE '$address')";
        }



        $extra_services = $wpdb->get_results(
              "SELECT sl_id as sl_map_id , sl_address, sl_address2, sl_store, sl_city, sl_state, sl_zip, sl_latitude,sl_longitude, sl_description, sl_url, sl_hours, sl_phone, sl_fax, sl_email, sl_image, sl_tags , IF(sl_linked_postid  IS NULL , '0' , sl_linked_postid ) as sl_id  FROM wp_store_locator WHERE  $cond ");

       // echo $wpdb->last_query;exit;
      
        if(empty(  $extra_services)){
          return array();
        }

        return $extra_services;
    }
      public function get_location_by_titles($sl_id,$store,$city,$state,$address){
      $ind = 0;

         global $wpdb;
              $storetitle = trim($title);
              $search_text = "%" . $storetitle . "%";

           
               $cond = "sl.sl_store = '".$store."' AND sl.sl_state = '".$state."' AND sl.sl_city = '".$city."' AND sl.sl_id = '".$sl_id."'";
           

          $extra_services = $wpdb->get_results(
              "SELECT sl.sl_id as sl_map_id ,sl.sl_address, sl.sl_address2, sl.sl_store, sl.sl_city, sl.sl_state, sl.sl_zip, sl.sl_latitude,sl.sl_longitude, sl.sl_description, sl.sl_url, sl.sl_hours, sl.sl_phone, sl.sl_fax, sl.sl_email, sl.sl_image, sl.sl_tags FROM wp_store_locator sl INNER JOIN wp_posts as pts
        ON pts.post_title = sl.sl_store AND
       $cond   GROUP BY sl.sl_id ");


      


              if(!empty($extra_services)) {
                foreach($extra_services as $key => $extra_services)
                {
                      $result[$ind] = $extra_services;

                       // start favourite status
                        // $fav = $this->isFavourite($storeID,$uid='');
                        // if($fav != 0){
                        //    $result[$ind]->favourite =  '1';
                        // } else {
                        //    $result[$ind]->favourite =  '0';
                        // }
                        // end favourite status


                    //$result[$ind]->sl_id =  $storeID;
                    $ind++;
                  
                } 
              } else {
                $result =array();
              }


              return $result;
    }

    public function getstoredetailbyid($id){
        global $wpdb;
        $result = $wpdb->get_results(
              "SELECT sl_id as sl_map_id,sl_address, sl_address2, sl_store, sl_city, sl_state, sl_zip, sl_latitude,sl_longitude, sl_description, sl_url, sl_hours, sl_phone, sl_fax, sl_email, sl_image, sl_tags FROM wp_store_locator WHERE sl_id = '".$id."' ");

        return $result;

    }
    public function get_location_by_title($title){
      $ind = 0;

         global $wpdb;
              $storetitle = trim($title);
              $search_text = "%" . $storetitle . "%";

              if(is_numeric( $storetitle)){
                $cond = "sl_zip LIKE '$search_text'";
              } else {
                $cond = "sl_store LIKE '$search_text'";
              }

            $extra_services = $wpdb->get_results(
              "SELECT sl_id as sl_map_id,sl_address, sl_address2, sl_store, sl_city, sl_state, sl_zip, sl_latitude,sl_longitude, sl_description, sl_url, sl_hours, sl_phone, sl_fax, sl_email, sl_image, sl_tags FROM wp_store_locator WHERE  $cond ");


              if(!empty($extra_services)) {
                foreach($extra_services as $key => $extra_services)
                {
                   
                  $extra_services1 =  $wpdb->get_results( 
                    $wpdb->prepare( 
                            "SELECT `ID` FROM `wp_posts` WHERE `post_type` IN ('green_grocer','greengrocer','post') AND 
                        `post_status` = 'publish' AND  `post_title` LIKE %s",$extra_services->sl_store 
                    ) 
                  );
                  $storeID =  $extra_services1[$ind]->ID;
                  if( $storeID  != ''){
                      $result[$ind] = $extra_services;

                      $result[$ind]->sl_id =  $storeID;

                       //TEMP CODE FOR UPDATE STORE LOCATOR WITH POST....
                      
                        //TEMP CODE FOR UPDATE STORE LOCATOR WITH POST....


                      $ind++;
                  } else {
                     
                     $result[$ind] = $extra_services;
                     $result[$ind]->sl_id =  "0";
                     $ind++;

                  }
                } 
              } else {
                $result =array();
              }

              return $result;
    }

     public function destroy_current_user_token(){
        global $wpdb,$user;
        $header_token = $_SERVER['HTTP_TOKEN']; 
        $wpdb->delete($wpdb->prefix.'users_token', array('user_id'=>get_current_user_id(),'token'=>$header_token));
    }

     /**
     * Helper function of forgot_password
     * @param string $user_login
     * @return bool
     */
    private function retrieve_password($user_login = '') {
        global $wpdb, $wp_hasher;

        $errors = new WP_Error();

        if ( empty( $user_login ) ) {
            $errors->add('empty_username', __('Enter a username or e-mail address.'));
        } elseif ( strpos( $user_login, '@' ) ) {
            $user_data = get_user_by( 'email', trim( $user_login ) );
            if ( empty( $user_data ) )
                $errors->add('invalid_email', __('There is no User registered with this Email ID.'));
        } else {
            $login = trim($user_login);
            $user_data = get_user_by('login', $login);
        }

        /**
         * Fires before errors are returned from a password reset request.
         *
         * @since 2.1.0
         */
        do_action( 'lostpassword_post' );

        if ( $errors->get_error_code() )
            return $errors;

        if ( !$user_data ) {
            $errors->add('invalidcombo', __('<strong>ERROR</strong>: Invalid username or e-mail.'));
            return $errors;
        }

        // Redefining user_login ensures we return the right case in the email.
        $user_login = $user_data->user_login;
        $user_email = $user_data->user_email;

        /**
         * Fires before a new password is retrieved.
         *
         * @since 1.5.0
         * @deprecated 1.5.1 Misspelled. Use 'retrieve_password' hook instead.
         *
         * @param string $user_login The user login name.
         */
        do_action( 'retreive_password', $user_login );

        /**
         * Fires before a new password is retrieved.
         *
         * @since 1.5.1
         *
         * @param string $user_login The user login name.
         */
        do_action( 'retrieve_password', $user_login );

        /**
         * Filter whether to allow a password to be reset.
         *
         * @since 2.7.0
         *
         * @param bool true           Whether to allow the password to be reset. Default true.
         * @param int  $user_data->ID The ID of the user attempting to reset a password.
         */
        $allow = apply_filters( 'allow_password_reset', true, $user_data->ID );

        if ( ! $allow ) {
            return new WP_Error( 'no_password_reset', __('Password reset is not allowed for this user') );
        } elseif ( is_wp_error( $allow ) ) {
            return $allow;
        }

        // Generate something random for a password reset key.
        $key = wp_generate_password( 20, false );

        /**
         * Fires when a password reset key is generated.
         *
         * @since 2.5.0
         *
         * @param string $user_login The username for the user.
         * @param string $key        The generated password reset key.
         */
        do_action( 'retrieve_password_key', $user_login, $key );

        // Now insert the key, hashed, into the DB.
        if ( empty( $wp_hasher ) ) {
            require_once ABSPATH . WPINC . '/class-phpass.php';
            $wp_hasher = new PasswordHash( 8, true );
        }
        $hashed = $hashed = time() . ':' .$wp_hasher->HashPassword( $key );
        $wpdb->update( $wpdb->users, array( 'user_activation_key' => $hashed ), array( 'user_login' => $user_login ) );

        $message = __('Someone requested that the password be reset for the following account:') . "\r\n\r\n";
        $message .= network_home_url( '/' ) . "\r\n\r\n";
        $message .= sprintf(__('Username: %s'), $user_login) . "\r\n\r\n";
        $message .= __('If this was a mistake, just ignore this email and nothing will happen.') . "\r\n\r\n";
        $message .= __('To reset your password, visit the following address:') . "\r\n\r\n";
        $message .= '<' . network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login') . ">\r\n";

        if ( is_multisite() )
            $blogname = $GLOBALS['current_site']->site_name;
        else
            /*
             * The blogname option is escaped with esc_html on the way into the database
             * in sanitize_option we want to reverse this for the plain text arena of emails.
             */
            $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

        $title = sprintf( __('[%s] Password Reset'), $blogname );

        /**
         * Filter the subject of the password reset email.
         *
         * @since 2.8.0
         *
         * @param string $title Default email title.
         */
        $title = apply_filters( 'retrieve_password_title', $title );

        /**
         * Filter the message body of the password reset mail.
         *
         * @since 2.8.0
         * @since 4.1.0 Added `$user_login` and `$user_data` parameters.
         *
         * @param string  $message    Default mail message.
         * @param string  $key        The activation key.
         * @param string  $user_login The username for the user.
         * @param WP_User $user_data  WP_User object.
         */
        $message = apply_filters( 'retrieve_password_message', $message, $key, $user_login, $user_data );

        if ( $message && !wp_mail( $user_email, wp_specialchars_decode( $title ), $message ) )
            wp_die( __('The e-mail could not be sent.') . "<br />\n" . __('Possible reason: your host may have disabled the mail() function.') );

        return true;
    }

    


    public function android($device_token, $message = null, array $args = array())
    {

       
              
        define( 'API_ACCESS_KEY', 'AIzaSyB-FUeES4zse4kDcrdgIOJZ1CbVgpGV-Aw' );
        $registrationIds = array('ez8ieQP9b5Y:APA91bEF-x-sR6ZRS2CkFO1mm0M5BQCnKYSFasdnmbjoLoHYNixSPZCM1fTMRtJ6_iUT-exzHwi2JvrsEdeoGHomk314jvhcyvbJA-bhXozXcaFRLMKU-Mf68HZv6X1SrCR6-P42j544');


          $msg = array
          (
            'message'   => 'HELLO',
         
          );
          $fields = array
          (
            'registration_ids'  => $registrationIds,
            'data'      => $msg
          );
           
          $headers = array
          (
            'Authorization: key=' . API_ACCESS_KEY,
            'Content-Type: application/json'
          );
           
          $ch = curl_init();
          curl_setopt( $ch,CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send' );
          //curl_setopt( $ch,CURLOPT_URL, 'https://android.googleapis.com/gcm/send' );
          curl_setopt( $ch,CURLOPT_POST, true );
          curl_setopt( $ch,CURLOPT_HTTPHEADER, $headers );
          curl_setopt( $ch,CURLOPT_RETURNTRANSFER, true );
          curl_setopt( $ch,CURLOPT_SSL_VERIFYPEER, false );
          curl_setopt( $ch,CURLOPT_POSTFIELDS, json_encode( $fields ) );
          $result = curl_exec($ch );
          curl_close( $ch );
          echo $result;
          exit;

    }
    ######################## PUSH NOTIFICATION ##########################

  
  #######################################  SCRIPT ##################################################
  public function update_storelocator(){
    global $wpdb;
        
    $result = $wpdb->get_results("SELECT `sl_id`,`sl_store`,`sl_linked_postid` FROM wp_store_locator");

    foreach ($result as $key => $store) {
      $title = trim($store->sl_store);
        
      $title = str_replace("&#39;", "â€™", $title);
   
      $postsID = $wpdb->get_var("SELECT `ID` FROM {$wpdb->prefix}posts WHERE post_type = 'green_grocer' AND post_status = 'publish' AND post_title  LIKE '%{$title}%' ");

      if($postsID != '') {

            echo "POST TITLE ---> ".$title;
            echo "\n";
            echo "POST ID ---> ";
            print_r( $postsID);
            echo "\n";

            print_r($store);
           

            $wpdb->update(
              'wp_store_locator',
              array(
                'sl_linked_postid'   => $postsID
              ),
              array(
                'sl_id'  => $store->sl_id
              )
            );

            echo  $wpdb->last_query;
            echo "\n";echo "\n";echo "\n";

      
      }
    }
  }

    public function update_seasondata(){
                  
     


        global $wpdb,$current_user;
        if(!$this->_post('month')) {
          throw new Exception('Please enter current month!',400);
        } 
        $currentMonth =  $this->_post('month');
        if(!$this->_post('id')) {
          throw new Exception('Season does not exist!',400);
        } 
        $seasonData = TablePress::$model_table->load($this->_post('id') , true, true);
        $err = is_wp_error($seasonData);
        if($err != '1') {
          unset($seasonData['data'][0]);
          foreach ($seasonData['data'] as $key => $value) {

            $postID = $value['14'];


            $fruitsTitle = $value[0];
            
            if (strpos($value[$currentMonth], '[color]&nbsp;[/color]') !== false) {
                  
              $sfruitsTitle = "%" . trim($fruitsTitle) . "%";
                  
                    

             // $getPostid =  $wpdb->get_var( $wpdb->prepare ("SELECT ID FROM `wp_posts` WHERE `post_type` = 'produce' AND `post_status` = 'publish' AND  `post_title` LIKE %s",$sfruitsTitle) ); 

              $ind = 0;
              if($postID != ''){

            

                $featured =   $image_id = get_post_thumbnail_id($postID);
                $image = wp_get_attachment_image_src($featured, 'full');
                $image = $image[0];

                if ($image != '') { $image = $image; } else { $image = ''; }

                  $frt[] = array(
                      'id' => $postID,
                      'title' => trim(ucfirst($fruitsTitle)),
                      'image' => $image
                  );
            
                  $ind++;
              }

            } else if($value[$currentMonth] != ''){

                $postID1 = $value['14'];

              $pfruitsTitle = "%" . trim($fruitsTitle) . "%";

              //$getPostid1 =  $wpdb->get_var( $wpdb->prepare ("SELECT ID FROM `wp_posts` WHERE `post_type` = 'produce' AND `post_status` = 'publish' AND  `post_title` LIKE %s",$pfruitsTitle)); 

              if($postID1 != ''){

               
                  $featured =   $image_id = get_post_thumbnail_id($postID1);
                  $image = wp_get_attachment_image_src($featured, 'full');
                  $image = $image[0];

                  if ($image != '') { $image = $image; } else { $image = ''; }

                      $veg[] = array(
                              'id' => $postID1,
                              'title' => trim(ucfirst($fruitsTitle)),
                               'image' => $image
                          );

                    }
              }   
          }
      
        } else {
          return array();
        }
        

        print_r($frt);
        print_r($veg);
        exit;
        

        $return = array(
          'shoulder_season' => $frt,
          'peak_season' => $veg
        );

        return  $return;

    }
  #######################################  SCRIPT ##################################################

  public function inseason_list_live(){
    
        global $wpdb,$current_user;

        if(!$this->_post('month')) {
          throw new Exception('Please enter current month!',400);
        } 

        $currentMonth =  $this->_post('month');

        if(!$this->_post('id')) {
          throw new Exception('Season does not exist!',400);
        } 

        $seasonData = TablePress::$model_table->load($this->_post('id') , true, true);

        $err = is_wp_error($seasonData);

        if($err != '1') {

          unset($seasonData['data'][0]);
          foreach ($seasonData['data'] as $key => $value) {

              $fruitsTitle = $value[0];
            
              if (strpos($value[$currentMonth], '[color]&nbsp;[/color]') !== false) {

                    
                    $sfruitsTitle = "%" . trim($fruitsTitle) . "%";
                   
                    $getPostid =  $value['14'];

                    $ind = 0;

                        $featured =   $image_id = get_post_thumbnail_id($getPostid);
                        $image = wp_get_attachment_image_src($featured, 'full');
                        $image = $image[0];

                        if ($image != '') { $image = $image; } else { $image = ''; }

                        $frt[] = array(
                                'id' => $getPostid,
                                'title' => trim(ucfirst($fruitsTitle)),
                                'image' => $image
                            );

                      $ind++;


                 

              } else if($value[$currentMonth] != ''){

                    $pfruitsTitle = "%" . trim($fruitsTitle) . "%";

                    $getPostid1 =  $value['14'];
                    $featured =   $image_id = get_post_thumbnail_id($getPostid1);
                    $image = wp_get_attachment_image_src($featured, 'full');
                    $image = $image[0];

                    if ($image != '') { $image = $image; } else { $image = ''; }

                    $veg[] = array(
                             'id' => $getPostid1,
                             'title' => trim(ucfirst($fruitsTitle)),
                             'image' => $image
                             );

                    
              }   
          }
      
        } else {
          return array();
        }
      

        $return = array(
          'shoulder_season' => $frt,
          'peak_season' => $veg
        );

        return  $return;

    }
  

    public function auto_empty_token() {
      global $wpdb;

      $date = date("Y-m-d");
      $predate = date ( 'Y-m-d H:i:s', strtotime ( '-4 day' , strtotime ( $date ) )) ;

      //SELECT * FROM wp_users_token WHERE `created_at` < '2016-07-02 00:00:00' 

      //DELETE * FROM wp_users_token WHERE `created_at` < '2016-07-02 00:00:00' 
     

      $result = $wpdb->get_results("SELECT * FROM wp_users_token WHERE `created_at`  < '$predate'");

      echo $wpdb->last_query;exit;
      print_r($result);

      exit;

    }    

}
