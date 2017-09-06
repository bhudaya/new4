<?php

namespace Iapps\PaymentService\Common\TMoneySwitch;

use Iapps\Common\Core\IappsDateTime;
use Iapps\Common\Helper\HttpService;
use Iapps\Common\Helper\StringMasker;
use Iapps\PaymentService\PaymentRequest\PaymentRequestClientInterface;
use Iapps\PaymentService\Common\Logger;


class TMoneySwitchClient implements PaymentRequestClientInterface{
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
    protected $sender_phone;
    protected $receiver_fullname;
    protected $receiver_firstname;
    protected $receiver_lastname;
    protected $receiver_middlename;
    protected $receiver_address;
    protected $receiver_address1;
    protected $receiver_address2;
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
    protected $_inquiryTrxType      = '1';
    protected $_transferTrxType     = '2';
    protected $_inquiryDesc         = 'Inquiry';
    protected $_transferDesc        = 'Bank Transfer';
    protected $amountCheck = 10;

    protected $_inquiry_response ;
    protected $_signin_response ;
    protected $log ;
    protected $switcher_reference_no;
    protected $tmoney_log ;
    protected $tmoney_info;
    protected $info ;




    function __construct(array $config)
    {
        $this->config = $config;
                 

        if( !$this->_getUserName() OR
            !$this->_getPassword() OR
            !$this->_getTerminal() OR
            !$this->_getBearer() OR
            !$this->_getUrl() OR
            !$this->_getId() OR
            !$this->_getFusionId() OR
            !$this->_getApiKey() OR
            !$this->_getApiKeyPrivate() OR
            !$this->_getPin())

            throw new \Exception('invalid switch configuration');

        $this->_http_serv = new HttpService();  
        $this->_http_serv->setUrl($this->_getUrl());
        $this->header = array
        (
            'Authorization'=>'Bearer '. $this->_getBearer(),
            'Content-Type'=>'application/x-www-form-urlencoded'
        );

    }

    public function checkAccount($bank_code , $account_number){

        $this->setBankCode($bank_code);
        $this->setAccountNo($account_number);

        if( $signInResponse = $this->signIn()) {
            if ( !$signInResponse->isSuccess()) {
                return false;
            }
            $this->setToken($signInResponse->getUser()->token);
        }else{return false;}

        $option = array(
            'transactionType'=>$this->_inquiryTrxType ,
            'description'=>$this->_inquiryDesc,
            'terminal'=>$this->_getTerminal(),
            'apiKey'=>$this->_getApiKey(),
            'idTmoney'=>$this->_getId(),
            'idFusion'=>$this->_getFusionId(),
            'pin'=>$this->_getPin(),
            'token'=>$this->getToken(),
            'transactionID'=>$this->getTransactionID(),
            'refNo'=>$this->getReferenceNo(),
            'amount'=>$this->amountCheck ,
            'bankCode'=>$this->getBankCode(),
            'bankAccount'=>$this->getAccountNo(),
            'thirdpartyEmail'=>$this->getReceiverEmail()
        );

        $this->_option = $option;
        set_time_limit($this->_getTimeLimit());
        $this->_http_serv->post($this->header, $option, $this->_inquiryTransferUri);
        return new TMoneySwitchResponse($this->_http_serv->getLastResponse(),"api");

    }

    public function signIn(){

        $datetime       =   date("Y-m-d H:i:s");
        $dataSign       =   $this->_getUserName() .$datetime . $this->_getTerminal() . $this->_getApiKey() ;
        $signature      =   hash_hmac('sha256', $dataSign, $this->_getApiKeyPrivate(), false);

        $this->setTransactionType("0");
        $option = array(
            'userName'=>$this->_getUserName() ,
            'password'=>$this->_getPassword(),
            'apiKey'=>$this->_getApiKey(),
            'terminal'=>$this->_getTerminal(),
            'datetime'=>$datetime,
            'signature'=>$signature
        );
        $this->_option = $option;
        $this->_addInfo("signin_param",$this->getSelectedArray($option , array("datetime","signature")));

        set_time_limit($this->_getTimeLimit());
        $this->_http_serv->post($this->header, $option, $this->_signInUri);     
        return new TMoneySwitchResponse($this->_http_serv->getLastResponse(),"api");
    }
    

