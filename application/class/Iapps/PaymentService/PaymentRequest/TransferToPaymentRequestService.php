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


        $transferto_switch_client->setResponseFields($request->getResponse()->toArray());

        $response = $transferto_switch_client->bankTransfer() ;
        $request->getResponse()->setJson(json_encode(array("Transferto Bank Transfer"=>$transferto_switch_client->getTransactionType())));


        if($response )
        {

            $result = $this->_checkResponse($request, $response);
            $request->getResponse()->add('transferto_response', $response->getFormattedResponse());
            $request->getResponse()->add('transferto_process', $transferto_switch_client->getTransfertoInfo());

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
                                $request->getResponse()->setJson(json_encode(array("TMoney Bank Transfer" => $transferto_switch_client->getTransactionType())));
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
        $notfound = false;
        foreach ($lines as $line_num => $line) {
            $clm = explode(',',$line);
            if ($j > 0) {
                $transactionIDTt = trim(substr($clm[4], 0, 20));
                echo"trx db :";print_r($trx_id); echo "|";
                echo"trx TT :";print_r($transactionIDTt);echo"|";
                if ($transactionIDTt == trim($trx_id)) {
                    $notfound=false;
                    //break;
                }else{
                    $notfound = true;
                }
            }
            $j++;
        }

        return $notfound;
    }

    public function reconDownload($trx_date){

        $CI =& get_instance();
        $CI->load->library('zip');
        //$CI->zip->add_data($prefs['filename'], $this->_backup($prefs));
        //return $CI->zip->get_zip();
        $filename ="../files/transferto-recon/transferto-success11-". $trx_date .".csv";
        echo $filename;
        //$files = array($filename);
        # create new zip opbject
        //$zip = new CI_Zip();
        $CI->zip->add_data("../files/transferto-recon/recon.zip",$filename);
        //$CI->zip->archive('../files/transferto-recon/recon.zip');
        $CI->zip->archive('recon.zip');
        //$CI->zip->download('recon.zip');

        # send the file to the browser as a download
        header('Content-disposition: attachment; filename=recon.zip');
        header('Content-type: application/zip');
    }


    public function reconTransaction($trx_date){

        $transferto_file = "../files/transferto-recon/iApps-". $trx_date .".csv";

        if(!file_exists($transferto_file)) {
            return false;
        }   

        //------------------------------ create success and suspect file -----
        // based on transferto report file
        $lines = file($transferto_file);
        $j=0;
        $header="creation_date,operation_date,transaction_status,t2_id,ext_id,sender_country,sender_name,recipient_country,recipient_name  ,recipient_msisdn,received_amount,received_amount_currency,settlement_amount_sgd,payout_rate_sgd,t2_commission_sgd,total_value_sgd,iapps_status\n";
        $dataSuccess11="";
        $dataSuccess12="";
        $dataSuccess10="";
        $dataFailed21="";
        $dataFailed22="";
        $dataFailed20="";

        $dataOk="";
        $dataSuspect="";
        $dataForced ="";
        foreach ($lines as $line_num => $line) {
            $statusdb="";
            $line = str_replace("\n","",$line);
            $line = str_replace("\r","",$line);

            if($j == 0){
                $dataSuccess11 = $header;
                $dataSuccess12 = $header;
                $dataSuccess10 = $header;
                $dataFailed21 =  $header;
                $dataFailed22 =  $header;
                $dataFailed20 =  $header;
                $dataOk=$header;
                $dataSuspect=$header;
                $dataForced =$header;
            }else{
                $clm = explode(',',$line);
                $status = $clm[2];
                $transactionID = trim(substr($clm[4],0,20)) ;

                $requests = $this->findRequestByRef($transactionID);
                if($requests) {
                    foreach ($requests->result as $req) {
                        if ($req instanceof PaymentRequest) {
                            $statusdb = $req->getStatus();
                        }
                    }
                }

                if(trim($status) == "COMPLETED"){
                    if(trim($statusdb) == "success") {
                        $dataSuccess11 = $dataSuccess11 . $line . "," .$statusdb . "\n" ;   //transferto success  - iapps success
                        $dataOk = $dataOk . $line . "," .$statusdb . "\n" ;   //transferto success  - iapps success

                    }
                    if(trim($statusdb) == "fail") {
                        $dataSuccess12 = $dataSuccess12 . $line . "," .$statusdb . "\n";  //success - fail
                        $dataSuspect = $dataSuspect . $line . "," .$statusdb . "\n";  //success - fail   (low suspect)

                    }
                    if(trim($statusdb) == "") {
                        $dataSuccess10 = $dataSuccess10 . $line . "," . $statusdb . "\n";  //success - not found
                        $dataSuspect = $dataSuspect . $line . "," .$statusdb . "\n";  //success - not found   (high suspect)

                    }

                }else{
                    if(trim($statusdb) == "success") {
                        $dataFailed21 = $dataFailed21 . $line . "," .$statusdb . "\n";   //fail - success
                        $dataForced = $dataForced . $line . "," .$statusdb . "\n";   //fail - success    high forced

                    }
                    if(trim($statusdb) == "fail") {
                        $dataFailed22 = $dataFailed22 . $line . "," .$statusdb . "\n";   //fail - fail
                        $dataOk = $dataOk . $line . "," .$statusdb . "\n";  //fail - fail
                    }
                    if(trim($statusdb) == "") {
                        $dataFailed20 = $dataFailed20 . $line . "," . $statusdb . "\n";  //fail - not found
                        $dataSuspect = $dataSuspect . $line . "," .$statusdb . "\n";   //fail - not found   (high suspect)

                    }
                }
            }
            $j++;
        }

        /*
        $filename ="../files/transferto-recon/transferto-success11-". $trx_date .".csv";
        $this->createFileRecon($filename,$dataSuccess11);
        $filename ="../files/transferto-recon/transferto-success12-". $trx_date .".csv";
        $this->createFileRecon($filename,$dataSuccess12);
        $filename ="../files/transferto-recon/transferto-success10-". $trx_date .".csv";
        $this->createFileRecon($filename,$dataSuccess10);
        $filename ="../files/transferto-recon/transferto-failed21-". $trx_date .".csv";
        $this->createFileRecon($filename,$dataFailed21);
        $filename ="../files/transferto-recon/transferto-failed22-". $trx_date .".csv";
        $this->createFileRecon($filename,$dataFailed22);
        $filename ="../files/transferto-recon/transferto-failed20-". $trx_date .".csv";
        $this->createFileRecon($filename,$dataFailed20);
        */

        $filename ="../files/transferto-recon/transferto-ok-". $trx_date .".csv";
        $this->createFileRecon($filename,$dataOk);

        $filename ="../files/transferto-recon/transferto-suspect-". $trx_date .".csv";
        $this->createFileRecon($filename,$dataSuspect);


        //------------------------------ create forced file   based on iapps database -----        
        if ($requests = $this->findPendingRequestByDate($trx_date)){

            $i=0;
            $dataNotFound01="";
            $dataNotFound02="";
            foreach ($requests->result as $req) {
                if ($req instanceof PaymentRequest) {
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
                    $notfound =$this->findTrxInReportFile($transferto_file,$req->getTransactionID());
                    if($notfound){
                        if ($req->getStatus() == "success"){
                            $dataNotFound01 = $dataNotFound01 . $data . "\n";   //transferto not found  - iapps success
                            $dataForced = $dataForced . $data . "\n";   // transferto not found -  iapps success   high forced
                        }else{
                            $dataNotFound02 = $dataNotFound02 . $data . "\n";   //transferto not found  - iapps failed  repayment again
                        }
                    }

                    /*
                    $last_response = $req->getResponse()->toArray() ;
                    if(array_key_exists("transferto_response",$last_response)) {
                        $transferto_response = $last_response["transferto_response"];
                        $transferto_process = $last_response["transferto_process"];
                        $transferto_response_arr = json_decode($transferto_response, true);
                        $transferto_process_arr = json_decode($transferto_process, true);

                        if (array_key_exists("status", $transferto_response_arr)) {
                            //$data .= $transferto_response_arr["status"] . ";";
                            $data .= $transferto_response_arr["status_message"] . "\n";
                        }
                    }*/

                }
                $i++;
            }
            /*
            $dataNotFound01 = $header . $dataNotFound01;
            $dataNotFound02 = $header . $dataNotFound02;
            $filename ="../files/transferto-recon/transferto-notfound01-". $trx_date .".csv";
            $this->createFileRecon($filename,$dataNotFound01);
            $filename ="../files/transferto-recon/transferto-notfound02-". $trx_date .".csv";
            $this->createFileRecon($filename,$dataNotFound02);
            */

            $filename ="../files/transferto-recon/transferto-forced-". $trx_date .".csv";
            $this->createFileRecon($filename,$dataForced);

        }
        return true;
    }


    public function createFileRecon($file_name,$data){
        if($file = fopen($file_name, "a")) {
            fwrite($file, $data);
            fclose($file);
            chmod($file_name, 0777);
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


}