<?php

namespace Iapps\PaymentService\Common\TransferToSwitch;

use Iapps\Common\Core\IappsDateTime;
use Iapps\Common\Helper\HttpService;
use Iapps\Common\Helper\StringMasker;
use Iapps\PaymentService\PaymentRequest\PaymentRequestClientInterface;
use Iapps\PaymentService\Common\Logger;


class TransferToSwitchClient implements PaymentRequestClientInterface{
    protected $config = array();
    protected $_http_serv;
    protected $_option;

    protected $signed_data;
    protected $inquire_signed_data;
    protected $reference_no;
    protected $trans_date;
    protected $sender_fullname;
    protected $sender_firstname;
    protected $sender_lastname;
    protected $sender_middlename;
    protected $sender_address;
    protected $sender_address1;
    protected $sender_address2;
    protected $sender_address3;
    protected $sender_address4;

    protected $sender_phone;
    protected $receiver_fullname;
    protected $receiver_firstname;
    protected $receiver_lastname;
    protected $receiver_middlename;
    protected $receiver_address;
    protected $receiver_address1;
    protected $receiver_address2;
    protected $receiver_address3;
    protected $receiver_address4;

    protected $receiver_mobile_phone;
    protected $receiver_gender;
    protected $receiver_birth_date;
    protected $receiver_email;

    protected $transaction_type;
    protected $payable_code;
    protected $bank_code;
    protected $branch_name;
    protected $account_no;
    protected $landed_currency;
    protected $landed_amount;
    protected $message_to_bene1;
    protected $message_to_bene2;

    protected $token;
    protected $header;
    protected $transactionID;

    protected $_inquiryTransferUri  = 'transfer-p2b';
    protected $_signInUri           = 'sign-in';
    protected $_quotTrxType         = '0';
    protected $_inquiryTrxType      = '1';
    protected $_transferTrxType     = '2';
    protected $_inquiryDesc         = 'Inquiry';
    protected $_transferDesc        = 'Bank Transfer';
    protected $amountCheck = 10;
    protected $_quotationsUri  = 'quotations';
    protected $quotID ;

    protected $sender_dob;
    protected $sender_gender;
    protected $sender_nationality;
    protected $sender_host_countrycode;
    protected $sender_host_identity;
    protected $sender_host_identitycard;

    protected $country_currency_code ;
    protected $receiver_country_iso_code;
    protected $ifs_code;
    protected $payment_mode;

    protected $_quot_response;
    protected $_quot_hit_count;
    protected $_number_of_check_trx =0 ;
    protected $info ;
    protected $_transfer_hit_count = 0 ;
    protected $_inquiry_hit_count = 0 ;
    protected $_last_process;
    protected $_check_trx_info = array() ;
    protected $_last_rc ;  //the last response code
    protected $_transfer_response ;
    protected $response_fields ;
    protected $transferto_info;
    protected $_inquiry_response ;
    protected $switcher_transaction_no;
    protected $_timeout_of_check_trx = 0;
    protected $_checkTrxType     = '3';
    protected $_internal_reff_id  ;


    function __construct(array $config)
    {
        $this->config = $config;

        if( !$this->_getUserName() OR
            !$this->_getPassword() OR
            !$this->_getBearer())

            throw new \Exception('invalid switch configuration');

        $this->_http_serv = new TransferToHttpService();

        $this->_http_serv->setUrl($this->_getUrl());
        $this->_http_serv->setUsername($this->_getUserName());
        $this->_http_serv->setPassword($this->_getPassword());
        $this->_http_serv->setAuth($this->_getBearer());

        $this->header = array(
            'Authorization: Basic '.$this->_getBearer(),
            'Content-Type: application/json'
        );
    }


    function checkTrx($id){

        $this->_number_of_check_trx++;
        $this->setTransactionType($this->_checkTrxType);

        $option = array();
        $this->_option = $option;
        //curl
        set_time_limit($this->_getTimeLimit());
        $uri = "transactions/". $id;

        $response = $this->_http_serv->get($this->header, $option, $uri);

        $this->_addInfo("last_process","check_transaction");
        $this->_addInfo("number_of_check_trx",$this->_number_of_check_trx);
        $this->_addInfo("timeout_of_check_trx",$this->_timeout_of_check_trx);


        if($response) {
            $response_arr = json_decode($response, true);
            //$response_arr= array("status"=>"60000" ,"status_message" => "AVAILABLE" ,"payer_transaction_reference"=>"1507712155");

            if (array_key_exists("status",$response_arr)){

                $selectedResponse = $this->getSelectedArray($response_arr,array('beneficiary','id','status','status_message' ,'payer_transaction_reference'));

                if ($response_arr["status_message"] == "COMPLETED") {
                    $response = array('resultCode' => "0", 'resultDesc' => $response_arr["status_message"] ,'response'=>$selectedResponse  );
                    return $response;
                }
                $response = array('resultCode' => "1", 'status' => $response_arr["status"],'resultDesc' => $response_arr["status_message"] ,'response'=>$selectedResponse );
            }
            //transaction id not found
            if (array_key_exists("errors",$response_arr)){
                $response = array('resultCode' => "1", 'status' =>  $response_arr["errors"][0]["code"] ,'resultDesc' => $response_arr["errors"][0]["message"] );
            }
            return $response ;
        }

        
        $response = array('status' => "PRC", 'status_message' => "Received timeout when check transaction");
        $this->_timeout_of_check_trx=1;
        $this->_addInfo("timeout_of_check_trx",$this->_timeout_of_check_trx);
        return $response ;        
    }


