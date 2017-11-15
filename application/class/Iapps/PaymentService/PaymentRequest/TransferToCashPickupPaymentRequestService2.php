<?php

namespace Iapps\PaymentService\PaymentRequest;

use Iapps\Common\Helper\GuidGenerator;
use Iapps\Common\Microservice\RemittanceService\RemittanceTransactionServiceFactory;
use Iapps\PaymentService\Common\TransferToSwitch\TransferToSwitchFunction;
use Iapps\PaymentService\Common\TransferToSwitch\TransferToSwitchResponse;
use Iapps\PaymentService\Common\MessageCode;
use Iapps\PaymentService\Common\TransferToSwitch\TransferToSwitchClientFactory;
use Iapps\PaymentService\Payment\PaymentDescription;
use Iapps\PaymentService\PaymentMode\PaymentModeType;
use Iapps\PaymentService\PaymentModeAttribute\PaymentModeAttributeCode;
use Iapps\PaymentService\PaymentModeAttribute\PaymentModeAttributeServiceFactory;
use Iapps\PaymentService\PaymentRequestValidator\TransferToPaymentRequestValidator;
use Iapps\PaymentService\Common\Logger;
use Iapps\Common\Microservice\AccountService\AccountServiceFactory;
use Iapps\Common\Core\IappsDateTime;
use Iapps\PaymentService\Payment\PaymentServiceFactory;
use Illuminate\Support\Facades\Log;

class TransferToCashPickupPaymentRequestService2 extends PaymentRequestService{

    function __construct(PaymentRequestRepository $rp, $ipAddress = '127.0.0.1', $updatedBy = NULL)
    {
        parent::__construct($rp, $ipAddress, $updatedBy);
        $this->payment_code = PaymentModeType::CASH_PICKUP_TRANSFERTO_2;
    }

    public function complete($user_profile_id, $request_id, $payment_code, array $response)
    {
        if( $response =  parent::complete($user_profile_id, $request_id, $payment_code, $response) )
        {
            if( $this->_request instanceof PaymentRequest )
            {
                $response['additional_info'] = $this->_request->getResponseFields(array('bank_code', 'account_no', 'reference_no', 'trans_date'));
            }

            return $response;
        }

        return false;
    }

