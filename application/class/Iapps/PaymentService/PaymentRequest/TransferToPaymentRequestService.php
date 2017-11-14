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
use Iapps\Common\Microservice\RemittanceService\RemittanceTransactionService;
use Iapps\PaymentService\Common\ReconFileS3Uploader;
use Iapps\PaymentService\Common\CoreConfigDataServiceFactory;
use Iapps\PaymentService\Common\CoreConfigType;
use Iapps\Common\CommunicationService\EmailAttachment;
use Iapps\Common\CommunicationService\CommunicationServiceProducer;
use Iapps\Common\Microservice\RemittanceService\RemittanceRecordServiceFactory;
use phpseclib\Crypt\RSA;
use phpseclib\Net\SFTP;
use Iapps\Common\AuditLog\AuditLogAction;



class TransferToPaymentRequestService extends PaymentRequestService{

    function __construct(PaymentRequestRepository $rp, $ipAddress = '127.0.0.1', $updatedBy = NULL)
    {
        parent::__construct($rp, $ipAddress, $updatedBy);
        $this->payment_code = PaymentModeType::BANK_TRANSFER_TRANSFERTO;
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

        $TransferTo_switch_client->setReferenceNo($request->getTransactionID());
        $TransferTo_switch_client->setTransactionID($request->getTransactionID());
        $country_currency_code = $request->getCountryCurrencyCode();

        $TransferTo_switch_client->setCountryCurrencyCode($country_currency_code);

        $TransferTo_switch_client->setPaymentMode(TransferToSwitchFunction::BANK_TRANSFER_TT1);



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

        if( $account = $request->getOption()->getValue('bank_account') )
            $TransferTo_switch_client->setAccountNo($account);
        if( $account = $request->getOption()->getValue('account_no') )
            $TransferTo_switch_client->setAccountNo($account);

        if( $receiver_fullname = $request->getOption()->getValue('account_holder_name') )
            $TransferTo_switch_client->setReceiverFullname($receiver_fullname);
        if( $bank_code = $request->getOption()->getValue('bank_code') )
            $TransferTo_switch_client->setBankCode($bank_code);

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

        //call getOption at TransferToSwitchClient.php , decode from json
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

        if(!empty($request->getReferenceID())){
            $request->setPending();
            //Processed already
            Logger::debug('TransferTo Processed');
            return false;
        }

        $transferto_switch_client->setPaymentMode(TransferToSwitchFunction::BANK_TRANSFER_TT1);

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

                if ($transferto_response_arr["status"] == "PRC" || $transferto_response_arr["status"] == "20000" ) {
                    if ($response = $transferto_switch_client->bankTransfer()) {
                        $ori_request = clone($request);

                        $result = $this->_checkResponse($request, $response);
                        $this->getRepository()->startDBTransaction();
                        if ($result) {

                            if ($complete = parent::_completeAction($request)) {
                                $request->getResponse()->setJson(json_encode(array("Transferto Bank Transfer" => $transferto_switch_client->getTransactionType())));
                                $request->getResponse()->add('transferto_response', $response->getFormattedResponse());
                                $request->getResponse()->add('transferto_process', $transferto_switch_client->getTransfertoInfo());
                                $request->setReferenceID($response->getTransactionIDSwitcher());

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

                            $request->getResponse()->setJson(json_encode(array("Transferto Bank Transfer" => $transferto_switch_client->getTransactionType())));
                            $request->getResponse()->add('transferto_response', $response->getFormattedResponse());
                            $request->getResponse()->add('transferto_process', $transferto_switch_client->getTransfertoInfo());

                            if ($request->getStatus() == PaymentRequestStatus::FAIL) {
                                $this->setResponseMessage($response->getRemarks());
                                $request->setFail();
                                $this->getRepository()->updateResponse($request);
                                $this->getRepository()->completeDBTransaction();
                                return true;
                            } elseif ($request->getStatus() == PaymentRequestStatus::PENDING) {
                                $this->getRepository()->updateResponse($request);
                                $this->getRepository()->completeDBTransaction();
                                return false;
                            }
                        }
                        $this->getRepository()->rollbackDBTransaction();

                    }
                }
            }

        }
        return false;
    }

    public function findPendingRequestByDate($trx_date){
        //$datefrom = date('Y-m-d', strtotime($date. ' -5 days'));
        //$dateto =   date ('Y-m-d', strtotime($date. ' -0  days'));
        $datefrom = $trx_date . " 00:00:00";
        $dateto = $trx_date . " 23:59:59";

        $paymentRequestServ = SearchPaymentRequestServiceFactory::build();
        $paymentRequestServ->setFromCreatedAt(IappsDateTime::fromString($datefrom));
        $paymentRequestServ->setToCreatedAt(IappsDateTime::fromString($dateto));
        $requestFilter = new PaymentRequest();
        //$requestFilter->setStatus(PaymentRequestStatus::FAIL);
        $requestFilter->setPaymentCode($this->getPaymentCode());
        $request = $paymentRequestServ->getPaymentBySearchFilter($requestFilter, MAX_VALUE, 1) ;
        return $request ;
    }

    public function findTrxInReportFile($filename,$trx_id){
        $lines = file($filename);

        $j=0;
        $notfound = true;
        foreach ($lines as $line_num => $line) {
            $clm = explode(',',$line);
            if ($j > 0) {
                $transactionIDTt = trim(substr($clm[4], 0, 20));
                if ($transactionIDTt == trim($trx_id)) {
                    $notfound=false;
                    break;
                }else{
                    $notfound = true;
                }

            }
            $j++;
        }
        return $notfound;
    }


    public function reconTransaction($recon_date){

        //$this->getFileRecon($trx_date);
        $configServ = CoreConfigDataServiceFactory::build();
        $user_client    =  AccountServiceFactory::build();
        //$remittanceRecordService = RemittanceRecordServiceFactory::build();
        $remittanceTrxService = RemittanceTransactionServiceFactory::build("admin");
        //$remittanceRecordService = RemittanceRecordServiceFactory::build();




        $out_path =  $configServ->getConfig(CoreConfigType::TRANSFERTO_INTERFACE_OUT_PATH);
        $in_path =  $configServ->getConfig(CoreConfigType::TRANSFERTO_INTERFACE_IN_PATH);


        $transferto_file =  "iApps-". $recon_date .".csv";
        $in_file = $in_path.$transferto_file;

        $trx_date = date('Y-m-d', strtotime('-1 days', strtotime($recon_date)));



        if(!file_exists($in_file)) {
            return false;
        }

        //------------------------------ create success and suspect file -----
        // based on transferto report file
        $lines = file($in_file);
        $j=0;
        //$header= "#,Application,User ID,Reference No,Send Currency,Settlement Amount SGD,Receive Currency,Receive Amount,Cost Currency,Cost,Status,SLIDE Receipt No.,External Reference No,Time of API Call,Send Currency,Send Amount,Receive Currency,Receive Amount,Transaction Status,Recon Status,New Transaction Status,Refund ID ,Refund Status,Field Not Matched,New Refund Status\n";
        $header= "#,Application,User ID,Reference No,Send Currency,Settlement Amount SGD,Receive Currency,Receive Amount,T2 Comission USD,Status,SLIDE Receipt No.,External Reference No,Time of API Call,Send Currency,Send Amount,Receive Currency,Receive Amount,Transaction Status ,Refund Status  ,Refund Transaction ID ,Recon Status ,Field Not Matched ,New Transaction Status (Auto Update)\n";

        $dataOk="";
        $dataSuspect="";
        $dataForced ="";
        $dataRecon ="";
        $tot_match = 0;
        $tot_not_match =0;
        $tot_not_found =0;
        $sgd_send = 0;$sgd_receive = 0;$sgd_fee = 0 ;
        $idr_send = 0;$idr_receive = 0;$idr_fee = 0 ;
        $php_send = 0;$php_receive = 0;$php_fee = 0 ;
        $mmk_send = 0;$mmk_receive = 0;$mmk_fee = 0 ;
        $slide_trx =0;
        $slide_sgd_send = 0;$slide_sgd_receive = 0;
        $slide_idr_send = 0;$slide_idr_receive = 0;
        $slide_php_send = 0;$slide_php_receive = 0;
        $slide_mmk_send = 0;$slide_mmk_receive = 0;
        $total_tt_trx=0;


        foreach ($lines as $line_num => $line) {
            $statusdb="";
            $line = str_replace("\n","",$line);
            $line = str_replace("\r","",$line);
            $line = trim($line);
            $clm = explode(',',$line);
            $transactionID = substr(trim($clm[4]),0,19) ;
            $header_id = substr($transactionID,0,13);
            $transaction_id_in = substr($transactionID,13,6) - 1;
            for($i=strlen($transaction_id_in);$i<6;$i++){
                $transaction_id_in = "0" . $transaction_id_in ;
            }
            $transaction_id_in = $header_id . $transaction_id_in ;

            if($j > 0   && $transactionID != ""){
                $status = $clm[2];
                $reference_id = $clm[3];
                $send_currency = 'SGD';
                $send_amount = $clm[12];       //transferto
                $receive_currency = $clm[11];
                $receive_amount = $clm[10];
                $cost_currency = 'SGD';
                $cost_amount = $clm[14];
                $transactionID_db="";
                $timeApi="";
                $field_not_matched = "";
                $remittance_id="";
                $slide_send_amount=0;
                $slide_receive_amount=0;
                $slide_receive_currency="";
                $slide_send_currency="";
                $recon_status="";
                $new_status="";
                $refund_status = "";
                $new_refund_status ="";
                $userId="";$id="";
                $external_ref_id ="";
                $last_response="";$refund_id="";


              if($requests = $this->findRequestByRef($transactionID)) {
                  $total_tt_trx++;

                  foreach ($requests->result as $req) {
                      if ($req instanceof PaymentRequest) {
                          $id = $req->getId();
                          $statusdb = $req->getStatus();
                          $transactionID_db = $req->getTransactionID();
                          $timeApi = $req->getCreatedAt()->getString();
                          $option = $req->getOption()->toArray();
                          $last_response = $req->getResponse()->toArray();
                          $external_ref_id = $req->getReferenceID();
                      }
                  }

                  if (array_key_exists("transferto_response", $last_response)) {
                      $transferto_response = $last_response["transferto_response"];
                      $transferto_process = $last_response["transferto_process"];
                      $transferto_response_arr = json_decode($transferto_response, true);
                      $transferto_process_arr = json_decode($transferto_process, true);
                      if (array_key_exists("id", $transferto_response_arr)) {
                          $external_ref_id = $transferto_response_arr["id"];
                      }
                  }

                  if ($remitt_data = $remittanceTrxService->getTransactionHistoryDetailByRefId($transactionID_db, 1, 1)) {
                      $remittance_id = $remitt_data->result->remittance->remittanceID;
                      $remitt_id = $remitt_data->result->remittance->id;
                      $reason = $remitt_data->result->remittance->reason;
                      $userId = $remitt_data->result->remittance->sender->accountID;
                      $slide_send_amount = $remitt_data->result->remittance->from_amount;
                      $slide_receive_amount = $remitt_data->result->remittance->to_amount;
                      $slide_send_currency = explode("-", $remitt_data->result->remittance->from_country_currency_code)[1];
                      $slide_receive_currency = explode("-", $remitt_data->result->remittance->to_country_currency_code)[1];
                  }
              }

                 //('pending', 'success', 'fail', 'cancelled', 'pending_collection')
                 if (trim($status) == "COMPLETED") {
                     if (trim($statusdb) == "success") {
                         $dataOk = $dataOk . $line . "," . $statusdb . "\n";   //transferto success  - iapps success
                         $recon_status = "matched";
                         $tot_match++;
                         $slide_trx++;
                     }
                     if (trim($statusdb) == "fail" || trim($statusdb) == "pending" || trim($statusdb) == "pending_collection") {
                         $dataSuspect = $dataSuspect . $line . "," . $statusdb . "\n";  //success - fail   (low suspect)
                         $recon_status = "not matched";
                         $new_status = "collected";
                         $refund_status = "initated";
                         $tot_not_match++;
                         $field_not_matched = "Transaction Status";
                         $slide_trx++;
                     }
                     if (trim($statusdb) == "") {
                         $dataSuspect = $dataSuspect . $line . "," . $statusdb . "\n";  //success - not found   (high suspect)
                         $recon_status = "not found";
                         $new_status = "collected";
                         $tot_not_found++;
                     }
                 }else if(trim($status) == "SUBMITTED"){

                     if (trim($statusdb) == "pending") {
                         $dataOk = $dataOk . $line . "," . $statusdb . "\n";   //transferto success  - iapps success
                         $recon_status = "matched";
                         $tot_match++;
                         $slide_trx++;
                     }

                     if (trim($statusdb) == "fail" || trim($statusdb) == "success" || trim($statusdb) == "pending_collection") {
                         $dataSuspect = $dataSuspect . $line . "," . $statusdb . "\n";  //success - fail   (low suspect)
                         $recon_status = "not matched";
                         $new_status = "pending";
                         $tot_not_match++;
                         $field_not_matched = "Transaction Status";
                         $slide_trx++;
                     }

                     if (trim($statusdb) == "") {
                         $dataSuspect = $dataSuspect . $line . "," . $statusdb . "\n";  //success - not found   (high suspect)
                         $recon_status = "not found";
                         $new_status = "pending";
                         $tot_not_found++;
                     }
                 }else if(trim($status) == "AVAILABLE"){

                     if (trim($statusdb) == "pending_collection") {
                         $dataOk = $dataOk . $line . "," . $statusdb . "\n";   //transferto success  - iapps success
                         $recon_status = "matched";
                         $tot_match++;
                         $slide_trx++;
                     }

                     if (trim($statusdb) == "fail" || trim($statusdb) == "success" || trim($statusdb) == "pending") {
                         $dataSuspect = $dataSuspect . $line . "," . $statusdb . "\n";  //success - fail   (low suspect)
                         $recon_status = "not matched";
                         $new_status = "pending_collection";
                         $tot_not_match++;
                         $field_not_matched = "Transaction Status";
                         $slide_trx++;
                     }

                     if (trim($statusdb) == "") {
                         $dataSuspect = $dataSuspect . $line . "," . $statusdb . "\n";  //success - not found   (high suspect)
                         $recon_status = "not found";
                         $new_status = "pending_collection";
                         $tot_not_found++;
                     }
                 } else {
                     if (trim($statusdb) == "success"  || trim($statusdb) == "pending" || trim($statusdb) == "pending_collection") {
                         $dataForced = $dataForced . $line . "," . $statusdb . "\n";   //fail - success    high forced
                         $recon_status = "not matched";
                         $new_status = "failed";
                         $tot_not_match++;
                         //$refund_status = "initated";
                         $field_not_matched = "Transaction Status";
                         $slide_trx++;
                     }
                     if (trim($statusdb) == "fail") {
                         $dataOk = $dataOk . $line . "," . $statusdb . "\n";  //fail - fail
                         $recon_status = "matched";
                         $tot_match++;
                         $slide_trx++;
                     }
                     if (trim($statusdb) == "") {
                         $dataSuspect = $dataSuspect . $line . "," . $statusdb . "\n";   //fail - not found   (high suspect)
                         $recon_status = "not found";
                         $new_status = "failed";
                         $tot_not_found++;
                     }
                 }



                 if ($new_status == "failed" || trim($statusdb) == "fail" ) {
                     if ($refund = $remittanceTrxService->getRefundRequestByTransactionID($transaction_id_in)) {
                         $new_refund_status = $refund->result->status;
                         $refund_id = $refund->result->refundID;
                     }
                 }

                 if ($reference_id != $external_ref_id) {
                     $recon_status = "not matched";
                     $field_not_matched = "Reference No";
                     $tot_match = $tot_match -1;$tot_not_match++;
                 }
                 if ($receive_amount != $slide_receive_amount) {
                     $recon_status = "not matched";
                     $field_not_matched = "Receive Amount";
                     $tot_match = $tot_match -1;$tot_not_match++;
                 }

                if($recon_status == "matched") {
                    //transferto summary
                    if (trim($send_currency) == "SGD")    //settlement with slide is sgd
                        $sgd_send = $sgd_send + $send_amount;
                    if (trim($send_currency) == "IDR")
                        $idr_send = $idr_send + $send_amount;
                    if (trim($send_currency) == "PHP")
                        $php_send = $php_send + $send_amount;
                    if (trim($send_currency) == "MMK")
                        $mmk_send = $mmk_send + $send_amount;

                    if (trim($receive_currency) == "SGD")
                        $sgd_receive = $sgd_receive + $receive_amount;
                    if (trim($receive_currency) == "IDR")
                        $idr_receive = $idr_receive + $receive_amount;
                    if (trim($receive_currency) == "PHP")
                        $php_receive = $php_receive + $receive_amount;
                    if (trim($receive_currency) == "MMK")
                        $mmk_receive = $mmk_receive + $receive_amount;

                    if (trim($cost_currency) == "SGD")
                        $sgd_fee = $sgd_fee + $cost_amount;


                    //slide summary
                    if (trim($slide_send_currency) == "SGD")
                        $slide_sgd_send = $slide_sgd_send + $slide_send_amount;
                    if (trim($slide_send_currency) == "IDR")
                        $slide_idr_send = $slide_idr_send + $slide_send_amount;
                    if (trim($slide_send_currency) == "PHP")
                        $slide_php_send = $slide_php_send + $slide_send_amount;
                    if (trim($slide_send_currency) == "MMK")
                        $slide_mmk_send = $slide_mmk_send + $slide_send_amount;

                    if (trim($slide_receive_currency) == "SGD")
                        $slide_sgd_receive = $slide_sgd_receive + $slide_receive_amount;
                    if (trim($slide_receive_currency) == "IDR")
                        $slide_idr_receive = $slide_idr_receive + $slide_receive_amount;
                    if (trim($slide_receive_currency) == "PHP")
                        $slide_php_receive = $slide_php_receive + $slide_receive_amount;
                    if (trim($slide_receive_currency) == "MMK")
                        $slide_mmk_receive = $slide_mmk_receive + $slide_receive_amount;
                }else{
                    $payment_request_status= Null;
                    if($new_status == "collected") {
                        $payment_request_status = "success";
                    }else if($new_status == "failed"){
                        $payment_request_status = "fail";
                    }else{
                        $payment_request_status = $new_status;
                    }

                    if($payment_request_status) {
                        $this->updatePaymentRequest($payment_request_status, $transactionID);
                    }
                }

                 $row = $j . "," . "slide" . "," . $userId . "," . $reference_id . "," . $send_currency . "," . $send_amount . "," . $receive_currency . "," . $receive_amount
                     . "," . $cost_amount . "," . $status . "," . $remittance_id . "," . $external_ref_id . "," . $timeApi
                     . "," . $slide_send_currency . "," . $slide_send_amount . "," . $slide_receive_currency . "," . $slide_receive_amount
                     . "," . $statusdb . " ," . $new_refund_status . "  ," . $refund_id . "," . $recon_status . "  ," . $field_not_matched . "  ," . $new_status;

                 $dataRecon = $dataRecon . $row . "\n";


            }
            $j++;
        }

        //$filename ="../files/transferto-recon/transferto-ok-". $trx_date .".csv";
        //$this->createFileRecon($filename,$dataOk);
        //$filename ="../files/transferto-recon/transferto-suspect-". $trx_date .".csv";
        //$this->createFileRecon($filename,$dataSuspect);


        //------------------------------ create forced file   based on iapps database -----
        if ($requests = $this->findPendingRequestByDate($trx_date)){

            $i=0;$k=0;
            $dataNotFound01="";
            $dataNotFound02="";
            foreach ($requests->result as $req) {
                if ($req instanceof PaymentRequest) {
                    $userId = $req->getUserProfileId();
                    $reference_id = "";
                    $external_ref_id = $req->getReferenceID();
                    $send_currency = "";
                    $send_amount = "";
                    $receive_currency ="";
                    $receive_amount ="";
                    $cost_currency = "";
                    $cost_amount="";
                    $status = "";
                    $transactionID_db = $req->getTransactionID();
                    $timeApi = $req->getCreatedAt()->getString();
                    $statusdb = $req->getStatus();
                    $recon_status = "not found";
                    $new_status ="";
                    $refund_status="";
                    $new_refund_status="";
                    $refund_id="";
                    $slide_send_amount =0;
                    $slide_send_currency = "";
                    $slide_receive_amount =0;
                    $slide_receive_currency = "";
                    $field_not_matched="";
                    $remittance_id="";
                    $header_id = substr($transactionID_db,0,13);
                    $transaction_id_in = substr($transactionID_db,13,6) - 1;
                    for($i=strlen($transaction_id_in);$i<6;$i++){
                        $transaction_id_in = "0" . $transaction_id_in ;
                    }
                    $transaction_id_in = $header_id . $transaction_id_in ;

                    if($remitt_data = $remittanceTrxService->getTransactionHistoryDetailByRefId($transactionID_db,1,1)){
                        $remittance_id = $remitt_data->result->remittance->remittanceID ;
                        $userId = $remitt_data->result->remittance->sender->accountID ;
                        $slide_send_amount = $remitt_data->result->remittance->from_amount ;
                        $slide_receive_amount = $remitt_data->result->remittance->to_amount ;
                        $slide_send_currency = explode("-",$remitt_data->result->remittance->from_country_currency_code)[1] ;
                        $slide_receive_currency = explode("-",$remitt_data->result->remittance->to_country_currency_code)[1] ;
                    }

                    $last_response = $req->getResponse()->toArray() ;
                    if(array_key_exists("transferto_response",$last_response)) {
                        $transferto_response = $last_response["transferto_response"];
                        $transferto_process = $last_response["transferto_process"];
                        $transferto_response_arr = json_decode($transferto_response, true);
                        $transferto_process_arr = json_decode($transferto_process, true);
                        if(array_key_exists("quotation_response",$transferto_process_arr)) {
                            //$slide_send_amount = $transferto_process_arr["quotation_response"]["source"]["amount"];
                            //$slide_send_currency = $transferto_process_arr["quotation_response"]["source"]["currency"];
                        }
                    }

                    $data  = $req->getCreatedAt()->getString() . "," ;
                    $data .= " "."," ;      //operation date
                    $data .= " "."," ;      //transaction status
                    $data .= $req->getReferenceID() . "," ;      //t2_id
                    $data .= $req->getTransactionID() . "," ;    //ext_id
                    $data .= " "."," ;      //sender country
                    $data .= " "."," ;      //sender name
                    $data .= " "."," ;      //recipient country
                    $data .= " "."," ;      //recipient name
                    $data .= " "."," ;      //recipient msisdn
                    $data .= " "."," ;      //received amount
                    $data .= " "."," ;      //received amount currency
                    $data .= " "."," ;      //settlement amount sgd
                    $data .= " "."," ;      //payout rate sgd
                    $data .= " "."," ;      //t2_commission_sgd
                    $data .= " "."," ;      //total_value_sgd

                    $data .= $req->getStatus() ;
                    $notfound =$this->findTrxInReportFile($in_file,$req->getTransactionID());
                    if($notfound){
                        $tot_not_found++;

                        if($refund =    $remittanceTrxService->getRefundRequestByTransactionID($transaction_id_in)){
                            $new_refund_status = $refund->result->status;
                            $refund_id = $refund->result->refundID;
                        }

                        $k++;
                        $slide_trx++;
                        if ($req->getStatus() == "success"){
                            $dataNotFound01 = $dataNotFound01 . $data . "\n";   //transferto not found  - iapps success
                            $dataForced = $dataForced . $data . "\n";   // transferto not found -  iapps success   high forced
                        }else{
                            $dataNotFound02 = $dataNotFound02 . $data . "\n";   //transferto not found  - iapps failed  repayment again

                            if ($req->getStatus() == "fail" || $req->getStatus() == "failed") {
                                $refund_status = "initated";
                            }
                        }

                        $row = $slide_trx .","."slide".",".$userId.",".$reference_id.",".$send_currency.",".$send_amount.",".$receive_currency.",".$receive_amount
                            .",".$cost_amount.",".$status.",".$remittance_id ."," . $external_ref_id.",".$timeApi
                            . ",".$slide_send_currency.",".$slide_send_amount.",".$slide_receive_currency.",".$slide_receive_amount
                            . ",".$statusdb."  ,".$new_refund_status ."  ,".$refund_id." ,".$recon_status."   ,".$field_not_matched ." ,".$new_status ;
                        $dataRecon = $dataRecon . $row .  "\n";
                    }
                }
                $i++;
            }

            //$filename ="../files/transferto-recon/transferto-forced-". $trx_date .".csv";
            //$this->createFileRecon($filename,$dataForced);
        }


        $title =  "Transaction Date ," .$recon_date . "(Asia/Singapore)\n";
        $linenull = " ".","." \n";
        $summary  = "Overall Summary".","." \n";
        $total_match = "Total no. of matched transactions:"."," .    $tot_match    ." \n";
        $total_not_match = "Total no. of not matched transactions:".",". $tot_not_match . " \n";
        $total_not_found = "Total no. of not found transactions:".",". $tot_not_found ." \n";
        $summary = $summary . $total_match . $total_not_match . $total_not_found;
        $title = $title .$linenull .$summary .$linenull;

        $transferto_total = "Total no. of transactions from Transfer-To".",".  $total_tt_trx  ." \n";
        $transferto_currency = "Currency".",".  "SGD" . ",".  "IDR" . ",".  "PHP" . ",".  "MMK"." \n";
        $total_send = "Total Send amount:".",".  $slide_sgd_send . ",".  $slide_idr_send . ",".  $slide_php_send . ",".  $slide_mmk_send." \n";

        $transferto_total_send = "Total Settlement amount:".",".  $sgd_send . ",".  $idr_send . ",".  $php_send . ",".  $mmk_send." \n";
        $transferto_total_receive = "Total Receive amount:".",".  $sgd_receive . ",".  $idr_receive . ",". $php_receive . ",".  $mmk_receive." \n";
        $transferto_total_fee = "Total Fee Charged:".",".  $sgd_fee . ",".  $idr_fee . ",".  $php_fee . ",".  $mmk_fee." \n";
        $tt_summary = $transferto_total . $transferto_currency  . $total_send . $transferto_total_send . $transferto_total_receive  . $transferto_total_fee . $linenull ;

        $slide_total = "Total no. of transactions from Slide".",".  $slide_trx  ." \n";
        $slide_currency = "Currency".",".  "SGD" . ",".  "IDR" . ",".  "PHP" . ",".  "MMK"." \n";
        $slide_total_send = "Total Send amount:".",".  $slide_sgd_send . ",".  $slide_idr_send . ",".  $slide_php_send . ",".  $slide_mmk_send." \n";
        $slide_total_receive = "Total Receive amount:".",".  $slide_sgd_receive . ",".  $slide_idr_receive . ",". $slide_php_receive . ",".  $slide_mmk_receive." \n";
        $slide_summary = $slide_total . $slide_currency . $slide_total_send . $slide_total_receive   . $linenull ;

        $dataResult = $title . $tt_summary . $slide_summary. $header . $dataRecon ;
        $trx_datef = str_replace('-','',$recon_date);

        $result_file_name = "SLIDE_TRANSFERTO_". $trx_datef ."_results.csv";

        $out_filename = $out_path . $result_file_name;
        $this->createFileRecon($out_filename,$dataResult);
        $this->_notifyReconcilationFile($transferto_file, $out_path, $result_file_name);

        return true;
    }


    public function updatePaymentRequest($new_status,$trx_id)
    {
        $payment_request = new PaymentRequest();
        $payment_request->setTransactionID($trx_id);
        $this->getRepository()->startDBTransaction();
        if($requests = $this->getRepository()->findBySearchFilter($payment_request, null, null, null)){
            foreach ($requests->result as $req) {
                if ($req instanceof PaymentRequest) {
                    $req->setTransactionID($trx_id);
                    $req->setStatus($new_status);
                    $req->setUpdatedBy($this->getUpdatedBy());

                    if ($this->getRepository()->updateStatus($req)) {
                        $this->getRepository()->completeDBTransaction();
                        $this->setResponseCode(MessageCode::CODE_EDIT_PAYMENT_REQUEST_SUCCESS);
                        //dispatch event to auditLog
                        $this->fireLogEvent('iafb_payment.payment_request', AuditLogAction::UPDATE, $req->getId(), $req);
                        return true;
                    }
                }
            }
        }
        $this->getRepository()->rollbackDBTransaction();
        $this->setResponseCode(MessageCode::CODE_EDIT_PAYMENT_REQUEST_FAILED);
        return false;
    }

    public function createFileRecon($file_name,$data){
        if($file = fopen($file_name, "w+")) {
            fwrite($file, $data);
            fclose($file);
            //chmod($file_name, 0777);
            return true;
        }
        return false;
    }



    public function findRequestByRef($trx_id){
        $payment_request = new PaymentRequest();
        //$payment_request->setPending();
        //$payment_request->setPaymentCode($this->getPaymentCode());
        $payment_request->setTransactionID($trx_id);
        $requests = $this->getRepository()->findBySearchFilter($payment_request, null, null, null);
        return $requests;

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



    protected function _notifyReconcilationFile($fileDate, $outPath, $okName)
    {
        //get config data
        $configServ = CoreConfigDataServiceFactory::build();
        if( $email = $configServ->getConfig(CoreConfigType::TRANSFERTO_INTERFACE_EMAIL) )
        {
            $email = explode('|', $email);
            if( is_array($email) ) {
                $subject = 'Transfer-to Reconciliation '.$fileDate;

                $okUploader = new ReconFileS3Uploader($outPath, $okName);

                $content = "<p>Transfer-to Reconcilation Interface Files [$fileDate]:</p>
							<p></p>";

                $attachment = new EmailAttachment();

                $fileName = '';
                if ($okUploader->uploadtoS3(NULL)) {
                    $fileName = $okUploader->getFileName();
                    $attachment->add($okUploader->getFileName(), $okUploader->getUrl());
                }
                $content .= "<p>Reconciliation File: [$fileName]</p>";
                $content .= "<p></p><p>Thank You</p>";

                $ics = new CommunicationServiceProducer();
                return $ics->sendEmail(getenv('ICS_PROJECT_ID'), $subject, $content, $content, $email, $attachment);
            }
        }

        return false;
    }


}