    public function bankTransfer()
    {
        $this->getLastResponse(); //get from  db fields
        $this->setLastResponse(); //set to class fields

        if ($this->_last_rc == "PRC"  ||  $this->_last_rc == "20000"  ||  $this->_last_rc == "50000" ||  $this->_last_rc == "60000") {     //50000=submitted

            $info = $this->_check_trx_info;
            $startDate = date("Y-m-d");
            $stopDate = date("Y-m-d");
            $trxID = "";
            if (array_key_exists('startDate', $info))
                $startDate = $info["startDate"];
            if (array_key_exists('stopDate', $info))
                $stopDate = $info["stopDate"];
            if (array_key_exists('trxID', $info))
                $trxID = $info["trxID"];



            //check transaction timeout || after transfer timeout
            if ( ($this->_last_process == "check_transaction"  && $this->_number_of_check_trx <=2  &&  $this->_timeout_of_check_trx ==1)
                   ||   ($this->_last_process == "transfer" && $this->_number_of_check_trx == 0)   ||  ($this->_last_rc == "20000"  ||  $this->_last_rc == "50000"  ||  $this->_last_rc == "60000" ) ) {

                $rslt = $this->checkTrx($trxID);

                if($this->_number_of_check_trx == 3  &&  ( $this->_last_rc != "20000"   && $this->_last_rc != "50000" && $this->_last_rc != "60000") ){
                    $response = array('status' => "", 'status_message' => "Received timeout when check transaction");
                    $response = json_encode($response);
                    return new TransferToSwitchResponse($response, "checkTrx");
                }
                if ($rslt["resultCode"] == "0") {
                    $response = array('status' => "0", 'status_message' => $rslt["resultDesc"] ,'id'=>$trxID ,'response'=>$rslt["response"]);
                } else {
                    if ($rslt["resultCode"] == "1") {

                        if($this->_last_rc == "20000" || $this->_last_rc == "50000" || $this->_last_rc == "60000"){    //esp for confirmed submited , available
                            $response = array('status' => $rslt["status"], 'status_message' => $rslt["resultDesc"], 'response' => $rslt["response"]);

                        }else {
                            $response = array('status' => "PRC", 'status_message' => $rslt["resultDesc"], 'response' => $rslt["response"]);
                            if ($rslt["status"] == "90200") {  //decline beneficiary
                                $response = array('status' => $rslt["status"], 'status_message' => $rslt["resultDesc"], 'response' => $rslt["response"]);
                            }
                        }

                    } else {
                        $response = array('status' => "PRC", 'status_message' => "Received timeout when check transaction");
                    }
                }

                $response = json_encode($response);
                return new TransferToSwitchResponse($response, "checkTrx");
            }
        }

        if ($quotResponse = $this->quotation()) {
            $this->_quot_response = json_decode($quotResponse->getFormattedResponse(), true);

            if(array_key_exists("creation_date",$this->_quot_response))
             $this->_addInfo("quotation_response", $this->getSelectedArray($this->_quot_response, array("creation_date", "external_id","id","source","wholesale_fx_rate","fee")));

            if (!$quotResponse->isSuccess()) {
                return $quotResponse;
            }
            $this->setQuotID($quotResponse->getQuotID());
        } else {
            return false;
        }

        if( $inquiryResponse = $this->inquiry()) {

            $this->_inquiry_response = json_decode($inquiryResponse->getFormattedResponse(),true) ;
            $this->setSwitcherTransactionNo($inquiryResponse->getTransactionIDSwitcher());

            if(array_key_exists("creation_date",$this->_quot_response))
                $this->_addInfo("inquiry_response",$this->getSelectedArray($this->_inquiry_response , array("status","status_message","creation_date","external_id","id","status","status_message")));


            if (!$inquiryResponse->isSuccess()) {
                
               // max 2 times in inquiry timeout
                if($inquiryResponse->getResponseCode() == "PRC"  &&  $this->_inquiry_hit_count == 2 ){
                    $response = array(
                        'status'=>"",
                        'status_message'=>"Received timeout when inquiry process"
                    );
                    $response = json_encode($response);
                    return new TransferToSwitchResponse($response,"inquiry");
                }                
                return $inquiryResponse;
            }
        }else{return false;}

        if( $trfResponse = $this->transfer()) {

            $this->_transfer_response = json_decode($trfResponse->getFormattedResponse(),true) ;
            if(array_key_exists("creation_date",$this->_quot_response))
                $this->_addInfo("transfer_response",$this->getSelectedArray($this->_transfer_response , array("status","status_message","creation_date","id","status","status_message")));
            if (!$trfResponse->isSuccess()) {

                //if the first process is timeout , set to timeout and will call check trx at the second hit
                if($trfResponse->getResponseCode()== "PRC"   || $trfResponse->getResponseCode()== "20000"){
                    if($this->_transfer_hit_count == 1){
                        $info = array(
                            'startDate'=>date("Y-m-d"),
                            'stopDate'=>date("Y-m-d"),
                            'trxID'=>$trfResponse->getTransactionIDSwitcher()
                        );
                        $this->_addInfo("check_trx_info",$info);
                    }else{
                        $response = array('status'=>"",'status_message'=>"Received timeout when transfer process");
                        $response = json_encode($response);
                        return new TransferToSwitchResponse($response, "transfer");
                    }
                }


            }

            return $trfResponse;
        }else{return false;}

    }


