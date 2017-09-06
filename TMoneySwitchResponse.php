<?php

namespace Iapps\PaymentService\Common\TMoneySwitch;

use Iapps\Common\Helper\ResponseMessage;
use Iapps\PaymentService\PaymentRequest\PaymentRequestResponseInterface;
use Iapps\Common\Helper\StringMasker;
use Iapps\PaymentService\PaymentRequest\PaymentRequestStatus;

class TMoneySwitchResponse implements PaymentRequestResponseInterface{
    protected $raw;
    protected $formatted_response;
    protected $api_request;
    protected $user;

    protected $invoice_no;
    protected $response_status;
    protected $date_delivered;
    protected $remarks;

    protected $date_processed;
    protected $response_code;
    protected $description;
    protected $transactionIDSwitcher ;
    protected $refNoSwitcher ;
    protected $lastBalance ;
    protected $destCode ;
    protected $destName ;
    protected $amount ;
    protected $feeAmount ;
    protected $totalAmount ;

    protected $sender_name;
    protected $sender_address;
    protected $sender_city;
    protected $sender_postcode;
    protected $sender_country;
    protected $sender_telepon;

    protected $dest_acc_holder;
    protected $dest_bankcode;
    protected $dest_bankacc;
    protected $dest_bankname;
    protected $token ;

    function __construct($response, $api_request)
    {
        $this->setAPIRequest($api_request);
        $this->setRaw($response);
    }

    //set API response array to object
    protected function _extractResponse(array $fields)
    {

        $this->setFormattedResponse($fields);
        if(array_key_exists('resultDesc', $fields)) {

            foreach ($fields AS $field => $value) {
                if ($field == 'resultCode') {
                    $this->setResponseCode($value);
                }
                if ($field == 'resultDesc') {
                    $this->setDescription($value);
                }
                if ($field == 'transactionID') {
                    $this->setTransactionIDSwitcher($value);
                }
                if ($field == 'refNo') {
                    $this->setRefNoSwitcher($value);
                }
                if ($field == 'lastBalance') {
                    $this->setLastBalance($value);
                }
                if ($field == 'destCode') {
                    $this->setLastBalance($value);
                }
                if ($field == 'destName') {
                    $this->setDestName($value);
                }
                if ($field == 'amount') {
                    $this->setAmount($value);
                }
                if ($field == 'feeAmount') {
                    $this->setFeeAmount($value);
                }
                if ($field == 'totalAmount') {
                    $this->setTotalAmount($value);
                }
                if ($field == 'timeStamp') {
                    $this->setDateProcessed($value);
                }
                if ($field == 'senderName') {
                    $this->setSenderName($value);
                }
                if ($field == 'senderAddress') {
                    $this->setSenderAddress($value);
                }
                if ($field == 'senderPhone') {
                    $this->setSenderTelepon($value);
                }
                if ($field == 'senderCity') {
                    $this->setSenderCity($value);
                }
                if ($field == 'senderCountry') {
                    $this->setSenderCountry($value);
                }
                if ($field == 'bankCode') {
                    $this->setDestBankcode($value);
                }
                if ($field == 'bankName') {
                    $this->setDestBankname($value);
                }
                if ($field == 'bankAccount') {
                    $this->setDestBankacc($value);
                }
                if ($field == 'bankAccHolder') {
                    $this->setDestAccHolder($value);
                }

                if ($field == 'user') {
                    $this->setUser($value); //object
                }

            }
        } else{

            $this->setFormattedResponse(array('ERR'=>'timeout'));
            //ERROR but make it processing
            $this->setResponseCode('PRC');
            $this->setDescription('Received timeout but pending confirmation from TMoney');
        }
    }

    public function setRaw($raw)
    {
        $this->raw = $raw;
        $this->_extractResponse($raw);
        return $this;
    }

    public function getRaw()
    {
        return $this->raw;
    }

    public function setFormattedResponse($formatted_response)
    {
        $this->formatted_response = json_encode($formatted_response);
        return $this;
    }

    public function getFormattedResponse()
    {
        return $this->formatted_response;
    }

    public function setAPIRequest($api_request)
    {
        $this->api_request = $api_request;
    }

    public function getAPIRequest()
    {
        return $this->api_request;
    }

    public function getResponse()
    {
        return $this->getFormattedResponse();
    }

    public function isSuccess()
    {
        return (in_array($this->getResponseCode(), array('0','00')) || $this->getResponseStatus()=="PAID");
    }

    public function isPending()
    {
        return (in_array($this->getResponseStatus(), array(TMoneySwitchFunction::TMONEY_STATUS_INPROCESS, TMoneySwitchFunction::TMONEY_STATUS_OUTSTANDING, TMoneySwitchFunction::TMONEY_STATUS_FOR_VERIFICATION)));
    }