    public function inquiry()
    {

       if( $signInResponse = $this->signIn()) {
           $this->_signin_response = json_decode($signInResponse->getFormattedResponse(),true) ;
           $this->_addInfo("signin_response",$this->getSelectedArray($this->_signin_response , array("resultCode","resultDesc")));
           if ( !$signInResponse->isSuccess()) {
               return $signInResponse;
           }
           $this->_removeInfo("signin_param");
           $this->setToken($signInResponse->getUser()->token);
       }else{return false;}

        $this->setTransactionType($this->_inquiryTrxType);
        $option = array(
            'transactionType'=>$this->_inquiryTrxType ,
            'description'=>$this->_inquiryDesc,
            'terminal'=>$this->_getTerminal(),
            'apiKey'=>$this->_getApiKey(),
            'idTmoney'=>$this->_getId(),
            'idFusion'=>$this->_getFusionId(),
            'pin'=>$this->_getPin(),
            'token'=>$this->getToken(),
            'transactionID'=>$this->getTransactionID(),
            'refNo'=>$this->getReferenceNo(),
            'amount'=>$this->getLandedAmount() ,
            'bankCode'=>$this->getBankCode(),
            'bankAccount'=>$this->getAccountNo(),
            'thirdpartyEmail'=>$this->getReceiverEmail()    
        );
        $this->_option = $option;
        $this->_addInfo("inquiry_param",$this->getSelectedArray($option , array("transactionID","refNo")));

        set_time_limit($this->_getTimeLimit());
        $this->_http_serv->post($this->header, $option, $this->_inquiryTransferUri);
        return new TMoneySwitchResponse($this->_http_serv->getLastResponse(),"api");
    }


    public function bankTransfer()
    {
        if( $inquiryResponse = $this->inquiry()) {
            $this->_inquiry_response = json_decode($inquiryResponse->getFormattedResponse(),true) ;
            //refNo once sent transfer
            $this->setSwitcherReferenceNo($inquiryResponse->getRefNoSwitcher());
            $this->_addInfo("inquiry_response",$this->getSelectedArray($this->_inquiry_response , array("transactionID","refNo","resultCode","resultDesc","timeStamp")));
            if (!$inquiryResponse->isSuccess()) {
                return $inquiryResponse;
            }
            $this->_removeInfo("signin_response");
        }else{return false;}


        $this->setTransactionType($this->_transferTrxType);
        $option = array(
            'transactionType'=>$this->_transferTrxType ,
            'terminal'=>$this->_getTerminal(),
            'apiKey'=>$this->_getApiKey(),
            'idTmoney'=>$this->_getId(),
            'idFusion'=>$this->_getFusionId(),
            'token'=>$this->getToken(),
            'bankCode'=>$this->getBankCode(),
            'bankAccount'=>$this->getAccountNo(),    // get from param option
            'amount'=>$this->getLandedAmount(),
            'description'=>$this->_transferDesc,
            'thirdpartyEmail'=>$this->getReceiverEmail(),    
            'pin'=>$this->_getPin(),
            'transactionID'=>$inquiryResponse->getTransactionIDSwitcher(),
            'refNo'=>$inquiryResponse->getRefNoSwitcher()
        );

        $this->_option = $option;
        $this->_addInfo("transfer_param",$this->getSelectedArray($option , array("transactionType","transactionID","refNo")));

        //curl
        set_time_limit($this->_getTimeLimit());
        $this->_http_serv->post($this->header, $option, $this->_inquiryTransferUri);
        return new TMoneySwitchResponse($this->_http_serv->getLastResponse(),"transfer");

    }