    public function checkAccount($bank_code , $account_number){

        $this->setBankCode($bank_code);
        $this->setAccountNo($account_number);
        /*
        https://api-pre.mm.transferto.com/v1/money-transfer/payers/214/
        credit-party-information?bank_account_number=212938100
        */
        $option = array();
        $this->_option = $option;
        $uri = "payers/". $this->getBankCode() ."/credit-party-information?bank_account_number=".$this->getAccountNo();
        $response = $this->_http_serv->get($this->header, $option, $uri);
        return new TransferToSwitchResponse($response, 'checkaccount');
    }

    public function quotation(){
        $this->_quot_hit_count++;
        $this->setTransactionType($this->_quotTrxType);

        //from internal . transactionID + counter
        $this->_internal_reff_id = $this->getReferenceNo() .  $this->_quot_hit_count ;

        $datetime       =   date("Y-m-d H:i:s");
        $source = array('amount'=>"1",'currency'=>"SGD",'country_iso_code'=>"SGP");
        $dest = array('amount'=>$this->getLandedAmount(),'currency'=>$this->getLandedCurrency());

        if($this->getPaymentMode() == TransferToSwitchFunction::PHILIPPINES_CP){
            $this->setBankCode(TransferToSwitchFunction::PHILIPPINES_CP_BANK_CODE);  //cash pickup philippines
        }
        if($this->getPaymentMode() == TransferToSwitchFunction::VIETNAM_CP){
            $this->setBankCode(TransferToSwitchFunction::VIETNAM_CP_BANK_CODE);  //cash pickup vietnam
        }

        if($this->getPaymentMode() == TransferToSwitchFunction::BANK_TRANSFER_TT1){
            $this->setReceiverMobilePhone("");
        }

        $option = array(
            'external_id'=>$this->_internal_reff_id ,
            'payer_id'=>$this->getBankCode(),
            'mode'=>"DESTINATION_AMOUNT",
            'source'=>$source,
            'destination'=>$dest,
            'retail_fee'=>"1",
            'retail_fee_currency'=>"IDR"
        );

        $this->_addInfo("quotation_param",$this->getSelectedArray($option , array("external_id","payer_id")));
        $this->_addInfo("number_of_quotation_calls",$this->_quot_hit_count);
        $this->_addInfo("last_process","quotation");

        $this->_http_serv->setUrl($this->_getUrl());
        set_time_limit($this->_getTimeLimit());
        $response = $this->_http_serv->post($this->header, $option, $this->_quotationsUri);
        return new TransferToSwitchResponse($response, 'quotation');
    }

    public function inquiry()
    {

        $this->_inquiry_hit_count++;
        $this->setTransactionType($this->_inquiryTrxType);

        $creditParty = array(
            'msisdn'=>$this->getReceiverMobilePhone(),
            'bank_account_number'=>$this->getAccountNo(),
            'ifs_code'=>$this->getIfsCode()
        );

        $sender = array(
            'lastname'=>$this->getSenderLastname() ,
            'firstname'=>$this->getSenderFirstname(),
            'nationality_country_iso_code'=>$this->getSenderNationality(),
            'date_of_birth'=>$this->getSenderDob(),
            'country_of_birth_iso_code'=>"",
            'gender'=>$this->getSenderGender(),
            'address'=>$this->getSenderAddress(),
            'postal_code'=>"",
            'city'=>$this->getSenderAddress3(),
            'country_iso_code'=>$this->getSenderHostCountrycode(),
            'msisdn'=>$this->getSenderPhone() ,
            'email'=>"",
            //'id_type'=>$this->getSenderHostIdentity(),
            'id_type'=>"",
            'id_number'=>$this->getSenderHostIdentity(),
            'id_delivery_date'=>$this->getTransDate()
        );

        $beneficiary = array(
            'lastname'=>$this->getReceiverLastName() ,
            'firstname'=>$this->getReceiverFirstName(),
            'nationality_country_iso_code'=>"",
            'date_of_birth'=>"",
            'country_of_birth_iso_code'=>"",
            'gender'=>$this->getReceiverGender(),
            'address'=>$this->getReceiverAddress(),
            'city'=>$this->getReceiverAddress3(),
            'country_iso_code'=>$this->getReceiverCountryIsoCode(),
            'msisdn'=>$this->getReceiverMobilePhone()
        );

        $option = array(
            'credit_party_identifier'=>$creditParty ,
            'sender'=>$sender,
            'beneficiary'=>$beneficiary,
            'external_id'=>$this->_internal_reff_id,
            'purpose_of_remittance'=>"FAMILY_SUPPORT",
            'callback_url'=>"https://mm-callback.transferto.com/"
        );

        $this->_addInfo("inquiry_param",$this->getSelectedArray($option , array("external_id","sender","beneficiary")));
        $this->_addInfo("number_of_inquiry_calls",$this->_inquiry_hit_count);
        $this->_addInfo("last_process","inquiry");

        $this->_option = $option;
        set_time_limit($this->_getTimeLimit());
        $uri = "quotations/". $this->getQuotID() ."/transactions";
        $response = $this->_http_serv->post($this->header, $option, $uri);
        return new TransferToSwitchResponse($response, 'inquiry');
    }


