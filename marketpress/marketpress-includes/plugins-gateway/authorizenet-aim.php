<?php
/*
MarketPress Authorize.net AIM Gateway Plugin
Author: S H Mohanjith (Incsub)
*/

class MP_Gateway_AuthorizeNet_AIM extends MP_Gateway_API {

  //private gateway slug. Lowercase alpha (a-z) and dashes (-) only please!
  var $plugin_name = 'authorizenet-aim';
  
  //name of your gateway, for the admin side.
  var $admin_name = '';
  
  //public name of your gateway, for lists and such.
  var $public_name = '';

  //url for an image for your checkout method. Displayed on checkout form if set
  var $method_img_url = '';
  
  //url for an submit button image for your checkout method. Displayed on checkout form if set
  var $method_button_img_url = '';

  //always contains the url to send payment notifications to if needed by your gateway. Populated by the parent class
  var $ipn_url;

	//whether if this is the only enabled gateway it can skip the payment_form step
  var $skip_form = false;

  //credit card vars
  var $API_Username, $API_Password, $API_Signature, $SandboxFlag, $returnURL, $cancelURL, $API_Endpoint, $version, $currencyCode, $locale;
    
  /****** Below are the public methods you may overwrite via a plugin ******/
  
  /**
   * Runs when your class is instantiated. Use to setup your plugin instead of __construct()
   */
  function on_creation() {
    global $mp;
    $settings = get_option('mp_settings');
    
    //set names here to be able to translate
    $this->admin_name = __('Authorize.net Checkout (Beta)', 'mp');
    $this->public_name = __('Credit Card', 'mp');
    
    $this->method_img_url = $mp->plugin_url . 'images/credit_card.png';
    $this->method_button_img_url = $mp->plugin_url . 'images/cc-button.png';
    
    $this->version = "63.0"; //api version
    $this->force_ssl = true;
    
    //set credit card vars
    if ( isset( $settings['gateways']['authorizenet-aim'] ) ) {
      $this->API_Username = $settings['gateways']['authorizenet-aim']['api_user'];
      $this->API_Password = $settings['gateways']['authorizenet-aim']['api_pass'];
      $this->API_Signature = $settings['gateways']['authorizenet-aim']['api_sig'];
      $this->currencyCode = $settings['gateways']['authorizenet-aim']['currency'];
      $this->locale = $settings['gateways']['authorizenet-aim']['locale'];
      //set api urls
      if ($settings['gateways']['authorizenet-aim']['mode'] == 'sandbox')	{
        $this->API_Endpoint = "https://test.authorize.net/gateway/transact.dll";
      } else {
        $this->API_Endpoint = "https://secure.authorize.net/gateway/transact.dll";
      }
    }
  }

