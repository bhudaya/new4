<?php

use Iapps\PaymentService\PaymentRequest\IPaymentRequestDataMapper;
use Iapps\PaymentService\PaymentRequest\PaymentRequest;
use Iapps\PaymentService\PaymentRequest\PaymentRequestCollection;
use Iapps\Common\Core\IappsDateTime;
use Iapps\PaymentService\ValueObject\EncryptedFieldFactory;
use Iapps\PaymentService\PaymentRequest\PaymentModeRequestType;
use Iapps\PaymentService\PaymentMode\PaymentModeType;

class Payment_request_model extends Base_Model
                            implements IPaymentRequestDataMapper
{

    public function TransBegin(){
        $this->db->trans_begin();
    }

    public function TransCommit(){
        $this->db->trans_commit();
    }

    public function TransStatus(){
        $this->db->trans_status();
    }

    public function map(stdClass $data)
    {
        $entity = new PaymentRequest();

        if (isset($data->payment_request_id))
            $entity->setId($data->payment_request_id);

        if (isset($data->country_code))
            $entity->setCountryCode($data->country_code);

        if (isset($data->user_profile_id))
            $entity->setUserProfileId($data->user_profile_id);

        if (isset($data->module_code))
            $entity->setModuleCode($data->module_code);

        if (isset($data->transactionID))
            $entity->setTransactionID($data->transactionID);

        if (isset($data->reference_id))
            $entity->setReferenceID($data->reference_id);

        if (isset($data->payment_code))
            $entity->setPaymentCode($data->payment_code);

        if (isset($data->channelID))
            $entity->setChannelID($data->channelID);
        
        if (isset($data->option))
            $entity->getOption()->setJson($data->option);

        if (isset($data->response))
            $entity->getResponse()->setJson($data->response);

        if (isset($data->status))
            $entity->setStatus($data->status);

        if (isset($data->country_currency_code))
            $entity->setCountryCurrencyCode($data->country_currency_code);

        if (isset($data->amount))
            $entity->setAmount($data->amount);

        if (isset($data->first_check_by))
            $entity->setFirstCheckBy($data->first_check_by);

        if (isset($data->first_check_at))
            $entity->setFirstCheckAt(IappsDateTime::fromUnix($data->first_check_at));

        if (isset($data->first_check_remarks))
            $entity->setFirstCheckRemarks($data->first_check_remarks);

        if (isset($data->first_check_status))
            $entity->setFirstCheckStatus($data->first_check_status);

        if( isset($data->payment_mode_request_type_id) )
            $entity->getPaymentModeRequestType()->setId($data->payment_mode_request_type_id);

        if( isset($data->payment_mode_request_type_code) )
            $entity->getPaymentModeRequestType()->setCode($data->payment_mode_request_type_code);

        if( isset($data->payment_mode_request_type_name) )
            $entity->getPaymentModeRequestType()->setDisplayName($data->payment_mode_request_type_name);

        if( isset($data->receipt_reference_image_url) ) {
            $encrypted_code = EncryptedFieldFactory::build();
            $encrypted_code->setEncryptedValue($data->receipt_reference_image_url);
            $entity->setReceiptReferenceImageUrl($encrypted_code->getValue());
        }

        if (isset($data->created_at))
            $entity->setCreatedAt(IappsDateTime::fromUnix($data->created_at));

        if (isset($data->created_by))
            $entity->setCreatedBy($data->created_by);

        if (isset($data->updated_at))
            $entity->setUpdatedAt(IappsDateTime::fromUnix($data->updated_at));

        if (isset($data->updated_by))
            $entity->setUpdatedBy($data->updated_by);

        if (isset($data->deleted_at))
            $entity->setDeletedAt(IappsDateTime::fromUnix($data->deleted_at));

        if (isset($data->deleted_by))
            $entity->setDeletedBy($data->deleted_by);

        return $entity;
    }

    public function findById($id, $deleted = false)
    {
        $this->db->select('pr.id as payment_request_id,
                           pr.country_code,
                           pr.user_profile_id,
                           pr.module_code,
                           pr.transactionID,
                           pr.reference_id,
                           pr.payment_code,
                           pr.channelID,
                           pr.option,
                           pr.response,
                           pr.status,
                           pr.country_currency_code,
                           pr.amount,
                           pr.first_check_by,
                           pr.first_check_at,
                           pr.first_check_remarks,
                           pr.first_check_status,
                           pr.payment_mode_request_type_id,
                           sc.code as payment_mode_request_type_code,
                           sc.display_name as payment_mode_request_type_name,
                           pr.receipt_reference_image_url,
                           pr.created_at,
                           pr.created_by,
                           pr.updated_at,
                           pr.updated_by,
                           pr.deleted_at,
                           pr.deleted_by');
        $this->db->from('iafb_payment.payment_request pr');
        $this->db->join('iafb_payment.system_code sc', 'pr.payment_mode_request_type_id = sc.id', 'LEFT');
        if (!$deleted) {
            $this->db->where('pr.deleted_at', NULL);
            $this->db->where('sc.deleted_at', NULL);
        }
        $this->db->where('pr.id', $id);

        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return $this->map($query->row());
        }

        return false;
    }

    public function findByTransactionID($module_code, $transactionID)
    {
        $this->db->select('pr.id as payment_request_id,
                           pr.country_code,
                           pr.user_profile_id,
                           pr.module_code,
                           pr.transactionID,
                           pr.reference_id,
                           pr.payment_code,
                           pr.option,
                           pr.response,
                           pr.status,
                           pr.country_currency_code,
                           pr.amount,
                           pr.first_check_by,
                           pr.first_check_at,
                           pr.first_check_remarks,
                           pr.first_check_status,
                           pr.payment_mode_request_type_id,
                           sc.code as payment_mode_request_type_code,
                           sc.display_name as payment_mode_request_type_name,
                           pr.receipt_reference_image_url,
                           pr.created_at,
                           pr.created_by,
                           pr.updated_at,
                           pr.updated_by,
                           pr.deleted_at,
                           pr.deleted_by');
        $this->db->from('iafb_payment.payment_request pr');
        $this->db->join('iafb_payment.system_code sc', 'pr.payment_mode_request_type_id = sc.id', 'LEFT');
        $this->db->where('pr.deleted_at', NULL);
        $this->db->where('sc.deleted_at', NULL);
        $this->db->where('pr.module_code', $module_code);
        $this->db->where('pr.transactionID', $transactionID);

        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return $this->mapCollection($query->result(), new PaymentRequestCollection(), $query->num_rows());
        }

        return false;
    }

    public function updateResponse(PaymentRequest $request)
    {
        if($request->getReferenceID())
            $this->db->set('reference_id', $request->getReferenceID());
        if($request->getResponse()->toJson())
            $this->db->set('response', $request->getResponse()->toJson());
        $this->db->set('status', $request->getStatus());
        $this->db->set('updated_at', IappsDateTime::now()->getUnix());
        $this->db->set('updated_by', $request->getUpdatedBy());
        $chkStatus = true;
        if(in_array($request->getPaymentCode(), array(PaymentModeType::BANK_TRANSFER_GPL)) && $request->getStatus()==\Iapps\PaymentService\PaymentRequest\PaymentRequestStatus::PENDING) {
            $chkStatus = false;
        }
        if( in_array($request->getPaymentCode(), array(PaymentModeType::OCBC_CREDIT_CARD)) ) {
            $chkStatus = false;
        }
        if( in_array($request->getPaymentCode(), array(PaymentModeType::BANK_TRANSFER_TMONEY)) ) {
            $chkStatus = false;
        }


        if($chkStatus){
            $this->db->where('status <>', $request->getStatus());
        }
        $this->db->where('id', $request->getId());
        $this->db->update('iafb_payment.payment_request');

        if ($this->db->affected_rows() > 0) {
            return true;
        }

        return false;
    }

    public function updateStatus(PaymentRequest $request)
    {
        $this->db->set('status', $request->getStatus());
        $this->db->set('updated_at', IappsDateTime::now()->getUnix());
        $this->db->set('updated_by', $request->getUpdatedBy());

        $this->db->where('status <>', $request->getStatus());
        $this->db->where('id', $request->getId());
        $this->db->update('iafb_payment.payment_request');

        if ($this->db->affected_rows() > 0) {
            return true;
        }

        return false;
    }


    //first checker approve or reject
    public function updateFirstCheck(PaymentRequest $request)
    {
        $this->db->set('first_check_by', $request->getFirstCheckBy());
        $this->db->set('first_check_at', IappsDateTime::now()->getUnix());
        $this->db->set('first_check_remarks', $request->getFirstCheckRemarks());
        $this->db->set('first_check_status', $request->getFirstCheckStatus());
        $this->db->set('updated_at', IappsDateTime::now()->getUnix());
        $this->db->set('updated_by', $request->getUpdatedBy());

        $this->db->where('id', $request->getId());
        $this->db->where('first_check_status', NULL);
        $this->db->update('iafb_payment.payment_request');

        if ($this->db->affected_rows() > 0) {
            return true;
        }

        return false;
    }

    /*
     * This will update value that is not null
     */
    public function update(PaymentRequest $request)
    {
        $updated_at = IappsDateTime::now();

        //todo add other fields to be updated
        if($request->getUserProfileId())
            $this->db->set('user_profile_id', $request->getUserProfileId());

        $this->db->set('updated_at', $updated_at->getUnix());
        $this->db->set('updated_by', $request->getUpdatedBy());

        $this->db->where('id', $request->getId());
        $this->db->update('iafb_payment.payment_request');

        if ($this->db->affected_rows() > 0)
        {
            $request->setUpdatedAt($updated_at);
            return $request;
        }

        return false;
    }

    public function insert(PaymentRequest $request)
    {
        $this->db->set('id', $request->getId());
        $this->db->set('country_code', $request->getCountryCode());
        $this->db->set('user_profile_id', $request->getUserProfileId());
        $this->db->set('module_code', $request->getModuleCode());
        $this->db->set('transactionID', $request->getTransactionID());
        $this->db->set('reference_id', $request->getReferenceID());
        $this->db->set('payment_code', $request->getPaymentCode());
        $this->db->set('channelID', $request->getChannelID());
        $this->db->set('option', $request->getOption()->toJson());
        $this->db->set('response', $request->getResponse()->toJson());
        $this->db->set('status', $request->getStatus());
        $this->db->set('country_currency_code', $request->getCountryCurrencyCode());
        $this->db->set('amount', $request->getAmount());
        $this->db->set('first_check_by', $request->getFirstCheckBy());
        $this->db->set('first_check_at', $request->getFirstCheckAt()->getUnix());
        $this->db->set('first_check_remarks', $request->getFirstCheckRemarks());
        $this->db->set('first_check_status', $request->getFirstCheckStatus());
        $this->db->set('payment_mode_request_type_id', $request->getPaymentModeRequestType() ? $request->getPaymentModeRequestType()->getId() : null);
        $this->db->set('receipt_reference_image_url', $request->getReceiptReferenceImageUrl() ? $request->getReceiptReferenceImageUrl()->getUrlEncryptedField()->getEncodedValue() : null);
        $this->db->set('created_at', IappsDateTime::now()->getUnix());
        $this->db->set('created_by', $request->getCreatedBy());

        if ($this->db->insert('iafb_payment.payment_request')) {
            return true;
        }

        return false;
    }

    public function findList($module_code = null, $start_timestamp = null, $end_timestamp = null, $orderBy = null)
    {
        $this->db->start_cache(); //to cache active record query
        $this->db->select('pr.id as payment_request_id,
                           pr.country_code,
                           pr.user_profile_id,
                           pr.module_code,
                           pr.transactionID,
                           pr.reference_id,
                           pr.payment_code,
                           pr.option,
                           pr.response,
                           pr.status,
                           pr.country_currency_code,
                           pr.amount,
                           pr.first_check_by,
                           pr.first_check_at,
                           pr.first_check_remarks,
                           pr.first_check_status,
                           pr.payment_mode_request_type_id,
                           sc.code as payment_mode_request_type_code,
                           sc.display_name as payment_mode_request_type_name,
                           pr.receipt_reference_image_url,
                           pr.created_at,
                           pr.created_by,
                           pr.updated_at,
                           pr.updated_by,
                           pr.deleted_at,
                           pr.deleted_by');
        $this->db->from('iafb_payment.payment_request pr');
        $this->db->join('iafb_payment.system_code sc', 'pr.payment_mode_request_type_id = sc.id', 'LEFT');
        $this->db->where('pr.deleted_at', NULL);
        $this->db->where('sc.deleted_at', NULL);
        if (!empty($module_code)) {
            $this->db->where('pr.`module_code`', $module_code);
        }
        if (!empty($start_timestamp)) {
            $this->db->where('pr.`created_at` >= ', $start_timestamp);
        }
        if (!empty($end_timestamp)) {
            $this->db->where('pr.`created_at` <= ', $end_timestamp);
        }
        if (!empty($orderBy)) {
            $this->db->order_by('pr.'.$orderBy, 'ASC');
        }

        $this->db->stop_cache();

        $total = $this->db->count_all_results(); //to get total num of result w/o limit

        $query = $this->db->get();

        $this->db->flush_cache();
        if ($query->num_rows() > 0) {
            return $this->mapCollection($query->result(), new PaymentRequestCollection(), $total);
        }
        return false;
    }


    public function findBySearchFilter(PaymentRequest $request, array $user_profile_id_arr = NULL, $limit, $page)
    {
        $offset = ($page - 1) * $limit;
        $this->db->start_cache(); //to cache active record query
        $this->db->select('pr.id as payment_request_id,
                           pr.country_code,
                           pr.user_profile_id,
                           pr.module_code,
                           pr.transactionID,
                           pr.reference_id,
                           pr.payment_code,
                           pr.option,
                           pr.response,
                           pr.status,
                           pr.country_currency_code,
                           pr.amount,
                           pr.first_check_by,
                           pr.first_check_at,
                           pr.first_check_remarks,
                           pr.first_check_status,
                           pr.payment_mode_request_type_id,
                           sc.code as payment_mode_request_type_code,
                           sc.display_name as payment_mode_request_type_name,
                           pr.receipt_reference_image_url,
                           pr.created_at,
                           pr.created_by,
                           pr.updated_at,
                           pr.updated_by,
                           pr.deleted_at,
                           pr.deleted_by');
        $this->db->from('iafb_payment.payment_request pr');
        $this->db->join('iafb_payment.system_code sc', 'pr.payment_mode_request_type_id = sc.id', 'LEFT');
        $this->db->where('pr.deleted_at', NULL);
        $this->db->where('sc.deleted_at', NULL);

        if ($request->getModuleCode()) {
            $this->db->where('pr.module_code', $request->getModuleCode());
        }
        if ($request->getReferenceID()) {
            $this->db->where('pr.reference_id', $request->getReferenceID());
        }
        if ($request->getPaymentCode()) {
            $this->db->where('pr.payment_code', $request->getPaymentCode());
        }
        if ($request->getStatus()) {
            $this->db->where('pr.status', $request->getStatus());
        }
        if ($request->getFirstCheckStatus()) {
            $this->db->where('pr.first_check_status', $request->getFirstCheckStatus());
        }
        if ($request->getUserProfileId()) {
            $this->db->where('pr.user_profile_id', $request->getUserProfileId());
        }
        if ($request->getCountryCode()) {
            $this->db->where('pr.country_code', $request->getCountryCode());
        }
        if ($request->getTransactionID()) {
            $this->db->where('pr.transactionID', $request->getTransactionID());
        }
        if ($request->getTransactionIDMMDD()) {
            $this->db->where('SUBSTRING(pr.transactionID, 5, 4) = ', $request->getTransactionIDMMDD(), true);
        }
        if ($request->getTransactionIDLast6Digits()) {
            $this->db->where('RIGHT(pr.transactionID, 6) = ', $request->getTransactionIDLast6Digits(), true);
        }
        if ($request->getPaymentModeRequestType()) {
            if ($request->getPaymentModeRequestType()->getId() != NULL) {
                $this->db->where('pr.payment_mode_request_type_id', $request->getPaymentModeRequestType()->getId());
            }
        }
        if($user_profile_id_arr != NULL) {
            $this->db->where_in('pr.user_profile_id', $user_profile_id_arr);
        }

        if( !$this->getFromCreatedAt()->isNull() )
            $this->db->where('pr.created_at >=', $this->getFromCreatedAt()->getUnix());

        if( !$this->getToCreatedAt()->isNull() )
            $this->db->where('pr.created_at <=', $this->getFromCreatedAt()->getUnix());

        $this->db->order_by('pr.created_at', 'desc');
        $this->db->stop_cache();

        $total = $this->db->count_all_results(); //to get total num of result w/o limit
        $this->db->limit($limit, $offset);
        $query = $this->db->get();

        $this->db->flush_cache();

        if ($query->num_rows() > 0) {
            return $this->mapCollection($query->result(), new PaymentRequestCollection(), $total);
        }
        return false;
    }


    public function trxListFindBySearchFilter(PaymentRequest $request, $limit=null, $page=null)
    {
        if($page && $limit) {
            $offset = ($page - 1) * $limit;
        }
        $this->db->start_cache(); //to cache active record query
        $this->db->select('pr.id as payment_request_id,
                           pr.country_code,
                           pr.user_profile_id,
                           pr.module_code,
                           pr.transactionID,
                           pr.reference_id,
                           pr.payment_code,
                           pr.option,
                           pr.response,
                           pr.status,
                           pr.country_currency_code,
                           pr.amount,
                           pr.first_check_by,
                           pr.first_check_at,
                           pr.first_check_remarks,
                           pr.first_check_status,
                           pr.payment_mode_request_type_id,
                           sc.code as payment_mode_request_type_code,
                           sc.display_name as payment_mode_request_type_name,
                           pr.receipt_reference_image_url,
                           pr.created_at,
                           pr.created_by,
                           pr.updated_at,
                           pr.updated_by,
                           pr.deleted_at,
                           pr.deleted_by');
        $this->db->from('iafb_payment.payment_request pr');
        $this->db->join('iafb_payment.system_code sc', 'pr.payment_mode_request_type_id = sc.id', 'LEFT');
        $this->db->where('pr.deleted_at', NULL);
        $this->db->where('sc.deleted_at', NULL);

        if ($request->getModuleCode()) {
            $this->db->where('pr.module_code', $request->getModuleCode());
        }
        if ($request->getReferenceID()) {
            $this->db->like('pr.reference_id', $request->getReferenceID());
        }
        if ($request->getPaymentCode()) {
            $this->db->where('pr.payment_code', $request->getPaymentCode());
        }
        if ($request->getStatus()) {
            $this->db->where('pr.status', $request->getStatus());
        }
        if ($request->getFirstCheckStatus()) {
            $this->db->where('pr.first_check_status', $request->getFirstCheckStatus());
        }
        if ($request->getUserProfileId()) {
            $this->db->where('pr.user_profile_id', $request->getUserProfileId());
        }
        if ($request->getCountryCode()) {
            $this->db->where('pr.country_code', $request->getCountryCode());
        }
        if ($request->getTransactionID()) {
            $this->db->where('pr.transactionID', $request->getTransactionID());
        }
        if ($request->getTransactionIDMMDD()) {
            $this->db->where('SUBSTRING(pr.transactionID, 5, 4) = ', $request->getTransactionIDMMDD(), true);
        }
        if ($request->getTransactionIDLast6Digits()) {
            $this->db->where('RIGHT(pr.transactionID, 6) = ', $request->getTransactionIDLast6Digits(), true);
        }
        if ($request->getPaymentModeRequestType()) {
            if ($request->getPaymentModeRequestType()->getId() != NULL) {
                $this->db->where('pr.payment_mode_request_type_id', $request->getPaymentModeRequestType()->getId());
            }
        }

        if(!$this->getFromCreatedAt()->isNull()){
            $this->db->where('pr.created_at >=', $this->getFromCreatedAt()->getUnix());
        }

        if(!$this->getToCreatedAt()->isNull()){
            $this->db->where('pr.created_at <=', $this->getToCreatedAt()->getUnix());
        }


        $this->db->order_by('pr.created_at', 'desc');
        $this->db->stop_cache();

        $total = $this->db->count_all_results(); //to get total num of result w/o limit
        if($page && $limit) {
            $this->db->limit($limit, $offset);
        }
        $query = $this->db->get();

        $this->db->flush_cache();

        if ($query->num_rows() > 0) {
            return $this->mapCollection($query->result(), new PaymentRequestCollection(), $total);
        }
        return false;
    }

}