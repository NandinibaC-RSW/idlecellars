<?php
class Wine_Club_UPS {


    var $upstool='https://www.ups.com/ups.app/xml/Rate';

    var $request;  //'rate' for single service or 'shop' for all possible services

    var $service='02';
    var $pickuptype='01'; // 01 daily pickup
      /* Pickup Type
	01- Daily Pickup
	03- Customer Counter
	06- One Time Pickup
	07- On Call Air
	11- Suggested Retail Rates
	19- Letter Center
	20- Air Service Center
      */
    var $residential;

  //ship from location or shipper
    var $s_zip;
    var $s_state;
    var $s_country;  

  //ship to location
    var $t_zip;
    var $t_state;
    var $t_country;

  //package info
    var $package_type;  // 02 customer supplied package

    var $weight;


    var $error=0;
    var $errormsg;

    var $xmlarray = array(); 

    var $xmlreturndata = ""; 
          
   
    public function __construct($szip,$sstate,$scountry) 
    { 
        // init function 
      $this->s_zip = $szip;
      $this->s_state = $sstate;
      $this->s_country = $scountry;

      
    } 

    function construct_request_xml(){
      $ups_data = get_option('woocommerce_wf_shipping_ups_settings');
      $userid = $ups_data['user_id']; 
      $passwd = $ups_data['password']; 
      $accesskey = $ups_data['access_key']; 

      $xml="<?xml version=\"1.0\"?>
<AccessRequest xml:lang=\"en-US\">
   <AccessLicenseNumber>$accesskey</AccessLicenseNumber>
   <UserId>$userid</UserId>
   <Password>$passwd</Password>
</AccessRequest>
<?xml version=\"1.0\"?>
<RatingServiceSelectionRequest xml:lang=\"en-US\">
  <Request>
    <TransactionReference>
      <CustomerContext>Rating and Service</CustomerContext>
      <XpciVersion>1.0001</XpciVersion>
    </TransactionReference>
    <RequestAction>Rate</RequestAction> 
    <RequestOption>$this->request</RequestOption> 
  </Request>
  <PickupType>
  <Code>$this->pickuptype</Code>
  </PickupType>
  <Shipment>
    <Shipper>
      <Address>
    <StateProvinceCode>$this->s_state</StateProvinceCode>
    <PostalCode>$this->s_zip</PostalCode>
    <CountryCode>$this->s_country</CountryCode>
      </Address>
    </Shipper>
    <ShipTo>
      <Address>
    <StateProvinceCode>$this->t_state</StateProvinceCode>
    <PostalCode>$this->t_zip</PostalCode>
    <CountryCode>$this->t_country</CountryCode>
    
<ResidentialAddressIndicator>$this->residential</ResidentialAddressIndicator>
      </Address>
    </ShipTo>
    <Service>
    <Code>$this->service</Code>
    </Service>
    <Package>
      <PackagingType>
        <Code>$this->package_type</Code>
        <Description>Package</Description>
      </PackagingType>
      <Description>Rate Shopping</Description>
      <PackageWeight>
        <Weight>$this->weight</Weight>
      </PackageWeight>
    </Package>
    <ShipmentServiceOptions/>
  </Shipment>
</RatingServiceSelectionRequest>";
       return $xml;
    }

 
    function rate($service='',$tzip,$tstate='',$tcountry='US',
	$weight,$residential=0,$packagetype='02') 
    { 
       if($service=='')
          $this->request = 'shop';
       else
	  $this->request = 'rate';

     	$this->service = $service; 
	$this->t_zip = $tzip;
	$this->t_state= $tstate;
	$this->t_country = $tcountry;
	$this->weight = $weight;
	$this->residential=$residential;
	$this->package_type=$packagetype;

	
        $this->__runCurl(); 
        $this->xmlarray = $this->_get_xml_array($this->xmlreturndata); 

        //check if error occurred
	if($this->xmlarray==""){
          $this->error=0;
	  $this->errormsg="Unable to retrieve the Shipping info";
	  return NULL;
        }
        
        $values = $this->xmlarray['RatingServiceSelectionResponse']['Response'][0];

	if($values['ResponseStatusCode'] == 0){
	  $this->error=$values[Error][0][ErrorCode];
	  $this->errormsg=$values[Error][0][ErrorDescription];
          return NULL;
        }
 	return $this->get_rates();

    } 
    /** 
    * __runCurl() 
    * 
    * This is processes the curl command. 
    * 
    * @access    private 
    */ 
    function __runCurl() 
    { 

	$y = $this->construct_request_xml();
        $ch = curl_init(); 
        curl_setopt ($ch, CURLOPT_URL,$this->upstool); /// set the post-to url (do not include the ?query+string here!) 
        curl_setopt ($ch, CURLOPT_HEADER, 0); /// Header control 
        curl_setopt ($ch, CURLOPT_POST, 1);  /// tell it to make a POST, not a GET 
        curl_setopt ($ch, CURLOPT_POSTFIELDS, "$y");  /// put the querystring here starting with "?" 
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1); /// This allows the output to be set into a variable $xyz 
        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $this->xmlreturndata = curl_exec ($ch); /// execute the curl session and return the output to a variable $xyz 
        print_r($this->xmlreturndata);exit();
        curl_close ($ch); /// close the curl session
 
    } 
    /** 
    * __get_xml_array($values, &$i) 
    * 
    * This is adds the contents of the return xml into the array for easier processing. 
    * 
    * @access    private 
    * @param    array    $values this is the xml data in an array 
    * @param    int    $i    this is the current location in the array 
    * @return    Array 
    */ 
    function __get_xml_array($values, &$i) 
    { 
      $child = array(); 

      if(!isset($values[$i]['value'])) {
        $values[$i]['value'] = null;
      }

      if ($values[$i]['value']) array_push($child, $values[$i]['value']); 
     
      while (++$i < count($values)) 
      { 
        switch ($values[$i]['type']) 
        { 
          case 'cdata': 
            array_push($child, $values[$i]['value']); 
          break; 
     
          case 'complete': 
            $name = $values[$i]['tag']; 


            if(isset($values[$i]['value'])) {
              $child[$name]= $values[$i]['value'];               
            } else {
              $child[$name]= null;
            }

            if(!isset($values[$i]['attributes'])) {
              $values[$i]['attributes'] = null;
            }

            if($values[$i]['attributes']) 
            { 
              $child[$name] = $values[$i]['attributes']; 
            } 
          break; 
     
          case 'open': 
            $name = $values[$i]['tag']; 

            if(isset($child[$name])) {
              $size = sizeof($child[$name]);               
            } else {
              $size = 0;
            }

            if(!isset($values[$i]['attributes'])) {
              $values[$i]['attributes'] = null;
            }

            if($values[$i]['attributes']) 
            { 
                 $child[$name][$size] = $values[$i]['attributes']; 
                  $child[$name][$size] = $this->__get_xml_array($values, $i); 
            } 
            else 
            { 
                  $child[$name][$size] = $this->__get_xml_array($values, $i); 
            } 
          break; 
     
          case 'close': 
            return $child; 
          break; 
        } 
      } 
      return $child; 
    } 
     
    /** 
    * _get_xml_array($data) 
    * 
    * This is adds the contents of the return xml into the array for easier processing. 
    * 
    * @access    private 
    * @param    string    $data this is the string of the xml data 
    * @return    Array 
    */ 
    function _get_xml_array($data) 
    { 
      $values = array(); 
      $index = array(); 
      $array = array(); 
      $parser = xml_parser_create(); 
      xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1); 
      xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0); 
      xml_parse_into_struct($parser, $data, $values, $index); 
      xml_parser_free($parser); 
     
      $i = 0; 
     
      $name = $values[$i]['tag']; 

      if(isset($values[$i]['attributes'])) {
        $array[$name] = $values[$i]['attributes']; 
      } else {
        $array[$name] = null;
      }
      $array[$name] = $this->__get_xml_array($values, $i); 
     
      return $array; 
    } 
     
    /** 
    * get_general_package_info() 
    * 
    * This function parses the big array and returns the general info about the shiped package. 
    * 
    * @access    public 
    * @return    Array 
    */ 
    function get_rates() 
    { 
     // $retArray = array('service'=>'','basic'=>0,'option'=>0,'total'=>0,'days'=>'','time'=>'');
          
        $retArray=array();
        
        $values = $this->xmlarray['RatingServiceSelectionResponse']['RatedShipment'];
      	$ct = count($values);
        for($i=0;$i<$ct;$i++){
	           $current=&$values[$i];
             $retArray[$i]['service'] = $current['Service'][0]['Code']; 
            $retArray[$i]['basic'] = $current['TransportationCharges'][0]['MonetaryValue'];
        	  $retArray[$i]['option'] = $current['ServiceOptionsCharges'][0]['MonetaryValue'];
        	  $retArray[$i]['total'] = $current['TotalCharges'][0]['MonetaryValue'];
        	  $retArray[$i]['days'] =  $current['GuaranteedDaysToDelivery'];
        	  $retArray[$i]['time'] = $current['ScheduledDeliveryTime'];
        }

        unset($values); 
         
        return $retArray; 
    } 
}