    /*
     * TransferTo only call to switch upon complete
     */
    public function _requestAction(PaymentRequest $request)
    {

        try{
            $TransferTo_switch_client = TransferToSwitchClientFactory::build();
        }
        catch(\Exception $e)
        {//this is internal error, should not happen
            $this->setResponseCode(MessageCode::CODE_INVALID_SWITCH_SETTING);
            return false;
        }


        $TransferTo_switch_client->setReferenceNo($request->getTransactionID() );
        $TransferTo_switch_client->setTransactionID($request->getTransactionID());
        $country_currency_code = $request->getCountryCurrencyCode();
        
        $TransferTo_switch_client->setCountryCurrencyCode($country_currency_code);
        $TransferTo_switch_client->setPaymentMode(TransferToSwitchFunction::PHILIPPINES_CP);
        
        if( $sender_dob = $request->getOption()->getValue('sender_dob'))
            $TransferTo_switch_client->setSenderDob($sender_dob);
        if( $sender_gender = $request->getOption()->getValue('sender_gender'))
            $TransferTo_switch_client->setSenderGender($sender_gender);
        if( $sender_nationality = $request->getOption()->getValue('sender_nationality'))
            $TransferTo_switch_client->setSenderNationality($sender_nationality);
        if( $sender_host_countrycode = $request->getOption()->getValue('sender_host_countrycode'))
            $TransferTo_switch_client->setSenderHostCountrycode($sender_host_countrycode);
        if( $sender_host_identity = $request->getOption()->getValue('sender_host_identity') )
            $TransferTo_switch_client->setSenderHostIdentity($sender_host_identity);
        if( $sender_host_identitycard = $request->getOption()->getValue('sender_host_identitycard'))
            $TransferTo_switch_client->setSenderHostIdentitycard($sender_host_identitycard);
        //if( $account = $request->getOption()->getValue('bank_account') )   //no need
        //    $TransferTo_switch_client->setAccountNo($account);

        if( $account_holder_name = $request->getOption()->getValue('account_holder_name') )
            $TransferTo_switch_client->setReceiverFullname($account_holder_name);
        if( $receiver_full_name = $request->getOption()->getValue('receiver_full_name') )
            $TransferTo_switch_client->setReceiverFullname($receiver_full_name);
        if( $receiver_fullname = $request->getOption()->getValue('receiver_fullname') )
            $TransferTo_switch_client->setReceiverFullname($receiver_fullname);

        //if( $bank_code = $request->getOption()->getValue('bank_code') )  //no need
        //    $TransferTo_switch_client->setBankCode($bank_code);
        if( $sender_address = $request->getOption()->getValue('sender_address') )
            $TransferTo_switch_client->setSenderAddress($sender_address);
        if( $sender_phone = $request->getOption()->getValue('sender_phone') )
            $TransferTo_switch_client->setSenderPhone($sender_phone);
        if( $sender_fullname = $request->getOption()->getValue('sender_fullname') )
            $TransferTo_switch_client->setSenderFullname($sender_fullname);
        if( $receiver_address = $request->getOption()->getValue('receiver_address') )
            $TransferTo_switch_client->setReceiverAddress($receiver_address);
        if( $receiver_mobile_phone = $request->getOption()->getValue('receiver_mobile_phone') )
            $TransferTo_switch_client->setReceiverMobilePhone($receiver_mobile_phone);

        if( $receiver_gender = $request->getOption()->getValue('receiver_gender') )
            $TransferTo_switch_client->setReceiverGender($receiver_gender);
        if( $receiver_birth_date = $request->getOption()->getValue('receiver_birth_date') )
            $TransferTo_switch_client->setReceiverBirthDate($receiver_birth_date);
        if( $receiver_email = $request->getOption()->getValue('receiver_email') )
            $TransferTo_switch_client->setReceiverEmail($receiver_email);
        
        
        if( $landed_currency = $request->getOption()->getValue('landed_currency') )
            $TransferTo_switch_client->setLandedCurrency($landed_currency);

        $transDate = IappsDateTime::fromString($request->getDateOfTransfer());
        $TransferTo_switch_client->setTransDate($transDate->getFormat('Y-m-d'));

        $TransferTo_switch_client->setLandedAmount(-1*$request->getAmount());


        $option_array = json_decode($TransferTo_switch_client->getOption(), true);
        //set user type
        if( $user_type = $request->getOption()->getValue('user_type')) {
            $option_array['user_type'] = $user_type;
        }

        $request->getOption()->setArray($option_array);

        //this validation in main class
        $v = TransferToPaymentRequestValidator::make($request);
        if( !$v->fails() )
        {

               $request->setStatus(PaymentRequestStatus::PENDING);
               return true;

        }

        $this->setResponseCode(MessageCode::CODE_PAYMENT_INVALID_INFO);
        return false;
    }

    public function _completeAction(PaymentRequest $request)
    {
        //make request to switch
        try{
            $transferto_switch_client = TransferToSwitchClientFactory::build($request->getOption()->toArray());
        }
        catch(\Exception $e)
        {//this is internal error, should not happen
            $this->setResponseCode(MessageCode::CODE_INVALID_SWITCH_SETTING);
            return false;
        }

        //set default payment mode = CP2
        $transferto_switch_client->setPaymentMode(TransferToSwitchFunction::PHILIPPINES_CP);


        if(!empty($request->getReferenceID())){
            $request->setPending();
            //Processed already
            Logger::debug('TransferTo Processed');
            return false;
        }


        $transferto_switch_client->setResponseFields($request->getResponse()->toArray());

        $response = $transferto_switch_client->bankTransfer() ;
        $request->getResponse()->setJson(json_encode(array("Transferto Bank Transfer"=>$transferto_switch_client->getTransactionType())));


        if($response )
        {

            $result = $this->_checkResponse($request, $response);
            $request->getResponse()->add('transferto_response', $response->getFormattedResponse());
            $request->getResponse()->add('transferto_process', $transferto_switch_client->getTransfertoInfo());

            $request->setReferenceID($response->getTransactionIDSwitcher());
            if( $result ) {
                $request->setReferenceID($response->getTransactionIDSwitcher());
                return parent::_completeAction($request);
            }else{
                if($request->getStatus()==PaymentRequestStatus::FAIL){
                    $this->setResponseMessage($response->getDescription());
                    Logger::debug('Transferto Failed - ' . $request->getStatus() . ': ' . $response->getResponse() . ': ' . $transferto_switch_client->getTransfertoInfo());
                }
                if($request->getStatus()==PaymentRequestStatus::PENDING){
                    $this->setResponseMessage($response->getDescription());
                    Logger::debug('Transferto Pending - ' . $request->getStatus() . ': ' . $response->getResponse() . ': ' . $transferto_switch_client->getTransfertoInfo());
                }
            }

        }else{
            $request->getResponse()->add('transferto_process', $transferto_switch_client->getTransfertoInfo());  //sent and return data tmoney
            Logger::error('Transferto Error Process Log - ' . $transferto_switch_client->getTransfertoInfo());
        }


        return false;
    }