    public function getStatus()
    {
        $status = PaymentRequestStatus::FAIL;
        
        if($this->isSuccess())
        {
            $status = PaymentRequestStatus::SUCCESS;
        }elseif($this->isPending())
        {
            $status = PaymentRequestStatus::PENDING;
        }

        return $status;
    }

    public function setResponseCode($response_code)
    {
        $this->response_code = $response_code;
        return $this;
    }

    public function getResponseCode()
    {
        return $this->response_code;
    }
    
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setInvoiceNo($invoice_no){
        $this->invoice_no = $invoice_no;
    }

    public function getInvoiceNo(){
        return $this->invoice_no;
    }
    
    public function setResponseStatus($response_status){
        $this->response_status = $response_status;
    }

    public function getResponseStatus(){
        return $this->response_status;
    }
    public function setDateProcessed($date_processed){
        $this->date_processed = $date_processed;
    }
    public function getDateProcessed(){
        return $this->date_processed;
    }
    public function setDateDelivered($date_delivered){
        $this->date_delivered = $date_delivered;
    }
    public function getDateDelivered(){
        //$date_delivered = IappsDateTime::getSystemTimeFromLocalTime($start_time, $timeZone)->getUnix();
        return $this->date_delivered;
    }
    public function setRemarks($remarks){
        $this->remarks = $remarks;
    }
    public function getRemarks(){
        return $this->remarks;
    }
    public function getTransactionIDSwitcher()
    {
        return $this->transactionIDSwitcher;
    }
    public function setTransactionIDSwitcher($transactionIDSwitcher)
    {
        $this->transactionIDSwitcher = $transactionIDSwitcher;
    }
    public function getRefNoSwitcher()
    {
        return $this->refNoSwitcher;
    }
    public function setRefNoSwitcher($refNoSwitcher)
    {
        $this->refNoSwitcher = $refNoSwitcher;
    }
    public function getLastBalance()
    {
        return $this->lastBalance;
    }
    public function setLastBalance($lastBalance)
    {
        $this->lastBalance = $lastBalance;
    }
    public function getDestCode()
    {
        return $this->destCode;
    }
    public function setDestCode($destCode)
    {
        $this->destCode = $destCode;
    }
    public function getDestName()
    {
        return $this->destName;
    }


    public function setDestName($destName)
    {
        $this->destName = $destName;
    }


    public function getAmount()
    {
        return $this->amount;
    }


    public function setAmount($amount)
    {
        $this->amount = $amount;
    }


    public function getFeeAmount()
    {
        return $this->feeAmount;
    }

    public function setFeeAmount($feeAmount)
    {
        $this->feeAmount = $feeAmount;
    }

    public function getTotalAmount()
    {
        return $this->totalAmount;
    }

    public function setTotalAmount($totalAmount)
    {
        $this->totalAmount = $totalAmount;
    }

    public function getSenderName()
    {
        return $this->sender_name;
    }

    public function setSenderName($sender_name)
    {
        $this->sender_name = $sender_name;
    }

    public function getSenderAddress()
    {
        return $this->sender_address;
    }

    public function setSenderAddress($sender_address)
    {
        $this->sender_address = $sender_address;
    }

    public function getSenderCity()
    {
        return $this->sender_city;
    }

    public function setSenderCity($sender_city)
    {
        $this->sender_city = $sender_city;
    }
  
    public function getSenderPostcode()
    {
        return $this->sender_postcode;
    }
    
    public function setSenderPostcode($sender_postcode)
    {
        $this->sender_postcode = $sender_postcode;
    }

    public function getSenderCountry()
    {
        return $this->sender_country;
    }
    
    public function setSenderCountry($sender_country)
    {
        $this->sender_country = $sender_country;
    }
    
    public function getSenderTelepon()
    {
        return $this->sender_telepon;
    }
  
    public function setSenderTelepon($sender_telepon)
    {
        $this->sender_telepon = $sender_telepon;
    }
   
    public function getDestAccHolder()
    {
        return $this->dest_acc_holder;
    }
   
    public function setDestAccHolder($dest_acc_holder)
    {
        $this->dest_acc_holder = $dest_acc_holder;
    }
  
    public function getDestBankcode()
    {
        return $this->dest_bankcode;
    }
  
    public function setDestBankcode($dest_bankcode)
    {
        $this->dest_bankcode = $dest_bankcode;
    }
   
    public function getDestBankacc()
    {
        return $this->dest_bankacc;
    }
    
    public function setDestBankacc($dest_bankacc)
    {
        $this->dest_bankacc = $dest_bankacc;
    }
   
    public function getDestBankname()
    {
        return $this->dest_bankname;
    }
   
    public function setDestBankname($dest_bankname)
    {
        $this->dest_bankname = $dest_bankname;
    }
   
    public function getUser()
    {
        return $this->user;
    }
   
    public function setUser($user)
    {
        $this->user = $user;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function setToken($token)
    {
        $this->token = $token;
    }

    public function getSelectedField(array $fields)
    {
        return ArrayExtractor::extract($this->jsonSerialize(), $fields);
    }
}