    public function transfer()
    {
        $this->_transfer_hit_count++;
        $this->setTransactionType($this->_transferTrxType);

        $this->_addInfo("transfer_param",$this->getSwitcherTransactionNo());
        $this->_addInfo("number_of_transfer_calls",$this->_transfer_hit_count);
        $this->_addInfo("last_process","transfer");

        $option = array();
        $this->_option = $option;
        //curl
        set_time_limit($this->_getTimeLimit());
        $uri = "transactions/". $this->getSwitcherTransactionNo() ."/confirm";
        $response = $this->_http_serv->post($this->header, $option, $uri);
        return new TransferToSwitchResponse($response, 'transfer');

    }

    /*
     set post option to Client Object
     call from TransferToSwitchClientFactory
     call when option > 0 in TransferToSwitchClientFactory
     when complete data option from db (field option payment_request)
    */
    public static function fromOption(array $config, array $option)
    {
        $c = new TransferToSwitchClient($config);

        $c->setSenderNationality("SGP");
        $c->setSenderDob("1900-01-01");
        $c->setSenderLastname("lastname");
        $c->setReceiverLastName("lastname");


        if( isset($option['signed_data']) )
            if( isset($option['signed_data']) )
                $c->setSignedData($option['signed_data']);
        if( isset($option['inquire_signed_data']) )
            $c->setInquireSignedData($option['inquire_signed_data']);
        if( isset($option['reference_no']) )
            $c->setReferenceNo($option['reference_no']);
        if( isset($option['trans_date']) )
            $c->setTransDate($option['trans_date']);
        if( isset($option['sender_fullname']) )
            $c->setSenderFullname($option['sender_fullname']);
        if( isset($option['sender_address']) )
            $c->setSenderAddress($option['sender_address']);
        if( isset($option['sender_phone']) )
            $c->setSenderPhone($option['sender_phone']);
        if( isset($option['sender_dob']) )
            $c->setSenderDob($option['sender_dob']);
        if( isset($option['sender_gender']) )
            $c->setSenderGender($option['sender_gender']);

        if( isset($option['sender_nationality']) )
            $c->setSenderNationality($option['sender_nationality']);
        if( isset($option['sender_host_countrycode']) )
            $c->setSenderHostCountrycode($option['sender_host_countrycode']);

        if( isset($option['sender_host_identity']) )
            $c->setSenderHostIdentity($option['sender_host_identity']);
        if( isset($option['sender_host_identitycard']) )
            $c->setSenderHostIdentitycard($option['sender_host_identitycard']);

        if( isset($option['receiver_full_name']) )//esp for CP2
            $c->setReceiverFullname($option['receiver_full_name']);
        if( isset($option['receiver_fullname']) )
            $c->setReceiverFullname($option['receiver_fullname']);
        if( isset($option['account_holder_name']) )
            $c->setReceiverFullname($option['account_holder_name']);
                
        if( isset($option['receiver_address']) )
            $c->setReceiverAddress($option['receiver_address']);
        if( isset($option['receiver_mobile_phone']) )
            $c->setReceiverMobilePhone($option['receiver_mobile_phone']);
        if( isset($option['receiver_gender']) )
            $c->setReceiverGender($option['receiver_gender']);
        if( isset($option['receiver_birth_date']) )
            $c->setReceiverBirthDate($option['receiver_birth_date']);
        if( isset($option['receiver_email']) )
            $c->setReceiverEmail($option['receiver_email']);
        if( isset($option['bank_code']) )
            $c->setBankCode($option['bank_code']);
        if( isset($option['account_no']) )
            $c->setAccountNo($option['account_no']);
        if( isset($option['landed_currency']) )
            $c->setLandedCurrency($option['landed_currency']);
        if( isset($option['landed_amount']) )
            $c->setLandedAmount($option['landed_amount']);

        if( isset($option['receiver_country_iso_code']) ) {
            $receiver_country_iso_code = $option['receiver_country_iso_code'];
            if($receiver_country_iso_code == "IN"   ||  $receiver_country_iso_code == "IND" ){ //india
                $c->setIfsCode($option['bank_code']);
                $c->setBankCode("406");
            }
            $c->setReceiverCountryIsoCode($receiver_country_iso_code);
        }

        return $c;
    }



    //------------  getter and setter

    public function getSwitcherTransactionNo()
    {
        return $this->switcher_transaction_no;
    }
    public function setSwitcherTransactionNo($switcher_transaction_no)
    {
        $this->switcher_transaction_no = $switcher_transaction_no;
    }

    public function getPaymentMode()
    {
        return $this->payment_mode;
    }
    public function setPaymentMode($payment_mode)
    {
        $this->payment_mode = $payment_mode;
    }