    public function findPendingRequest(){
        $payment_request = new PaymentRequest();
        $payment_request->setPending();
        $payment_request->setPaymentCode($this->getPaymentCode());
        $requests = $this->getRepository()->findBySearchFilter($payment_request, null, null, null);

        return $requests;
    }

    public function findPendingCollectionRequest(){
        $payment_request = new PaymentRequest();
        $payment_request->setPendingCollection();
        $payment_request->setPaymentCode($this->getPaymentCode());
        $requests = $this->getRepository()->findBySearchFilter($payment_request, null, null, null);

        return $requests;
    }

    public function reprocessRequest(PaymentRequest $request){
        //make request to switch
        try{
            $transferto_switch_client = TransferToSwitchClientFactory::build($request->getOption()->toArray());
        }
        catch(\Exception $e)
        {//this is internal error, should not happen
            $this->setResponseCode(MessageCode::CODE_INVALID_SWITCH_SETTING);
            return false;
        }
        //get last info
        $transferto_switch_client->setResponseFields($request->getResponse()->toArray());

        $last_response = $request->getResponse()->toArray() ;

        if(array_key_exists("transferto_response",$last_response)) {

            $transferto_response = $last_response["transferto_response"];
            $transferto_response_arr = json_decode($transferto_response,true) ;

            if(array_key_exists("status",$transferto_response_arr)) {

                if ($transferto_response_arr["status"] == "PRC" || $transferto_response_arr["status"] == "20000"  || $transferto_response_arr["status"] == "50000" || $transferto_response_arr["status"] == "60000") {
                    if ($response = $transferto_switch_client->bankTransfer()) {
                        $ori_request = clone($request);

                        $result = $this->_checkResponse($request, $response);
                        $this->getRepository()->startDBTransaction();
                        if ($result) {

                            if ($complete = parent::_completeAction($request)) {
                                $request->getResponse()->setJson(json_encode(array("TMoney Bank Transfer" => $transferto_switch_client->getTransactionType())));
                                $request->getResponse()->add('transferto_response', $response->getFormattedResponse());
                                $request->getResponse()->add('transferto_process', $transferto_switch_client->getTransfertoInfo());
                                $request->setReferenceID($response->getRefNoSwitcher());

                                if (parent::_updatePaymentRequestStatus($request, $ori_request)) {
                                    $this->getRepository()->completeDBTransaction();
                                    $this->setResponseCode(MessageCode::CODE_REQUEST_COMPLETED);
                                    return true;
                                } else {
                                    Logger::debug("reprocessRequest failed");
                                    $this->getRepository()->rollbackDBTransaction();
                                    $this->setResponseCode(MessageCode::CODE_PAYMENT_REQUEST_FAIL); //***
                                    return false;
                                }
                            }
                        } else {//failed or still processing

                            $request->getResponse()->setJson(json_encode(array("TMoney Bank Transfer" => $transferto_switch_client->getTransactionType())));
                            $request->getResponse()->add('transferto_response', $response->getFormattedResponse());
                            $request->getResponse()->add('transferto_process', $transferto_switch_client->getTransfertoInfo());

                            if ($request->getStatus() == PaymentRequestStatus::FAIL) {
                                $this->setResponseMessage($response->getRemarks());
                                $request->setFail();
                                $this->getRepository()->updateResponse($request);
                                $this->getRepository()->completeDBTransaction();
                                return true;
                            } elseif ($request->getStatus() == PaymentRequestStatus::PENDING) {

                                if($ori_request->getStatus() == PaymentRequestStatus::PENDING){
                                    if( $response->getPayerTransactionCode()){
                                        $request->setPendingCollection();     //set to pending collection
                                        $this->getRepository()->updateResponse($request);
                                        $this->getRepository()->completeDBTransaction();
                                        return true;    //true/false to publishPaymentRequestChanged
                                    }else{
                                        $this->getRepository()->updateResponse($request);
                                        $this->getRepository()->completeDBTransaction();
                                        return false;                                        
                                    }      
                                }
                            }
                        }
                        $this->getRepository()->rollbackDBTransaction();

                    }
                }
            }

        }
        return false;
    }

