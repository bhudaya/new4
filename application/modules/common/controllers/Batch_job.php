<?php

use Iapps\PaymentService\PaymentBatch\BankTransferRequestInitiatedNotificationService;
use Iapps\Common\Core\IpAddress;
use Iapps\Common\Core\IappsDateTime;
use Iapps\Common\Helper\ResponseHeader;
use Iapps\PaymentService\PaymentRequest\PaymentRequestServiceFactory;
use Iapps\PaymentService\Common\MessageCode;
use Iapps\Common\Helper\RequestHeader;
use Iapps\PaymentService\PaymentRequest\TMoneyInquireTransactionStatusService;
use Iapps\PaymentService\PaymentRequest\TMoneyRetryTransactionService;
use Iapps\PaymentService\PaymentRequest\TransfertoRetryTransactionService;
use Iapps\PaymentService\PaymentRequest\TransfertoCp2RetryTransactionService;
use Iapps\PaymentService\PaymentRequest\TransfertoReconTransactionService;

class Batch_job extends System_Base_Controller{

    function __construct()
    {
        parent::__construct();

        $this->load->model('paymentrequest/Payment_request_model');
    }

    public function listenNotifyBankTransferRequestInitiatedQueue()
    {
        if( !$system_user_id = $this->_getUserProfileId() )
            return false;

        RequestHeader::set(ResponseHeader::FIELD_X_AUTHORIZATION, $this->clientToken);

        $listener = new BankTransferRequestInitiatedNotificationService();
        $listener->setIpAddress(IpAddress::fromString($this->_getIpAddress()));
        $listener->setUpdatedBy($system_user_id);
        $listener->listenEvent();

        $this->_respondWithSuccessCode(MessageCode::CODE_JOB_PROCESS_PASSED);
        return true;
    }

    public function retryTMoneyTransaction()
    {
        if (!$system_user_id = $this->_getUserProfileId())
            return false;

            RequestHeader::set(ResponseHeader::FIELD_X_AUTHORIZATION, $this->clientToken);

        $tmoney_retry_serv = new TMoneyRetryTransactionService();
        $tmoney_retry_serv->setUpdatedBy($system_user_id);
        $tmoney_retry_serv->setIpAddress(IpAddress::fromString($this->_getIpAddress()));

        $tmoney_retry_serv->process();
        $this->_respondWithSuccessCode(MessageCode::CODE_JOB_PROCESS_PASSED);
        return true;
    }

    public function inquireTMoneyTransactionStatus()
    {
            if (!$system_user_id = $this->_getUserProfileId())
                    return false;

            RequestHeader::set(ResponseHeader::FIELD_X_AUTHORIZATION, $this->clientToken);

        $tmoney_inquiry_serv = new TMoneyInquireTransactionStatusService();
        $tmoney_inquiry_serv->setUpdatedBy($system_user_id);
        $tmoney_inquiry_serv->setIpAddress(IpAddress::fromString($this->_getIpAddress()));

        $tmoney_inquiry_serv->process();
        $this->_respondWithSuccessCode(MessageCode::CODE_JOB_PROCESS_PASSED);
        return true;
    }


    public function reconTransfertoTransaction()
    {
        if (!$system_user_id = $this->_getUserProfileId())
            return false;


        if($this->input->get('recon_date')){
            $recon_date= $this->input->get('recon_date');
        }else{
            $recon_date = Date("Y-m-d");
            $trx_date = date('Y-m-d', strtotime('-1 days', strtotime($recon_date)));

        }

        //$trx_date= $this->input->get('trx_date') ? IappsDateTime::fromString($this->input->get('trx_date')) : (new IappsDateTime("Y-m-d"));
        RequestHeader::set(ResponseHeader::FIELD_X_AUTHORIZATION, $this->clientToken);

        $recon_serv = new TransfertoReconTransactionService();
        $recon_serv->setUpdatedBy($system_user_id);
        $recon_serv->setIpAddress(IpAddress::fromString($this->_getIpAddress()));

        $recon_serv->process($recon_date);
        $this->_respondWithSuccessCode(MessageCode::CODE_JOB_PROCESS_PASSED);
        return true;
    }

}