    public function getQuotID()
    {
        return $this->quotID;
    }
    public function setQuotID($quotID)
    {
        $this->quotID = $quotID;
    }

    protected function _getUserName()
    {
        if( array_key_exists('username', $this->config) )
            return $this->config['username'];

        return false;
    }

    protected function _getPassword()
    {
        if( array_key_exists('password', $this->config) )
            return $this->config['password'];

        return false;
    }

    protected function _getTerminal()
    {
        if( array_key_exists('terminal', $this->config) )
            return $this->config['terminal'];

        return false;
    }

    protected function _getBearer()
    {
        if( array_key_exists('bearer', $this->config) )
            return $this->config['bearer'];

        return false;
    }

    /*
     * This will first try from config['url']
     * Then environment
     * Otherwise return false
     */
    protected function _getUrl()
    {
        if( array_key_exists('url', $this->config) )
            return $this->config['url'];
        else if( getenv('TRANSFERTO_SWITCH_URL') )
            return getenv('TRANSFERTO_SWITCH_URL');

        return false;
    }

    protected function _getPin()
    {
        if( array_key_exists('pin', $this->config) )
            return $this->config['pin'];

        return false;
    }

    protected function _getApiKey()
    {
        if( array_key_exists('api_key', $this->config) )
            return $this->config['api_key'];

        return false;
    }

    protected function _getApiKeyPrivate()
    {
        if( array_key_exists('api_key_private', $this->config) )
            return $this->config['api_key_private'];

        return false;
    }

    protected function _getId()
    {
        if( array_key_exists('id', $this->config) )
            return $this->config['id'];

        return false;
    }

    protected function _getFusionId()
    {
        if( array_key_exists('fusion_id', $this->config) )
            return $this->config['fusion_id'];

        return false;
    }

    protected function _getTimeLimit()
    {
        if( getenv('SWITCH_TIME_LIMIT') ) {
            return getenv('SWITCH_TIME_LIMIT');
        }
        return 90;
    }

    protected function _getCountryIsoCode3Char($code){

        switch ($code) {
            case "ID":
                $result="IDN";
                break;
            case "IN":
                $result="IND";
                break;
            case "SG":
                $result="SGP";
                break;
            case "PH":
                $result="PHL";
                break;
            default:
                $result=$code;
        }
        return $result;
    }



    //------------------------------------ getter/setter

    public function getReceiverEmail()
    {
        return $this->receiver_email;
    }
    public function setReceiverEmail($receiver_email)
    {
        $this->receiver_email = $receiver_email;
    }

    public function setSignedData($signed_data)
    {
        $this->signed_data = $signed_data;
        return $this;
    }
    public function getSignedData()
    {
        return $this->signed_data;
    }

    public function setInquireSignedData($inquire_signed_data)
    {
        $this->inquire_signed_data = $inquire_signed_data;
        return $this;
    }
    public function getInquireSignedData()
    {
        return $this->inquire_signed_data;
    }

    public function setTransactionID($transactionID)
    {
        $this->transactionID = $transactionID;
        return $this;
    }
    public function getTransactionID()
    {
        return $this->transactionID;
    }

    public function setReferenceNo($reference_no)
    {
        $this->reference_no = $reference_no;
        return $this;
    }
    public function getReferenceNo()
    {
        return $this->reference_no;
    }

    public function setToken($token)
    {
        $this->token = $token;
        return $this;
    }
    public function getToken()
    {
        return $this->token;
    }

    public function setTransDate($trans_date)
    {
        $this->trans_date = $trans_date;
        return $this;
    }

    public function getTransDate()
    {
        return $this->trans_date;
    }

    public function setSenderFullname($sender_fullname)
    {
        $this->sender_fullname = trim($sender_fullname);
        $arrName = $this->formatName($this->sender_fullname);

        $this->sender_firstname = isset($arrName[0]) ? $arrName[0] : '';
        $this->sender_middlename = isset($arrName[1]) ? $arrName[1] : '';
        $this->sender_lastname = isset($arrName[2]) ? $arrName[2] : '';

        if($this->sender_lastname == "") {
            if ($this->sender_firstname)
                $this->sender_lastname = $this->sender_firstname;
            if ($this->sender_middlename)
                $this->sender_lastname = $this->sender_middlename;
        }

        return $this;
    }
    public function getSenderFullname()
    {
        return $this->sender_fullname;
    }

    public function setSenderFirstname($sender_firstname)
    {
        $this->sender_firstname = $sender_firstname;
        return $this;
    }
    public function getSenderFirstname()
    {
        return $this->sender_firstname;
    }

    public function setSenderLastname($sender_lastname)
    {
        $this->sender_lastname = $sender_lastname;
        return $this;
    }
    public function getSenderLastname()
    {
        return $this->sender_lastname;
    }

    public function setSenderMiddlename($sender_middlename)
    {
        $this->sender_middlename = $sender_middlename;
        return $this;
    }
    public function getSenderMiddlename()
    {
        return $this->sender_middlename;
    }

