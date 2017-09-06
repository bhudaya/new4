<?php

use Iapps\PaymentService\PaymentBatch\BankTransferRequestInitiatedNotificationService;
use Iapps\Common\Core\IpAddress;
use Iapps\Common\Helper\ResponseHeader;
use Iapps\PaymentService\PaymentRequest\PaymentRequestServiceFactory;
use Iapps\PaymentService\Common\MessageCode;
use Iapps\Common\Helper\RequestHeader;
use Iapps\PaymentService\PaymentRequest\TMoneyInquireTransactionStatusService;
use Iapps\PaymentService\PaymentRequest\TMoneyRetryTransactionService;

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

}