    //set post option to Client Object , call from TMoneySwitchClientFactory
    public static function fromOption(array $config, array $option)
    {
        $c = new TMoneySwitchClient($config);
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
        if( isset($option['receiver_fullname']) )
            $c->setReceiverFullname($option['receiver_fullname']);
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

        if( isset($option['bank_code']) ) {
            $c->setBankCode($option['bank_code']);
            $branch_name = $option['bank_code'] == '014' ? 'BCA' : 'BCA';
            $c->setBranchName($branch_name);
        }
        if( isset($option['account_no']) )
            $c->setAccountNo($option['account_no']);
        if( isset($option['landed_currency']) )
            $c->setLandedCurrency($option['landed_currency']);
        if( isset($option['landed_amount']) )
            $c->setLandedAmount($option['landed_amount']);

        return $c;
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
        else if( getenv('TMONEY_SWITCH_URL') )
            return getenv('TMONEY_SWITCH_URL');

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

    protected function _addLog($key,$value)
    {
        $this->log[$key] = $value;
        return $this ;
    }

    protected function _addInfo($key,$value)
    {
        $this->info[$key] = $value;
        $this->setTmoneyInfo(json_encode($this->getInfo()));
        return $this ;
    }

    protected function _removeInfo($key)
    {
        unset($this->info[$key]);
        $this->setTmoneyInfo(json_encode($this->getInfo()));
        return $this ;
    }


    //------------------------------------- getter/setter
    public function getTmoneyLog()
    {
        return $this->tmoney_log;
    }
    public function setTmoneyLog($tmoney_log)
    {
        $this->tmoney_log = $tmoney_log;
    }
    
    public function getLog()
    {
        return $this->log;
    }
    public function setLog($log)
    {
        $this->log = $log;
    }

    public function getInfo()
    {
        return $this->info;
    }
    public function setInfo($info)
    {
        $this->info = $info;
    }

    public function getTmoneyInfo()
    {
        return $this->tmoney_info;
    }
    public function setTmoneyInfo($tmoney_info)
    {
        $this->tmoney_info = $tmoney_info;
    }

    public function getSwitcherReferenceNo()
    {
        return $this->switcher_reference_no;
    }
    public function setSwitcherReferenceNo($switcher_reference_no)
    {
        $this->switcher_reference_no = $switcher_reference_no;
    }

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
        $this->receiver_address = trim($receiver_address);
        $arrAddress = $this->formatAddress($this->receiver_address);

        $this->receiver_address1 = isset($arrAddress[0]) ? $arrAddress[0] : '';
        $this->receiver_address2 = isset($arrAddress[1]) ? $arrAddress[1] : '';
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






    private function formatAddress($address, $length = 75){
        $arrAddress = array($address, '');
        if(strlen($address)>$length){
            $arrAddress = explode( "\n", wordwrap($address, 75));
        }
        return $arrAddress;
    }

    private function formatName($fullname){
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


    //get option data save  to db
    public function getOption()
    {
        $option = array('username' => $this->_getUserName(),
            'signed_data' => $this->getSignedData(),
            'inquire_signed_data' => $this->getInquireSignedData(),
            'trans_date' => $this->getTransDate(),
            'reference_no' => $this->getReferenceNo(),
            'sender_fullname' => $this->getSenderFullname(),
            'sender_address' => $this->getSenderAddress(),
            'sender_phone' => $this->getSenderPhone(),
            'receiver_fullname' => $this->getReceiverFullname(),
            'receiver_address' => $this->getReceiverAddress(),
            'receiver_mobile_phone' => $this->getReceiverMobilePhone(),
            'receiver_birth_date' => $this->getReceiverBirthDate(),
            'receiver_gender' => $this->getReceiverGender(),
            'receiver_email' => $this->getReceiverEmail(),
            'bank_code' => $this->getBankCode(),
            'account_no' => $this->getAccountNo(),
            'landed_amount' => $this->getLandedAmount(),
            'landed_currency' => $this->getLandedCurrency()
        );
        return json_encode($option);
    }


    public function delSelectedArray(array $fields , array $selected){
        foreach ($fields  as $i => $value) {
            if(in_array($i ,$selected)){
                unset($fields[$i]);
            }
        }
        return $fields;
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