    public function setSenderAddress($sender_address)
    {
        $this->sender_address = trim($sender_address);
        $arrAddress = $this->formatAddress($this->sender_address);
        $this->sender_address1 = isset($arrAddress[0]) ? $arrAddress[0] : '';
        $this->sender_address2 = isset($arrAddress[1]) ? $arrAddress[1] : '';
        $this->sender_address3 = isset($arrAddress[2]) ? $arrAddress[2] : '';
        $this->sender_address4 = isset($arrAddress[3]) ? $arrAddress[3] : '';
        return $this;
    }
    public function getSenderAddress()
    {
        return $this->sender_address;
    }

    public function setSenderAddress1($sender_address1)
    {
        $this->sender_address1 = $sender_address1;
        return $this;
    }
    public function getSenderAddress1()
    {
        return $this->sender_address1;
    }

    public function setSenderAddress2($sender_address2)
    {
        $this->sender_address2 = $sender_address2;
        return $this;
    }
    public function getSenderAddress2()
    {
        return $this->sender_address2;
    }

    public function setSenderAddress3($sender_address3)
    {
        $this->sender_address3 = $sender_address3;
        return $this;
    }
    public function getSenderAddress3()
    {
        return $this->sender_address3;
    }

    public function setSenderAddress4($sender_address4)
    {
        $this->sender_address4 = $sender_address4;
        return $this;
    }
    public function getSenderAddress4()
    {
        return $this->sender_address4;
    }



    public function setSenderPhone($sender_phone)
    {
        $this->sender_phone = ltrim(preg_replace('/\s+/', '', $sender_phone), "+");
        return $this;
    }
    public function getSenderPhone()
    {
        return $this->sender_phone;
    }

    public function setReceiverFullname($receiver_fullname)
    {
        $this->receiver_fullname = trim($receiver_fullname);
        $arrName = $this->formatName($this->receiver_fullname);
        $this->receiver_firstname = isset($arrName[0]) ? $arrName[0] : '';
        $this->receiver_middlename = isset($arrName[1]) ? $arrName[1] : '';
        $this->receiver_lastname = isset($arrName[2]) ? $arrName[2] : '';

        if($this->receiver_lastname =="") {
            if ($this->receiver_firstname)
                $this->receiver_lastname = $this->receiver_firstname;
            if ($this->receiver_middlename)
                $this->receiver_lastname = $this->receiver_middlename;
        }
        return $this;
    }
    public function getReceiverFullname()
    {
        return $this->receiver_fullname;
    }

    public function setReceiverFirstName($receiver_firstname)
    {
        $this->receiver_firstname = $receiver_firstname;
        return $this;
    }
    public function getReceiverFirstName()
    {
        return $this->receiver_firstname;
    }

    public function setReceiverLastName($receiver_lastname)
    {
        $this->receiver_lastname = $receiver_lastname;
        return $this;
    }
    public function getReceiverLastName()
    {
        return $this->receiver_lastname;
    }

    public function setReceiverMiddleName($receiver_middlename)
    {
        $this->receiver_middlename = $receiver_middlename;
        return $this;
    }
    public function getReceiverMiddleName()
    {
        return $this->receiver_middlename;
    }

    public function setReceiverAddress($receiver_address)
    {
        //Gjk, AGUSAN DEL NORTE , CABADBARAN CITY, Philippines
        // address has combine in TransferToPaymentModeOption class remittance service
        $this->receiver_address = trim($receiver_address);
        $arrAddress = $this->formatAddress($this->receiver_address);
        $this->receiver_address1 = isset($arrAddress[0]) ? $arrAddress[0] : '';
        $this->receiver_address2 = isset($arrAddress[1]) ? $arrAddress[1] : '';
        $this->receiver_address3 = isset($arrAddress[2]) ? $arrAddress[2] : '';
        $this->receiver_address4 = isset($arrAddress[3]) ? $arrAddress[3] : '';



        return $this;
    }
    public function getReceiverAddress()
    {
        return $this->receiver_address;
    }

    public function setReceiverAddress1($receiver_address1)
    {
        $this->receiver_address1 = $receiver_address1;
        return $this;
    }
    public function getReceiverAddress1()
    {
        return $this->receiver_address1;
    }

    public function setReceiverAddress2($receiver_address2)
    {
        $this->receiver_address2 = $receiver_address2;
        return $this;
    }
    public function getReceiverAddress2()
    {
        return $this->receiver_address2;
    }

    public function setReceiverAddress3($receiver_address3)
    {
        $this->receiver_address3 = $receiver_address3;
        return $this;
    }
    public function getReceiverAddress3()
    {
        return $this->receiver_address3;
    }

    public function setReceiverAddress4($receiver_address4)
    {
        $this->receiver_address4 = $receiver_address4;
        return $this;
    }
    public function getReceiverAddress4()
    {
        return $this->receiver_address4;
    }



    public function setReceiverMobilePhone($receiver_mobile_phone)
    {
        $this->receiver_mobile_phone = ltrim(preg_replace('/\s+/', '', $receiver_mobile_phone), "+");
        return $this;
    }
    public function getReceiverMobilePhone()
    {
        return $this->receiver_mobile_phone;
    }

    public function setReceiverGender($receiver_gender)
    {
        $this->receiver_gender = $receiver_gender;
        return $this;
    }
    public function getReceiverGender()
    {
        return $this->receiver_gender;
    }