  /**
   * Echo fields you need to add to the top of the payment screen, like your credit card info fields
   *
   * @param array $shipping_info. Contains shipping info and email in case you need it
   */
  function payment_form($global_cart, $shipping_info) {
    global $mp;
    if (isset($_GET['cancel'])) {
      echo '<div class="mp_checkout_error">' . __('Your credit card transaction has been canceled.', 'mp') . '</div>';
    }
    
    $settings = get_option('mp_settings');
    $meta = get_user_meta($current_user->ID, 'mp_billing_info');
    
    $email = (!empty($_SESSION['mp_billing_info']['email'])) ? $_SESSION['mp_billing_info']['email'] : (!empty($meta['email'])?$meta['email']:$_SESSION['mp_shipping_info']['email']);
    $name = (!empty($_SESSION['mp_billing_info']['name'])) ? $_SESSION['mp_billing_info']['name'] : (!empty($meta['name'])?$meta['name']:$_SESSION['mp_shipping_info']['name']);
    $address1 = (!empty($_SESSION['mp_billing_info']['address1'])) ? $_SESSION['mp_billing_info']['address1'] : (!empty($meta['address1'])?$meta['address1']:$_SESSION['mp_shipping_info']['address1']);
    $address2 = (!empty($_SESSION['mp_billing_info']['address2'])) ? $_SESSION['mp_billing_info']['address2'] : (!empty($meta['address2'])?$meta['address2']:$_SESSION['mp_shipping_info']['address2']);
    $city = (!empty($_SESSION['mp_billing_info']['city'])) ? $_SESSION['mp_billing_info']['city'] : (!empty($meta['city'])?$meta['city']:$_SESSION['mp_shipping_info']['city']);
    $state = (!empty($_SESSION['mp_billing_info']['state'])) ? $_SESSION['mp_billing_info']['state'] : (!empty($meta['state'])?$meta['state']:$_SESSION['mp_shipping_info']['state']);
    $zip = (!empty($_SESSION['mp_billing_info']['zip'])) ? $_SESSION['mp_billing_info']['zip'] : (!empty($meta['zip'])?$meta['zip']:$_SESSION['mp_shipping_info']['zip']);
    $country = (!empty($_SESSION['mp_billing_info']['country'])) ? $_SESSION['mp_billing_info']['country'] : (!empty($meta['country'])?$meta['country']:$_SESSION['mp_shipping_info']['country']);
    if (!$country)
      $country = $settings['base_country'];
    $phone = (!empty($_SESSION['mp_billing_info']['phone'])) ? $_SESSION['mp_billing_info']['phone'] : (!empty($meta['phone'])?$meta['phone']:$_SESSION['mp_shipping_info']['phone']);
    ?>
      <style type="text/css">
        .cardimage {
          height: 23px;
          width: 157px;
          display: inline-table;
        }
        
        .nocard {
          background-position: 0px 0px !important;
        }
        
        .visa_card {
          background-position: 0px -23px !important;
        }
        
        .mastercard {
          background-position: 0px -46px !important;
        }
        
        .discover_card {
          background-position: 0px -69px !important;
        }
        
        .amex {
          background-position: 0px -92px !important;
        }
      </style>
      <script type="text/javascript">
        function cc_card_pick(card_image, card_num){
          if (card_image == null) {
                  card_image = '#cardimage';
          }
          if (card_num == null) {
                  card_num = '#card_num';
          }
  
          numLength = jQuery(card_num).val().length;
          number = jQuery(card_num).val();
          if (numLength > 10)
          {
                  if((number.charAt(0) == '4') && ((numLength == 13)||(numLength==16))) { jQuery(card_image).removeClass(); jQuery(card_image).addClass('cardimage visa_card'); }
                  else if((number.charAt(0) == '5' && ((number.charAt(1) >= '1') && (number.charAt(1) <= '5'))) && (numLength==16)) { jQuery(card_image).removeClass(); jQuery(card_image).addClass('cardimage mastercard'); }
                  else if(number.substring(0,4) == "6011" && (numLength==16)) 	{ jQuery(card_image).removeClass(); jQuery(card_image).addClass('cardimage amex'); }
                  else if((number.charAt(0) == '3' && ((number.charAt(1) == '4') || (number.charAt(1) == '7'))) && (numLength==15)) { jQuery(card_image).removeClass(); jQuery(card_image).addClass('cardimage discover_card'); }
                  else { jQuery(card_image).removeClass(); jQuery(card_image).addClass('cardimage nocard'); }
  
          }
        }
        jQuery(document).ready( function() {
          jQuery(".noautocomplete").attr("autocomplete", "off");
        });
      </script>
      <table class="mp_cart_billing">
        <thead><tr>
          <th colspan="2"><?php _e('Enter Your Billing Information:', 'mp'); ?></th>
        </tr></thead>
        <tbody>
        <tr>
          <td align="right"><?php _e('Email:', 'mp'); ?>*</td><td>
        <?php echo apply_filters( 'mp_checkout_error_email' ); ?>
        <input size="35" name="email" type="text" value="<?php echo esc_attr($email); ?>" /></td>
          </tr>
  
          <tr>
          <td align="right"><?php _e('Full Name:', 'mp'); ?>*</td><td>
        <?php echo apply_filters( 'mp_checkout_error_name' ); ?>
        <input size="35" name="name" type="text" value="<?php echo esc_attr($name); ?>" /> </td>
          </tr>
  
          <tr>
          <td align="right"><?php _e('Address:', 'mp'); ?>*</td><td>
        <?php echo apply_filters( 'mp_checkout_error_address1' ); ?>
        <input size="45" name="address1" type="text" value="<?php echo esc_attr($address1); ?>" /><br />
        <small><em><?php _e('Street address, P.O. box, company name, c/o', 'mp'); ?></em></small>
        </td>
          </tr>
  
          <tr>
          <td align="right"><?php _e('Address 2:', 'mp'); ?>&nbsp;</td><td>
        <input size="45" name="address2" type="text" value="<?php echo esc_attr($address2); ?>" /><br />
        <small><em><?php _e('Apartment, suite, unit, building, floor, etc.', 'mp'); ?></em></small>
        </td>
          </tr>
  
          <tr>
          <td align="right"><?php _e('City:', 'mp'); ?>*</td><td>
        <?php echo apply_filters( 'mp_checkout_error_city' ); ?>
        <input size="25" name="city" type="text" value="<?php echo esc_attr($city); ?>" /></td>
          </tr>
  
          <tr>
          <td align="right"><?php _e('State/Province/Region:', 'mp'); ?>*</td><td>
        <?php echo apply_filters( 'mp_checkout_error_state' ); ?>
        <input size="15" name="state" type="text" value="<?php echo esc_attr($state); ?>" /></td>
          </tr>
  
          <tr>
          <td align="right"><?php _e('Postal/Zip Code:', 'mp'); ?>*</td><td>
        <?php echo apply_filters( 'mp_checkout_error_zip' ); ?>
        <input size="10" id="mp_zip" name="zip" type="text" value="<?php echo esc_attr($zip); ?>" /></td>
          </tr>
  
          <tr>
          <td align="right"><?php _e('Country:', 'mp'); ?>*</td><td>
          <?php echo apply_filters( 'mp_checkout_error_country' ); ?>
        <select id="mp_" name="country">
          <?php
          foreach ((array)$settings['shipping']['allowed_countries'] as $code) {
            ?><option value="<?php echo $code; ?>"<?php selected($country, $code); ?>><?php echo esc_attr($mp->countries[$code]); ?></option><?php
          }
          ?>
        </select>
        </td>
          </tr>
  
          <tr>
          <td align="right"><?php _e('Phone Number:', 'mp'); ?></td><td>
        <input size="20" name="phone" type="text" value="<?php echo esc_attr($phone); ?>" /></td>
          </tr>
          
          <tr>
            <td align="right"><?php _e('Credit Card Number:', 'mp'); ?>*</td>
            <td>
              <?php echo apply_filters( 'mp_checkout_error_card_num' ); ?>
              <input name="card_num" onkeyup="cc_card_pick('#cardimage', '#card_num');"
               id="card_num" class="credit_card_number input_field noautocomplete"
               type="text" size="22" maxlength="22" />
        <div class="hide_after_success nocard cardimage"  id="cardimage"
             style="background: url(<?php echo $mp->plugin_url; ?>images/card_array.png) no-repeat;"></div></td>
          </tr>
          
          <tr>
            <td align="right"><?php _e('Expiration Date:', 'mp'); ?>*</td>
            <td>
              <?php echo apply_filters( 'mp_checkout_error_exp' ); ?>
              <label class="inputLabel" for="exp_month"><?php _e('Month', 'mp'); ?></label>
        <select name="exp_month" id="exp_month">
          <?php print $this->_print_month_dropdown(); ?>
        </select>
        <label class="inputLabel" for="exp_year"><?php _e('Year', 'mp'); ?></label>
        <select name="exp_year" id="exp_year">
          <?php print $this->_print_year_dropdown('', true); ?>
        </select>
        </td>
          </tr>
          
          <tr>
            <td align="right"><?php _e('Security Code:', 'mp'); ?></td>
            <td><?php echo apply_filters( 'mp_checkout_error_card_code' ); ?>
            <input id="card_code" name="card_code" class="input_field noautocomplete"
               style="width: 70px;" type="text" size="4" maxlength="4" /></td>
          </tr>
  
        <?php do_action( 'mp_checkout_billing_field' ); ?>
  
        </tbody>
      </table>
    <?php
  }
  
