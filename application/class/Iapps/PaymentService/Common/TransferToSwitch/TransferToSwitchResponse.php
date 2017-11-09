<?php

namespace Iapps\PaymentService\Common\TransferToSwitch;

use Iapps\Common\Helper\ResponseMessage;
use Iapps\PaymentService\PaymentRequest\PaymentRequestResponseInterface;
use Iapps\Common\Helper\StringMasker;
use Iapps\PaymentService\PaymentRequest\PaymentRequestStatus;

class TransferToSwitchResponse implements PaymentRequestResponseInterface{
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
    protected $quotID;
    protected $payer_transaction_code ;

    function __construct($response, $api_request)
    {
        $this->setAPIRequest($api_request);
        $response = json_decode($response,true);
        $this->setRaw($response);
    }



    //set API response array to object
    //success transaction
    protected function _extractResponse($fields)
    {

        If(is_array($fields) && count($fields)>0) {

             $this->setFormattedResponse($fields);


            //handle manipulate from client
            if (array_key_exists('status', $fields)) {
                if ($fields["status"] == "PRC") {
                    $this->setResponseCode("PRC");
                    $this->setDescription($fields["status_message"]);
                    if (array_key_exists('id', $fields))
                        $this->setTransactionIDSwitcher($fields["id"]);
                }
                if ($fields["status"] == "0") {
                    $this->setResponseCode("0");
                    $this->setDescription($fields["status_message"]);
                    if (array_key_exists('id', $fields))
                        $this->setTransactionIDSwitcher($fields["id"]);
                }
                if ($fields["status"] == "20000") {   //esp after check trx for confirmed / submited
                    $this->setResponseCode("20000");
                    if (array_key_exists('id', $fields))
                        $this->setTransactionIDSwitcher($fields["id"]);
                }

                if ($fields["status"] == "50000") {   //esp after check trx for  submited
                    $this->setResponseCode("50000");
                    if (array_key_exists('id', $fields))
                        $this->setTransactionIDSwitcher($fields["id"]);

                    if (array_key_exists('payer_transaction_reference', $fields))
                        $this->setPayerTransactionCode($fields["payer_transaction_reference"]);
                }

                if ($fields["status"] == "60000") {   //esp after check trx for  available
                    $this->setResponseCode("60000");
                    if (array_key_exists('id', $fields))
                        $this->setTransactionIDSwitcher($fields["id"]);
                    if (array_key_exists('response', $fields))
                        $this->setPayerTransactionCode($fields["response"]["payer_transaction_reference"]);
                }


            }


             if (array_key_exists('errors', $fields)) {
                 $this->setResponseCode($fields["errors"][0]["code"]);
                 $this->setDescription($fields["errors"][0]["message"]);                 
                 $response = array("status" => $fields["errors"][0]["code"],"status_message"=>$fields["errors"][0]["message"]);
                 $this->setFormattedResponse($response);
             }

             if (array_key_exists('creation_date', $fields)) {
                 $this->setResponseCode("0");
                 $this->setTransactionIDSwitcher($fields["id"]);

                 if (array_key_exists('payer_transaction_reference', $fields))
                   $this->setPayerTransactionCode($fields["payer_transaction_reference"]);


                 if ($this->getAPIRequest() == "quotation") {
                     $this->setQuotID($fields["id"]);
                 }

                 if ($this->getAPIRequest() == "inquiry") {
                     $this->setDescription($fields["status_message"]);
                     if ($fields["status"] == "10000" ) {
                         $this->setResponseCode("0");
                         if (array_key_exists('id', $fields))
                             $this->setTransactionIDSwitcher($fields["id"]);
                     }else{
                         $this->setResponseCode($fields["status"]);
                     }
                 }

                 if ($this->getAPIRequest() == "transfer") {
                     $this->setDescription($fields["status_message"]);
                     if ($fields["status"] == "10000" ) {
                         $this->setResponseCode("0");
                     
                     }else{
                         $this->setResponseCode($fields["status"]);

                     }
                     $this->setDescription($fields["status_message"]);
                 }
             }

             if (array_key_exists('firstname', $fields)) {
                 if ($this->getAPIRequest() == "checkaccount") {
                     $this->setResponseCode("0");
                     $this->setDestAccHolder($fields['firstname'] . ' ' . $fields['lastname']);
                 }
             }


         }else{
             // $this->setFormattedResponse(array('ERR'=>'timeout'));
             $this->setFormattedResponse(array('status' => 'PRC', 'status_message' => 'timeout'));
             //ERROR but make it processing
             $this->setResponseCode('PRC');
             $this->setDescription('Timeout from Transfer-to');

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
        return (in_array($this->getResponseCode(), array('0','00','10000')) || $this->getResponseStatus()=="PAID");
    }

    public function isPending()
    {
        return (in_array($this->getResponseCode(), array('20000','50000','PRC' ,'60000')) || in_array($this->getResponseStatus(), array(TransferToSwitchFunction::TRANSFERTO_STATUS_INPROCESS, TransferToSwitchFunction::TRANSFERTO_STATUS_OUTSTANDING, TransferToSwitchFunction::TRANSFERTO_STATUS_FOR_VERIFICATION)));
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
        }elseif($this->isSubmitted())

        {
            $status = PaymentRequestStatus::SUBMITTED;
        }


           return $status;
    }

   
    public function getPayerTransactionCode()
    {
        return $this->payer_transaction_code;
    }
    public function setPayerTransactionCode($payer_transaction_code)
    {
        $this->payer_transaction_code = $payer_transaction_code;
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

    public function getQuotID()
    {
        return $this->quotID;
    }


    public function setQuotID($quotID)
    {
        $this->quotID = $quotID;
    }

}