    public function updateRequest(PaymentRequest $request){
        if($this->getRepository()->update($request)){
            return true;
        }
        return false;
    }

    public function inquireRequest(PaymentRequest $request){
        try{
            $TransferTo_switch_client = TransferToSwitchClientFactory::build($request->getOption()->toArray());
        }
        catch(\Exception $e)
        {//this is internal error, should not happen
            $this->setResponseCode(MessageCode::CODE_INVALID_SWITCH_SETTING);
            return false;
        }


        if($response = $TransferTo_switch_client->inquiry() ) {   //add for TransferTo only
           if (!$response->getResponseCode() == '0') {
                return false;
            }

           return $response;
        }
        return false;
    }

    protected function _generateDetail1(PaymentRequest $request)
    {
        //get bank transfer detail
        $bank_name ="";

        if ($request->getOption() != NULL) {
            $desc = new PaymentDescription();

            $attrServ = PaymentModeAttributeServiceFactory::build();

            $option_array = $request->getOption()->toArray();
            if ($option_array != NULL) {
                if (array_key_exists('reference_no', $option_array)) {
                    $desc->add('Transfer Ref No.', $option_array['reference_no']);
                }
                if (array_key_exists('bank_code', $option_array)) {
                    if( $value = $attrServ->getValueByCode($this->payment_code, PaymentModeAttributeCode::BANK_CODE, $option_array['bank_code']) )
                        $bank_name = $value->getValue();
                    $desc->add('Bank', $bank_name);
                }
                if (array_key_exists('account_no', $option_array)) {
                    $desc->add('Bank Account No.', $option_array['account_no']);
                }
                if (array_key_exists('trans_date', $option_array)) {
                    $desc->add('Date of Transfer', $option_array['trans_date']);
                }
            }

            $request->setDetail1($desc);
        }

        return true;
    }


    public function _checkAccount($bank_code, $account_number,$acc_holder_name)
    {

        //make chcekTrx to switch
        $request =[];
        try{
            $TransferTo_switch_client = TransferToSwitchClientFactory::build($request);
        }
        catch(\Exception $e)
        {//this is internal error, should not happen
            $this->setResponseCode(MessageCode::CODE_INVALID_SWITCH_SETTING);
            return false;
        }

        if($response = $TransferTo_switch_client->checkAccount($bank_code,$account_number) )
        {

            $result = array(
                //'responseCode'=>$response->getResponseCode(),
                'bankAccount'=>$response->getDestBankacc(),
                'CorrectAccountHolderName'=>$response->getDestAccHolder(),
                'description'=>$response->getDescription()
                //'formatResponse'=>$response->getFormattedResponse()
            );

            if ($response->getResponseCode() == "00" || $response->getResponseCode() == "0") {
                $this->setResponseCode(MessageCode::CODE_CHECK_BANK_ACCOUNT_SUCCESS);
                if(strtoupper($acc_holder_name) == strtoupper($response->getDestAccHolder()) ){
                    $this->setResponseCode(MessageCode::CODE_CHECK_ACCOUNT_HOLDER_NAME_SUCCESS);
                    $result["description"] = "Success";
                }else{
                    //$result["responseCode"] = "01";
                    $this->setResponseCode(MessageCode::CODE_CHECK_ACCOUNT_HOLDER_NAME_FAILED);
                    $result["description"] = "Invalid Account Holder Name";
                }

            }else{
                $this->setResponseCode(MessageCode::CODE_CHECK_BANK_ACCOUNT_FAILED);
            }
            //$this->setResponseMessage("Check Bank Account Failed");
            return $result ;
        }

        return false;
    }


}