  function _print_year_dropdown($sel='', $pfp = false)
  {
          $localDate=getdate();
          $minYear = $localDate["year"];
          $maxYear = $minYear + 15;
  
          $output =  "<option value=''>--</option>";
          for($i=$minYear; $i<$maxYear; $i++) {
                  if ($pfp) {
                          $output .= "<option value='". substr($i, 0, 4) ."'".($sel==(substr($i, 0, 4))?' selected':'').
                          ">". $i ."</option>";
                  } else {
                          $output .= "<option value='". substr($i, 2, 2) ."'".($sel==(substr($i, 2, 2))?' selected':'').
                  ">". $i ."</option>";
                  }
          }
          return($output);
  }
  
  function _print_month_dropdown($sel='')
  {
          $output =  "<option value=''>--</option>";
          $output .=  "<option " . ($sel==1?' selected':'') . " value='01'>01 - Jan</option>";
          $output .=  "<option " . ($sel==2?' selected':'') . "  value='02'>02 - Feb</option>";
          $output .=  "<option " . ($sel==3?' selected':'') . "  value='03'>03 - Mar</option>";
          $output .=  "<option " . ($sel==4?' selected':'') . "  value='04'>04 - Apr</option>";
          $output .=  "<option " . ($sel==5?' selected':'') . "  value='05'>05 - May</option>";
          $output .=  "<option " . ($sel==6?' selected':'') . "  value='06'>06 - Jun</option>";
          $output .=  "<option " . ($sel==7?' selected':'') . "  value='07'>07 - Jul</option>";
          $output .=  "<option " . ($sel==8?' selected':'') . "  value='08'>08 - Aug</option>";
          $output .=  "<option " . ($sel==9?' selected':'') . "  value='09'>09 - Sep</option>";
          $output .=  "<option " . ($sel==10?' selected':'') . "  value='10'>10 - Oct</option>";
          $output .=  "<option " . ($sel==11?' selected':'') . "  value='11'>11 - Nov</option>";
          $output .=  "<option " . ($sel==12?' selected':'') . "  value='12'>12 - Doc</option>";
  
          return($output);
  }
  
  /**
   * Use this to process any fields you added. Use the $_POST global,
   *  and be sure to save it to both the $_SESSION and usermeta if logged in.
   *  DO NOT save credit card details to usermeta as it's not PCI compliant.
   *  Call $mp->cart_checkout_error($msg, $context); to handle errors. If no errors
   *  it will redirect to the next step.
   *
   * @param array $shipping_info. Contains shipping info and email in case you need it
   */
  function process_payment_form($global_cart, $shipping_info) {
    global $mp;
    $settings = get_option('mp_settings');
    
    if (!is_email($_POST['email']))
      $mp->cart_checkout_error('Please enter a valid Email Address.', 'email');
    
    if (empty($_POST['name']))
      $mp->cart_checkout_error('Please enter your Full Name.', 'name');
    
    if (empty($_POST['address1']))
      $mp->cart_checkout_error('Please enter your Street Address.', 'address1');
    
    if (empty($_POST['city']))
      $mp->cart_checkout_error('Please enter your City.', 'city');
    
    if (($_POST['country'] == 'US' || $_POST['country'] == 'CA') && empty($_POST['state']))
      $mp->cart_checkout_error('Please enter your State/Province/Region.', 'state');
    
    if (empty($_POST['zip']))
      $mp->cart_checkout_error('Please enter your Zip/Postal Code.', 'zip');
    
    if (empty($_POST['country']) || strlen($_POST['country']) != 2)
      $mp->cart_checkout_error('Please enter your Country.', 'country');
    
    //for checkout plugins
    do_action( 'mp_billing_process' );
    
    //save to session
    global $current_user;
    $meta = get_user_meta($current_user->ID, 'mp_billing_info');
    $_SESSION['mp_billing_info']['email'] = ($_POST['email']) ? trim(stripslashes($_POST['email'])) : $current_user->user_email;
    $_SESSION['mp_billing_info']['name'] = ($_POST['name']) ? trim(stripslashes($_POST['name'])) : $current_user->user_firstname . ' ' . $current_user->user_lastname;
    $_SESSION['mp_billing_info']['address1'] = ($_POST['address1']) ? trim(stripslashes($_POST['address1'])) : $meta['address1'];
    $_SESSION['mp_billing_info']['address2'] = ($_POST['address2']) ? trim(stripslashes($_POST['address2'])) : $meta['address2'];
    $_SESSION['mp_billing_info']['city'] = ($_POST['city']) ? trim(stripslashes($_POST['city'])) : $meta['city'];
    $_SESSION['mp_billing_info']['state'] = ($_POST['state']) ? trim(stripslashes($_POST['state'])) : $meta['state'];
    $_SESSION['mp_billing_info']['zip'] = ($_POST['zip']) ? trim(stripslashes($_POST['zip'])) : $meta['zip'];
    $_SESSION['mp_billing_info']['country'] = ($_POST['country']) ? trim($_POST['country']) : $meta['country'];
    $_SESSION['mp_billing_info']['phone'] = ($_POST['phone']) ? preg_replace('/[^0-9-\(\) ]/', '', trim($_POST['phone'])) : $meta['phone'];

    //save to user meta
    if ($current_user->ID)
      update_user_meta($current_user->ID, 'mp_billing_info', $_SESSION['mp_billing_info']);
    
    if (!isset($_POST['exp_month']) || !isset($_POST['exp_year']) || empty($_POST['exp_month']) || empty($_POST['exp_year'])) {
      $mp->cart_checkout_error( __('Please select your credit card expiration date.', 'mp'), 'exp');
    }
    
    if (!isset($_POST['card_code']) || empty($_POST['card_code'])) {
      $mp->cart_checkout_error( __('Please enter your credit card security code', 'mp'), 'card_code');
    }
    
    if (!isset($_POST['card_num']) || empty($_POST['card_num'])) {
      $mp->cart_checkout_error( __('Please enter your credit card number', 'mp'), 'card_num');
    } else {
      if ($this->_get_card_type($_POST['card_num']) == "") {
        $mp->cart_checkout_error( __('Please enter a valid credit card number', 'mp'), 'card_num');
      }
    }
    
    if (!$mp->checkout_error) {
      if (
        ($this->_get_card_type($_POST['card_num']) == "American Express" && strlen($_POST['card_code']) != 4) || 
        ($this->_get_card_type($_POST['card_num']) != "American Express" && strlen($_POST['card_code']) != 3)
        ) {
        $mp->cart_checkout_error(__('Please enter a valid credit card security code', 'mp'), 'card_code');
      }
    }
    
    if (!$mp->checkout_error) {
      $_SESSION['card_num'] = $_POST['card_num'];
      $_SESSION['card_code'] = $_POST['card_code'];
      $_SESSION['exp_month'] = $_POST['exp_month'];
      $_SESSION['exp_year'] = $_POST['exp_year'];
      
      $mp->generate_order_id();
    }
  }
  