    public function setReceiverBirthDate($receiver_birth_date)
    {
        $this->receiver_birth_date = $receiver_birth_date;
        return $this;
    }
    public function getReceiverBirthDate()
    {
        return $this->receiver_birth_date;
    }

    public function setTransactionType($transaction_type)
    {
        $this->transaction_type = $transaction_type;
        return $this;
    }
    public function getTransactionType()
    {
        return $this->transaction_type;
    }

    public function setPayableCode($payable_code)
    {
        $this->payable_code = $payable_code;
        return $this;
    }
    public function getPayableCode()
    {
        return $this->payable_code;
    }

    public function setBankCode($bank_code)
    {
        $this->bank_code = $bank_code;
        return $this;
    }
    public function getBankCode()
    {
        return $this->bank_code;
    }

    public function setBranchName($branch_name)
    {
        $this->branch_name = $branch_name;
        return $this;
    }
    public function getBranchName()
    {
        return $this->branch_name;
    }

    public function setAccountNo($account_no)
    {
        $this->account_no = trim($account_no);
        return $this;
    }
    public function getAccountNo()
    {
        return $this->account_no;
    }

    public function setLandedCurrency($landed_currency)
    {
        $this->landed_currency = $landed_currency;
        return $this;
    }
    public function getLandedCurrency()
    {
        return $this->landed_currency;
    }

    public function setLandedAmount($landed_amount)
    {
        $this->landed_amount = $landed_amount;
        return $this;
    }
    public function getLandedAmount()
    {
        return $this->landed_amount;
    }

    public function setMessageToBene1($message_to_bene1)
    {
        $this->message_to_bene1 = $message_to_bene1;
        return $this;
    }
    public function getMessageToBene1()
    {
        return $this->message_to_bene1;
    }

    public function setMessageToBene2($message_to_bene2)
    {
        $this->message_to_bene2 = $message_to_bene2;
        return $this;
    }
    public function getMessageToBene2()
    {
        return $this->message_to_bene2;
    }

    public function getSenderDob()
    {
        return $this->sender_dob;
    }
    public function setSenderDob($sender_dob)
    {
        $this->sender_dob = $sender_dob;
    }

    public function getSenderGender()
    {
        return $this->sender_gender;
    }
    public function setSenderGender($sender_gender)
    {
        //only for transfer to
        if($sender_gender == "F"){$sender_gender="Female";}
        if($sender_gender == "M"){$sender_gender="Female";}
        $this->sender_gender = $sender_gender;
    }

    public function getSenderNationality()
    {
        return $this->sender_nationality;
    }
    public function setSenderNationality($sender_nationality)
    {
        $sender_nationality = $this->_getCountryIsoCode3Char($sender_nationality);
        $this->sender_nationality = $sender_nationality;
    }

    public function getSenderHostCountrycode()
    {
        return $this->sender_host_countrycode;
    }
    public function setSenderHostCountrycode($sender_host_countrycode)
    {
        $sender_host_countrycode = $this->_getCountryIsoCode3Char($sender_host_countrycode);
        $this->sender_host_countrycode = $sender_host_countrycode;
    }

    public function getSenderHostIdentity()
    {
        return $this->sender_host_identity;
    }
    public function setSenderHostIdentity($sender_host_identity)
    {
        $this->sender_host_identity = $sender_host_identity;
    }

    public function getSenderHostIdentitycard()
    {
        return $this->sender_host_identitycard;
    }
    public function setSenderHostIdentitycard($sender_host_identitycard)
    {
        $this->sender_host_identitycard = $sender_host_identitycard;
    }

    public function getCountryCurrencyCode()
    {
        return $this->country_currency_code;
    }
    public function setCountryCurrencyCode($country_currency_code)
    {
        $this->country_currency_code = $country_currency_code;
        if( isset($country_currency_code)){
            $exp=explode("-",$country_currency_code);
            $this->setReceiverCountryIsoCode($exp[0]);
        }
    }

    public function getReceiverCountryIsoCode()
    {
        return $this->receiver_country_iso_code;
    }
    public function setReceiverCountryIsoCode($receiver_country_iso_code)
    {
        $receiver_country_iso_code = $this->_getCountryIsoCode3Char($receiver_country_iso_code) ;
        $this->receiver_country_iso_code = $receiver_country_iso_code;
    }

    public function getIfsCode()
    {
        return $this->ifs_code;
    }
    public function setIfsCode($ifs_code)
    {
        $this->ifs_code = $ifs_code;
    }


    //--------------------------------------------------------

    private function formatAddress($address, $length = 75){
        $arrAddress = explode(",",$address);
        return $arrAddress;
    }


    private function formatAddress2($address, $length = 75){
        $arrAddress = array($address, ',');
        if(strlen($address)>$length){
            $arrAddress = explode( "\n", wordwrap($address, 75));
        }
        return $arrAddress;
    }



    private function formatName1($fullname){
        $arrName = array($fullname, '', '.');
        if (preg_match('/\s/', $fullname)) {
            $firstName = mb_substr($fullname, 0, mb_strpos($fullname, " "));
            $lastName = mb_substr($fullname, -abs(mb_strpos(strrev($fullname), " ")));
            $arrName[0] = $firstName;
            $arrName[1] = strtoupper(substr($firstName,0,1));
            $arrName[2] = $lastName;
        }

        return $arrName;
    }