  function _get_card_type($number) {
    $num_length = strlen($number);
    
    if ($num_length > 10 && preg_match('/[0-9]+/', $number) >= 1)
    {
      if((substr($number, 0, 1) == '4') && (($num_length == 13)||($num_length == 16))) {
        return "Visa";
      } else if((substr($number, 0, 1) == '5' && ((substr($number, 1, 1) >= '1') && (substr($number, 1, 1) <= '5'))) && ($num_length == 16)) {
        return "Mastercard";
      } else if(substr($number, 0, 4) == "6011" && ($num_length == 16)) {
        return "Discover Card";
      } else if((substr($number, 0, 1) == '3' && ((substr($number, 1, 1) == '4') || (substr($number, 1, 1) == '7'))) && ($num_length == 15)) {
        return "American Express";
      }
    }
    return "";
  }
  
  /**
   * Echo the chosen payment details here for final confirmation. You probably don't need
   *  to post anything in the form as it should be in your $_SESSION var already.
   *
   * @param array $shipping_info. Contains shipping info and email in case you need it
   */
  function confirm_payment_form($global_cart, $shipping_info) {
    global $mp;
    
    $settings = get_option('mp_settings');
    $meta = get_user_meta($current_user->ID, 'mp_billing_info');
    
    $email = (!empty($_SESSION['mp_billing_info']['email'])) ? $_SESSION['mp_billing_info']['email'] : (!empty($meta['email'])?$meta['email']:$_SESSION['mp_shipping_info']['email']);
    $name = (!empty($_SESSION['mp_billing_info']['name'])) ? $_SESSION['mp_billing_info']['name'] : (!empty($meta['name'])?$meta['name']:$_SESSION['mp_shipping_info']['name']);
    $address1 = (!empty($_SESSION['mp_billing_info']['address1'])) ? $_SESSION['mp_billing_info']['address1'] : (!empty($meta['address1'])?$meta['address1']:$_SESSION['mp_shipping_info']['address1']);
    $address2 = (!empty($_SESSION['mp_billing_info']['address2'])) ? $_SESSION['mp_billing_info']['address2'] : (!empty($meta['address2'])?$meta['address2']:$_SESSION['mp_shipping_info']['address2']);
    $city = (!empty($_SESSION['mp_billing_info']['city'])) ? $_SESSION['mp_billing_info']['city'] : (!empty($meta['city'])?$meta['city']:$_SESSION['mp_shipping_info']['city']);
    $state = (!empty($_SESSION['mp_billing_info']['state'])) ? $_SESSION['mp_billing_info']['state'] : (!empty($meta['state'])?$meta['state']:$_SESSION['mp_shipping_info']['state']);
    $zip = (!empty($_SESSION['mp_billing_info']['zip'])) ? $_SESSION['mp_billing_info']['zip'] : (!empty($meta['zip'])?$meta['zip']:$_SESSION['mp_shipping_info']['zip']);
    $country = (!empty($_SESSION['mp_billing_info']['country'])) ? $_SESSION['mp_billing_info']['country'] : (!empty($meta['country'])?$meta['country']:$_SESSION['mp_shipping_info']['country']);
    if (!$country)
      $country = $settings['base_country'];
    $phone = (!empty($_SESSION['mp_billing_info']['phone'])) ? $_SESSION['mp_billing_info']['phone'] : (!empty($meta['phone'])?$meta['phone']:$_SESSION['mp_shipping_info']['phone']);

    $content = '';
    
    $content .= '<table class="mp_cart_billing">';
    $content .= '<thead><tr>';
    $content .= '<th>'.__('Billing Information:', 'mp').'</th>';
    $content .= '<th align="right"><a href="'. mp_checkout_step_url('checkout').'">'.__('&laquo; Edit', 'mp').'</a></th>';
    $content .= '</tr></thead>';
    $content .= '<tbody>';
    $content .= '<tr>';
    $content .= '<td align="right">'.__('Email:', 'mp').'</td><td>';
    $content .= esc_attr($email).'</td>';
    $content .= '</tr>';
    $content .= '<tr>';
    $content .= '<td align="right">'.__('Full Name:', 'mp').'</td><td>';
    $content .= esc_attr($name).'</td>';
    $content .= '</tr>';
    $content .= '<tr>';
    $content .= '<td align="right">'.__('Address:', 'mp').'</td>';
    $content .= '<td>'.esc_attr($address1).'</td>';
    $content .= '</tr>';
    
    if ($address2) {
      $content .= '<tr>';
      $content .= '<td align="right">'.__('Address 2:', 'mp').'</td>';
      $content .= '<td>'.esc_attr($address2).'</td>';
      $content .= '</tr>';
    }
  
    $content .= '<tr>';
    $content .= '<td align="right">'.__('City:', 'mp').'</td>';
    $content .= '<td>'.esc_attr($city).'</td>';
    $content .= '</tr>';
    
    if ($state) {
      $content .= '<tr>';
      $content .= '<td align="right">'.__('State/Province/Region:', 'mp').'</td>';
      $content .= '<td>'.esc_attr($state).'</td>';
      $content .= '</tr>';
    }
    
    $content .= '<tr>';
    $content .= '<td align="right">'.__('Postal/Zip Code:', 'mp').'</td>';
    $content .= '<td>'.esc_attr($zip).'</td>';
    $content .= '</tr>';
    $content .= '<tr>';
    $content .= '<td align="right">'.__('Country:', 'mp').'</td>';
    $content .= '<td>'.$mp->countries[$country].'</td>';
    $content .= '</tr>';
    
    if ($phone) {
      $content .= '<tr>';
      $content .= '<td align="right">'.__('Phone Number:', 'mp').'</td>';
      $content .= '<td>'.esc_attr($phone).'</td>';
      $content .= '</tr>';
    }
    
    $content .= '<tr>';
    $content .= '<td align="right">'.__('Payment method:', 'mp').'</td>';
    $content .= '<td>'.$this->_get_card_type($_SESSION['card_num']).'ending in '. substr($_SESSION['card_num'], strlen($_SESSION['card_num'])-4, 4).'</td>';
    $content .= '</tr>';
    $content .= '</tbody>';
    $content .= '</table>';
    
    return $content;
  }

  /**
   * Use this to do the final payment. Create the order then process the payment. If
   *  you know the payment is successful right away go ahead and change the order status
   *  as well.
   *  Call $mp->cart_checkout_error($msg, $context); to handle errors. If no errors
   *  it will redirect to the next step.
   *
   * @param array $shipping_info. Contains shipping info and email in case you need it
   */
  function process_payment($global_cart, $shipping_info) {
    global $mp;
    
    $timestamp = time();
    $settings = get_option('mp_settings');
    $billing_info = $_SESSION['mp_billing_info'];
    
    $payment = new MP_Gateway_Worker_AuthorizeNet_AIM($this->API_Endpoint,
      $settings['gateways']['authorizenet-aim']['delim_data'],
      $settings['gateways']['authorizenet-aim']['delim_char'],
      $settings['gateways']['authorizenet-aim']['encap_char'],
      $settings['gateways']['authorizenet-aim']['api_user'],
      $settings['gateways']['authorizenet-aim']['api_key'],
      ($settings['gateways']['authorizenet-aim']['mode'] == 'sandbox'));
    
    $payment->transaction($_SESSION['card_num']);
    
    $totals = array();
    
    foreach ($global_cart as $bid => $cart) {
      foreach ($cart as $product_id => $variations) {
        foreach ($variations as $variation => $data) {
          $sku = empty($data['SKU'])?"{$product_id}{$variation}":$data['SKU'];
          $totals[] = $data['price'] * $data['quantity'];
          $payment->addLineItem($sku, $data['name'],
            get_permalink($product_id), $data['quantity'], $data['price'], 1);
          $i++;
        }
      }
    }
    $total = array_sum($totals);

    //coupon line
    if ( $coupon = $mp->coupon_value($mp->get_coupon_code(), $total) ) {
      $total = $coupon['new_total'];
    }

    //shipping line
    if ( ($shipping_price = $mp->shipping_price()) !== false ) {
      $total = $total + $shipping_price;
    }
    
    //tax line
    if ( ($tax_price = $mp->tax_price()) !== false ) {
      $total = $total + $tax_price;
    }
    
    // Billing Info
    $payment->setParameter("x_card_code", $_SESSION['card_code']);
    $payment->setParameter("x_exp_date ", $_SESSION['exp_month'] . $_SESSION['exp_year']);
    $payment->setParameter("x_amount", $total);
    
    // Order Info
    $payment->setParameter("x_description", "Order ID ".$_SESSION['mp_order']);
    $payment->setParameter("x_invoice_num",  $_SESSION['mp_order']);
    if ($settings['gateways']['authorizenet-aim']['mode'] == 'sandbox')	{
      $payment->setParameter("x_test_request", true);
    } else {
      $payment->setParameter("x_test_request", false);
    }
    $payment->setParameter("x_duplicate_window", 30);
    
    // E-mail
    $payment->setParameter("x_header_email_receipt", $settings['gateways']['authorizenet-aim']['header_email_receipt']);
    $payment->setParameter("x_footer_email_receipt", $settings['gateways']['authorizenet-aim']['footer_email_receipt']);
    $payment->setParameter("x_email_customer", strtoupper($settings['gateways']['authorizenet-aim']['email_customer']));
    
    $_names = split(" ", $billing_info['name']);
    if (isset($_names[0])) {
      $first_name = array_shift($_names);
    } else {
      $first_name = "";
    }
    
    if (isset($_names[0])) {
      $last_name = join(" ", $_names);
    } else {
      $last_name = "";
    }
    
    $address = $billing_info['address1'];
    
    if (!empty($billing_info['address2'])) {
      $address .= "\n".$billing_info['address2'];
    }
    
    //Customer Info
    $payment->setParameter("x_first_name", $first_name);
    $payment->setParameter("x_last_name", $last_name);
    $payment->setParameter("x_address", $address);
    $payment->setParameter("x_city", $billing_info['city']);
    $payment->setParameter("x_state", $billing_info['state']);
    $payment->setParameter("x_country", $billing_info['country']);
    $payment->setParameter("x_zip", $billing_info['zip']);
    $payment->setParameter("x_phone", $billing_info['phone']);
    $payment->setParameter("x_email", $billing_info['email']);
    
    $_names = split(" ", $shipping_info['name']);
    if (isset($_names[0])) {
      $shipping_first_name = array_shift($_names);
    } else {
      $shipping_first_name = "";
    }
    
    if (isset($_names[0])) {
      $shipping_last_name = join(" ", $_names);
    } else {
      $shipping_last_name = "";
    }
    
    $shipping_address = $shipping_info['address1'];
    
    if (!empty($billing_info['address2'])) {
      $shipping_address .= "\n".$shipping_info['address2'];
    }
    
    $payment->setParameter("x_ship_to_first_name", $shipping_first_name);
    $payment->setParameter("x_ship_to_last_name", $shipping_last_name);
    $payment->setParameter("x_ship_to_address", $shipping_address);
    $payment->setParameter("x_ship_to_city", $shipping_info['city']);
    $payment->setParameter("x_ship_to_state", $shipping_info['state']);
    $payment->setParameter("x_ship_to_country", $shipping_info['country']);
    $payment->setParameter("x_ship_to_zip", $shipping_info['zip']);
    
    $payment->setParameter("x_customer_ip", $_SERVER['REMOTE_ADDR']);
    
    $payment->process();
    
    if ($payment->isApproved()) {
      $status = __('The payment has been completed, and the funds have been added successfully to your account balance.', 'mp');
      $paid = true;
      
      $payment_info['gateway_public_name'] = $this->public_name;
      $payment_info['gateway_private_name'] = $this->admin_name;
      $payment_info['method'] = $payment->getMethod();
      $payment_info['status'][$timestamp] = "paid";
      $payment_info['total'] = $total;
      $payment_info['currency'] = "USD"; // Authorize.net only supports USD transactions
      $payment_info['transaction_id'] = $payment->getTransactionID();
      
      //succesful payment, create our order now
      $result = $mp->create_order($_SESSION['mp_order'], $global_cart, $shipping_info, $payment_info, $paid);
    } else {
      $error = $payment->getResponseText();
      $mp->cart_checkout_error( sprintf(__('There was a problem finalizing your purchase. %s Please <a href="%s">go back and try again</a>.', 'mp') , $error, mp_checkout_step_url('checkout')) );
    }
  }
  