    private function formatName($fullname){
        $arrName = explode(" ",$fullname);
        return $arrName;
    }

    // get option data and save  to db
    // will save to field option payment_request tbl in request process
    // call from TransferToPaymentRequestService in request & complete
    public function getOption()
    {
        $option = array('username' => $this->_getUserName(),
            'signed_data' => $this->getSignedData(),
            'inquire_signed_data' => $this->getInquireSignedData(),
            'trans_date' => $this->getTransDate(),
            'reference_no' => $this->getReferenceNo(),
            'bank_code' => $this->getBankCode(),
            'account_no' => $this->getAccountNo(),
            'bank_account' => $this->getAccountNo(),
            'landed_amount' => $this->getLandedAmount(),
            'landed_currency' => $this->getLandedCurrency(),

            'sender_fullname' => $this->getSenderFullname(),
            'sender_address' => $this->getSenderAddress(),
            'sender_phone' => $this->getSenderPhone(),
            'sender_dob' => $this->getSenderDob(),
            'sender_gender' => $this->getSenderGender(),
            'sender_nationality' => $this->getSenderNationality(),
            'sender_host_countrycode' => $this->getSenderHostCountrycode(),
            'sender_host_identity' => $this->getSenderHostIdentity(),
            'sender_host_identitycard' => $this->getSenderHostIdentitycard(),

            'account_holder_name' => $this->getReceiverFullname(),
            'receiver_fullname' => $this->getReceiverFullname(),
            'receiver_address' => $this->getReceiverAddress(),
            'receiver_mobile_phone' => $this->getReceiverMobilePhone(),
            'receiver_birth_date' => $this->getReceiverBirthDate(),
            'receiver_gender' => $this->getReceiverGender(),
            'receiver_email' => $this->getReceiverEmail(),
            'receiver_country_iso_code'=>$this->getReceiverCountryIsoCode()

        );
        return json_encode($option);
    }


    public function setLastResponse(){
        $this->_addInfo("number_of_inquiry_calls",$this->_inquiry_hit_count);
        $this->_addInfo("number_of_transfer_calls",$this->_transfer_hit_count);
        $this->_addInfo("number_of_check_trx",$this->_number_of_check_trx);
        $this->_addInfo("check_trx_info",$this->_check_trx_info);
        $this->_addInfo("number_of_quotation_calls",$this->_quot_hit_count);

    }

    public function getLastResponse(){
        //get last response from request
        $last_response = $this->getResponseFields() ;
        if(array_key_exists('transferto_process', $last_response)) {
            $transferto_process = json_decode($last_response["transferto_process"], true);
            if(array_key_exists('number_of_inquiry_calls', $transferto_process))
                $this->_inquiry_hit_count        =  $transferto_process["number_of_inquiry_calls"];
            if(array_key_exists('number_of_transfer_calls', $transferto_process))
                $this->_transfer_hit_count       =  $transferto_process["number_of_transfer_calls"];
            if(array_key_exists('last_process', $transferto_process))
                $this->_last_process             =  $transferto_process["last_process"];
            if(array_key_exists('check_trx_info', $transferto_process))
                $this->_check_trx_info           =  $transferto_process["check_trx_info"];
            if(array_key_exists('number_of_check_trx', $transferto_process))
                $this->_number_of_check_trx     =  $transferto_process["number_of_check_trx"];

            if(array_key_exists('number_of_quotation_calls', $transferto_process))
                $this->_quot_hit_count        =  $transferto_process["number_of_quotation_calls"];

            if(array_key_exists('timeout_of_check_trx', $transferto_process))
                $this->_timeout_of_check_trx     =  $transferto_process["timeout_of_check_trx"];
        }
        if(array_key_exists('transferto_response', $last_response)) {
            $transferto_response = json_decode($last_response["transferto_response"], true);
            if(array_key_exists('status', $transferto_response))
                $this->_last_rc  =  $transferto_response["status"];
        }
    }

    protected function _addInfo($key,$value)
    {
        $this->info[$key] = $value;
        $this->setTransfertoInfo(json_encode($this->getInfo()));
        return $this ;
    }



    protected function _removeInfo($key)
    {
        unset($this->info[$key]);
        $this->setTransfertoInfo(json_encode($this->getInfo()));
        return $this ;
    }

    public function getTransfertoInfo()
    {
        return $this->transferto_info;
    }
    public function setTransfertoInfo($transferto_info)
    {
        $this->transferto_info = $transferto_info;
    }

    public function getInfo()
    {
        return $this->info;
    }
    public function setInfo($info)
    {
        $this->info = $info;
    }

    public function getResponseFields()
    {
        return $this->response_fields;
    }
    public function setResponseFields($response_fields)
    {
        $this->response_fields = $response_fields;
    }

    public function getSelectedArray(array $fields , array $selected){
        $result = array() ;
        foreach ($fields  as $i => $value) {
            if(in_array($i ,$selected)){
                $result[$i] =$value;
            }
        }
        return $result;
    }

}