  /**
   * Filters the order confirmation email message body. You may want to append something to
   *  the message. Optional
   *
   * Don't forget to return!
   */
  function order_confirmation_email($msg) {
    return $msg;
  }
  
  /**
   * Echo any html you want to show on the confirmation screen after checkout. This
   *  should be a payment details box and message.
   */
  function order_confirmation_msg($content, $order) {
    global $mp;
    if ($order->post_status == 'order_received') {
      $content .= '<p>' . sprintf(__('Your credit card payment for this order totaling %s is not yet complete. Here is the latest status:', 'mp'), $mp->format_currency($order->mp_payment_info['currency'], $order->mp_payment_info['total'])) . '</p>';
      $statuses = $order->mp_payment_info['status'];
      krsort($statuses); //sort with latest status at the top
      $status = reset($statuses);
      $timestamp = key($statuses);
      $content .= '<p><strong>' . date(get_option('date_format') . ' - ' . get_option('time_format'), $timestamp) . ':</strong>' . htmlentities($status) . '</p>';
    } else {
      $content .= '<p>' . sprintf(__('Your credit card payment for this order totaling %s is complete. The credit card transaction number is <strong>%s</strong>.', 'mp'), $mp->format_currency($order->mp_payment_info['currency'], $order->mp_payment_info['total']), $order->mp_payment_info['transaction_id']) . '</p>';
    }
    return $content;
  }
  
  function order_confirmation($order) {
    
  }
  
  /**
   * Echo a settings meta box with whatever settings you need for you gateway.
   *  Form field names should be prefixed with mp[gateways][plugin_name], like "mp[gateways][plugin_name][mysetting]".
   *  You can access saved settings via $settings array.
   */
  function gateway_settings_box($settings) {
    global $mp;
    ?>
    <div id="mp_authorizenet-aim_express" class="postbox">
      <h3 class='hndle'><span><?php _e('Authorize.net AIM Settings', 'mp'); ?></span></h3>
      <div class="inside">
        <span class="description"><?php _e('Authorize.net AIM is a customizable payment processing solution that gives the merchant control over all the steps in processing a transaction. SSL certificate is required to use this gateway.', 'mp') ?></span>
        <table class="form-table">
	  <tr>
	    <th scope="row"><?php _e('Mode', 'mp') ?></th>
	    <td>
              <p>
                <select name="mp[gateways][authorizenet-aim][mode]">
                  <option value="sandbox" <?php selected($settings['gateways']['authorizenet-aim']['mode'], 'sandbox') ?>><?php _e('Sandbox', 'mp') ?></option>
                  <option value="live" <?php selected($settings['gateways']['authorizenet-aim']['mode'], 'live') ?>><?php _e('Live', 'mp') ?></option>
                </select>
              </p>
	    </td>
	  </tr>
	  <tr>
	    <th scope="row"><?php _e('Gateway Credentials', 'mp') ?></th>
	    <td>
              <span class="description"><?php print sprintf(__('You must login to Authorize.net merchant dashboard to obtain the API login ID and API transaction key. <a target="_blank" href="%s">Instructions &raquo;</a>', 'mp'), "http://www.authorize.net/support/merchant/Integration_Settings/Access_Settings.htm"); ?></span>
	      <p>
		<label><?php _e('Login ID', 'mp') ?><br />
		  <input value="<?php echo esc_attr($settings['gateways']['authorizenet-aim']['api_user']); ?>" size="30" name="mp[gateways][authorizenet-aim][api_user]" type="text" />
		</label>
	      </p>
	      <p>
		<label><?php _e('Transaction Key', 'mp') ?><br />
		  <input value="<?php echo esc_attr($settings['gateways']['authorizenet-aim']['api_key']); ?>" size="30" name="mp[gateways][authorizenet-aim][api_key]" type="text" />
		</label>
	      </p>
	    </td>
	  </tr>
          <tr>
	    <th scope="row"><?php _e('Advanced Settings', 'mp') ?></th>
	    <td>
	      <span class="description"><?php _e('Optional settings to control advanced options', 'mp') ?></span>
              <p>
                <label><a title="<?php _e('Authorize.net default is \',\'. Otherwise, get this from your credit card processor. If the transactions are not going through, this character is most likely wrong.', 'mp'); ?>"><?php _e('Delimiter Character', 'mp'); ?></a><br />
                  <input value="<?php echo (empty($settings['gateways']['authorizenet-aim']['delim_char']))?",":esc_attr($settings['gateways']['authorizenet-aim']['delim_char']); ?>" size="2" name="mp[gateways][authorizenet-aim][delim_char]" type="text" />
                </label>
	      </p>
              
              <p>
		<label><a title="<?php _e('Authorize.net default is blank. Otherwise, get this from your credit card processor. If the transactions are going through, but getting strange responses, this character is most likely wrong.', 'mp'); ?>"><?php _e('Encapsulation Character', 'mp'); ?></a><br />
                  <input value="<?php echo esc_attr($settings['gateways']['authorizenet-aim']['encap_char']); ?>" size="2" name="mp[gateways][authorizenet-aim][encap_char]" type="text" />
                </label>
              </p>
              
              <p>
		<label><?php _e('Email Customer (on success):', 'mp'); ?><br />
                  <select name="mp[gateways][authorizenet-aim][email_customer]">
                    <option value="yes" <?php selected($settings['gateways']['authorizenet-aim']['email_customer'], 'yes') ?>><?php _e('Yes', 'mp') ?></option>
                    <option value="no" <?php selected($settings['gateways']['authorizenet-aim']['email_customer'], 'no') ?>><?php _e('No', 'mp') ?></option>
                  </select>
                </label>
              </p>
              
              <p>
		<label><a title="<?php _e('This text will appear as the header of the email receipt sent to the customer.', 'mp'); ?>"><?php _e('Customer Receipt Email Header', 'mp'); ?></a><br/>
                  <input value="<?php echo empty($settings['gateways']['authorizenet-aim']['header_email_receipt'])?__('Thanks for your payment!', 'mp'):esc_attr($settings['gateways']['authorizenet-aim']['header_email_receipt']); ?>" size="40" name="mp[gateways][authorizenet-aim][header_email_receipt]" type="text" />
                </label>
	      </p>
              
              <p>
		<label><a title="<?php _e('This text will appear as the footer on the email receipt sent to the customer.', 'mp'); ?>"><?php _e('Customer Receipt Email Footer', 'mp'); ?></a><br/>
                  <input value="<?php echo empty($settings['gateways']['authorizenet-aim']['footer_email_receipt'])?__('', 'mp'):esc_attr($settings['gateways']['authorizenet-aim']['footer_email_receipt']); ?>" size="40" name="mp[gateways][authorizenet-aim][footer_email_receipt]" type="text" />
                </label>
	      </p>
              
              <p>
		<label><a title="<?php _e('The payment gateway generated MD5 hash value that can be used to authenticate the transaction response. Not needed because responses are returned using an SSL connection.', 'mp'); ?>"><?php _e('Security: MD5 Hash', 'mp'); ?></a><br/>
                  <input value="<?php echo esc_attr($settings['gateways']['authorizenet-aim']['md5_hash']); ?>" size="32" name="mp[gateways][authorizenet-aim][md5_hash]" type="text" />
                </label>
              </p>

              <p>
		<label><a title="<?php _e('Request a delimited response from the payment gateway.', 'mp'); ?>"><?php _e('Delim Data:', 'mp'); ?></a><br/>
                  <select name="mp[gateways][authorizenet-aim][delim_data]">
                    <option value="yes" <?php selected($settings['gateways']['authorizenet-aim']['delim_data'], 'yes') ?>><?php _e('Yes', 'mp') ?></option>
                    <option value="no" <?php selected($settings['gateways']['authorizenet-aim']['delim_data'], 'no') ?>><?php _e('No', 'mp') ?></option>
                  </select>
                </label>
              </p>
              
	    </td>
	  </tr>
        </table>
      </div>
    </div>
    <?php
  }
  
  /**
   * Filters posted data from your settings form. Do anything you need to the $settings['gateways']['plugin_name']
   *  array. Don't forget to return!
   */
  function process_gateway_settings($settings) {
    return $settings;
  }
}

if(!class_exists('MP_Gateway_Worker_AuthorizeNet_AIM')) {
  class MP_Gateway_Worker_AuthorizeNet_AIM
  {
    var $login;
    var $transkey;
    var $params   = array();
    var $results  = array();
    var $line_items = array();

    var $approved = false;
    var $declined = false;
    var $error    = true;
    var $method   = "";

    var $fields;
    var $response;

    var $instances = 0;

    function __construct($url, $delim_data, $delim_char, $encap_char, $gw_username, $gw_tran_key, $gw_test_mode)
    {
      if ($this->instances == 0)
      {
	$this->url = $url;

	$this->params['x_delim_data']     = $delim_data;
	$this->params['x_delim_char']     = $delim_char;
	$this->params['x_encap_char']     = $encap_char;
	$this->params['x_relay_response'] = "FALSE";
	$this->params['x_url']            = "FALSE";
	$this->params['x_version']        = "3.1";
	$this->params['x_method']         = "CC";
	$this->params['x_type']           = "AUTH_CAPTURE";
	$this->params['x_login']          = $gw_username;
	$this->params['x_tran_key']       = $gw_tran_key;
	$this->params['x_test_request']   = $gw_test_mode;

	$this->instances++;
      } else {
	return false;
      }
    }

    function transaction($cardnum)
    {
      $this->params['x_card_num']  = trim($cardnum);
    }
    
    function addLineItem($id, $name, $description, $quantity, $price, $taxable = 0)
    {
      $this->line_items[] = "{$id}<|>{$name}<|>{$description}<|>{$quantity}<|>{$price}<|>{$taxable}";
    }

    function process($retries = 1)
    {
      global $mp;
      
      $this->_prepareParameters();
      $query_string = rtrim($this->fields, "&");
      
      $count = 0;
      while ($count < $retries)
      {
        $args['user-agent'] = "MarketPress/{$mp->version}: http://premium.wpmudev.org/project/e-commerce | Authorize.net AIM Plugin/{$mp->version}";
        $args['body'] = $query_string;
        $args['sslverify'] = false;
        
        //use built in WP http class to work with most server setups
        $response = wp_remote_post($this->url, $args);
        
        if (is_array($response) && isset($response['body'])) {
          $this->response = $response['body'];
        } else {
          $this->response = "";
          $this->error = true;
          return;
        }
        
	$this->parseResults();
        
	if ($this->getResultResponseFull() == "Approved")
	{
          $this->approved = true;
	  $this->declined = false;
	  $this->error    = false;
          $this->method   = $this->getMethod();
	  break;
	} else if ($this->getResultResponseFull() == "Declined")
	{
          $this->approved = false;
	  $this->declined = true;
	  $this->error    = false;
	  break;
	}
	$count++;
      }
    }

    function parseResults()
    {
      $this->results = explode($this->params['x_delim_char'], $this->response);
    }

    function setParameter($param, $value)
    {
      $param                = trim($param);
      $value                = trim($value);
      $this->params[$param] = $value;
    }

    function setTransactionType($type)
    {
      $this->params['x_type'] = strtoupper(trim($type));
    }

    function _prepareParameters()
    {
      foreach($this->params as $key => $value)
      {
	$this->fields .= "$key=" . urlencode($value) . "&";
      }
      for($i=0; $i<count($this->line_items); $i++) {
        $this->fields .= "x_line_item={$this->line_items[$i]}&";
      }
    }
    
    function getMethod()
    {
      if (isset($this->results[51]))
      {
        return str_replace($this->params['x_encap_char'],'',$this->results[51]);
      }
      return "";
    }

    function getGatewayResponse()
    {
      return str_replace($this->params['x_encap_char'],'',$this->results[0]);
    }

    function getResultResponseFull()
    {
      $response = array("", "Approved", "Declined", "Error");
      return $response[str_replace($this->params['x_encap_char'],'',$this->results[0])];
    }

    function isApproved()
    {
      return $this->approved;
    }

    function isDeclined()
    {
      return $this->declined;
    }

    function isError()
    {
      return $this->error;
    }

    function getResponseText()
    {
      return $this->results[3];
      $strip = array($this->params['x_delim_char'],$this->params['x_encap_char'],'|',',');
      return str_replace($strip,'',$this->results[3]);
    }

    function getAuthCode()
    {
      return str_replace($this->params['x_encap_char'],'',$this->results[4]);
    }

    function getAVSResponse()
    {
      return str_replace($this->params['x_encap_char'],'',$this->results[5]);
    }

    function getTransactionID()
    {
      return str_replace($this->params['x_encap_char'],'',$this->results[6]);
    }
  }
}

//register payment gateway plugin
mp_register_gateway_plugin( 'MP_Gateway_AuthorizeNet_AIM', 'authorizenet-aim', __('Authorize.net AIM Checkout (Beta)', 'mp') );
