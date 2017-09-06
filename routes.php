<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	http://codeigniter.com/user_guide/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller'] = 'welcome';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

//user registration
$route['currency/list'] = 'currency/Currency/getAllCurrencies';
$route['currency/get'] = 'currency/Currency/getCurrencyInfo';
$route['currency/add'] = 'currency/Currency/addCurrency';
$route['currency/edit'] = 'currency/Currency/editCurrency';

$route['currency/search'] = 'currency/Currency/getCurrencyInfoByCodeOrName';

$route['payment_mode/list'] = 'paymentmode/Payment_mode/getAllPaymentModes';
$route['payment_mode/get'] = 'paymentmode/Payment_mode/getPaymentModeInfo';
$route['payment_mode/supported'] = 'paymentmode/Payment_mode/getSupportedPaymentMode';
$route['payment_mode/agent/supported'] = 'paymentmode/Payment_mode/getAgentSupportedPaymentMode';
$route['payment_mode/list/refund'] = 'paymentmode/Payment_mode/getPaymentModesForRefund';

$route['country/currency/list'] = 'countrycurrency/Country_currency/getAllCountryCurrencies';
$route['country/currency/get'] = 'countrycurrency/Country_currency/getCountryCurrencyInfo';
$route['country/currency/add'] = 'countrycurrency/Country_currency/addCountryCurrency';
$route['country/currency/payment_mode/add'] = 'countrycurrency/Country_currency/addCountryCurrencyWithPaymentMode';
$route['country/currency/payment_mode/get'] = 'countrycurrency/Country_currency/getCurrencyInfoWithPaymentModeByCountryCode';

$route['country/currency/payment_mode/list'] = 'countrycurrencypaymentmode/Country_currency_payment_mode/getAllCountryCurrencyPaymentModes';

$route['country/currency/getbycountrycode'] = 'countrycurrency/Country_currency/getAllCountryCurrenciesByFromCountryCode';
//$route['country/currency/payment_mode/get'] = 'countrycurrencypaymentmode/Country_currency_payment_mode/getCountryCurrencyPaymentModeInfoByCountryCode';
//$route['country/currency/payment_mode/add'] = 'countrycurrencypaymentmode/Country_currency_payment_mode/addCountryCurrencyPaymentMode';
//$route['country/currency/payment_mode/add'] = 'countrycurrencypaymentmode/Country_currency_payment_mode/addCountryCurrencyPaymentModeBatch';

/*
 * Agent Payment APIs
 */
$route['agent/payment/request'] = 'payment/Agent_payment/request';
$route['agent/payment/complete'] = 'payment/Agent_payment/complete';
$route['agent/payment/cancel'] = 'payment/Agent_payment/cancel';
$route['agent/payment/make'] = 'payment/Agent_payment/makePayment';
$route['agent/payment/mode/attribute/get'] = 'paymentmodeattribute/Payment_mode_attribute_agent/getPaymentModeAttribute';
$route['agent/payment/mode/attribute/all/get'] = 'paymentmodeattribute/Payment_mode_attribute_agent/getAllByPaymentCode';

$route['agent/self/payment/request'] = 'payment/Agent_self_payment/request';
$route['agent/self/payment/cancel'] = 'payment/Agent_self_payment/cancel';
//$route['agent/self/payment/complete'] = 'payment/Agent_self_payment/complete';

$route['agent/self/payment/receipt/reference/upload'] = 'payment/Agent_self_payment/uploadReceiptRefereceImage';
$route['agent/self/payment/request/list'] = 'paymentrequest/Agent_payment_request/getPaymentRequestList';

$route['payment/mode/attribute/get'] = 'paymentmodeattribute/Payment_mode_attribute/getPaymentModeAttribute';


$route['payment/mode/attribute/bankname/list'] = 'paymentmodeattribute/Payment_mode_attribute/getAllBankNames';

$route['agent/payment/checkaccount'] = 'payment/Agent_payment/checkAccount';



/*
 * User Payment APIs
 */
$route['user/payment/request'] = 'payment/User_payment/request';
$route['user/payment/complete'] = 'payment/User_payment/complete';
$route['user/payment/cancel'] = 'payment/User_payment/cancel';
$route['user/payment/make'] = 'payment/User_payment/makePayment';
$route['user/payment/getbyuserid'] = 'payment/User_payment/getPaymentByUserId';
$route['user/payment/mode/attribute/get'] = 'paymentmodeattribute/Payment_mode_attribute_user/getPaymentModeAttribute';
$route['user/payment/mode/attribute/all/get'] = 'paymentmodeattribute/Payment_mode_attribute_user/getAllByPaymentCode';

$route['user/collection/option/list'] = 'paymentmodeattribute/Payment_mode_attribute_user/getCollectionOption';

$route['user/self/payment/request'] = 'payment/User_self_payment/request';
$route['user/self/payment/cancel'] = 'payment/User_self_payment/cancel';
$route['user/self/payment/receipt/reference/upload'] = 'payment/User_self_payment/uploadReceiptRefereceImage';
$route['user/self/payment/request/list'] = 'paymentrequest/User_payment_request/getPaymentRequestList';

$route['payment/get/getbysearchfilter'] = 'paymentrequest/Payment_request/getPaymentBySearchFilter';
$route['payment/get/transactionid'] = 'payment/Payment/getPaymentByTransactionID';
$route['payment_request/get/transactionid'] = 'paymentrequest/Payment_request/getPaymentRequestByTransactionID';

$route['agent/payment/getbycreator'] = 'payment/Agent_payment/getPaymentByCreator';
$route['payment/get/transactionid/array'] = 'payment/Payment/getPaymentByTransactionIDArr';
$route['agent/payment/getbycreatorbydate'] = 'payment/Agent_payment/getPaymentByCreatorByDate';

$route['agent/payment/listbycreator'] = 'payment/Agent_payment/getAgentPaymentByCreatorArrAndSearchFilter';

$route['admin/payment/listbycreator'] = 'payment/Admin_payment/getPaymentByCreatorArrAndSearchFilter';
$route['admin/user/payment/list'] = 'payment/Admin_payment/getUserPaymentList';

$route['user/payment/checkaccount'] = 'payment/User_payment/checkAccount';




/*
 * Admin Payment APIs
 */
$route['admin/payment/request'] = 'payment/Admin_payment/request';
$route['admin/payment/complete'] = 'payment/Admin_payment/complete';
$route['admin/payment/cancel'] = 'payment/Admin_payment/cancel';
$route['admin/payment/void'] = 'payment/Admin_payment/void';
$route['admin/payment/make'] = 'payment/Admin_payment/makePayment';

$route['admin/payment/request/firstcheck/list'] = 'paymentrequest/Admin_payment_request/getPaymentRequestListForFirstChecker';
$route['admin/payment/request/firstcheck/update'] = 'paymentrequest/Admin_payment_request/updatePaymentRequestForFirstCheckerResult';

/*
 * Partner Payment APIs
 */
$route['partner/payment/request'] = 'payment/Partner_payment/request';
$route['partner/payment/complete'] = 'payment/Partner_payment/complete';
$route['partner/payment/cancel'] = 'payment/Partner_payment/cancel';
$route['partner/payment/make'] = 'payment/Partner_payment/makePayment';

$route['partner/payment/listbycreator'] = 'payment/Partner_payment/getPaymentByCreatorArrAndSearchFilter';
/*
 * System Payment APIs
 */
$route['system/payment/request'] = 'payment/Payment/request';
$route['system/payment/complete'] = 'payment/Payment/complete';
$route['system/payment/cancel'] = 'payment/Payment/cancel';
$route['system/payment/collectioninfo/validate'] = 'payment/Payment/validateCollectionInfo';

$route['system/payment/convert/user'] = 'payment/Payment/convertUser';
/*
 * AUDIT LOG
 */
$route['log/listen'] = 'common/Audit_log/listenLogEvent';

/*
 * AUDIT TRAILS
 */
$route['currency/log'] = 'currency/Currency/getAuditLog';
$route['countrycurrency/log'] = 'countrycurrency/Country_currency/getAuditLog';
$route['countrycurrencypaymentmode/log'] = 'countrycurrencypaymentmode/Country_currency_payment_mode/getAuditLog';
$route['payment/log'] = 'payment/Payment/getAuditLog';
$route['paymentmode/log'] = 'paymentmode/Payment_mode/getAuditLog';
$route['paymentmodeattribute/log'] = 'paymentmodeattribute/Payment_mode_attribute/getAuditLog';
$route['paymentrequest/log'] = 'paymentrequest/Payment_request/getAuditLog';

/*
 * Batch Job
 */
$route['job/event/notification/banktransfer/initiated'] = 'common/Batch_job/listenNotifyBankTransferRequestInitiatedQueue';
$route['job/paymentrequest/tmoney/inquire'] = 'common/Batch_job/inquireTMoneyTransactionStatus';
$route['job/paymentrequest/tmoney/retry'] = 'common/Batch_job/retryTMoneyTransaction';


/*
*
* Promo Code
*/
$route['promocode/promoreward/add'] = 'promocode/Promo_reward/addPromoReward';
$route['promocode/promoreward/list'] = 'promocode/Promo_reward/getAllPromoReward';
$route['promocode/promoreward/getbypromocode'] = 'promocode/Promo_reward/getPromoRewardDetailByPromoCode';
$route['promocode/userpromoreward/getbyuserid'] = 'promocode/User_promo_reward/getUserPromoRewardByUserProfileId';


$route['promocode/userpromoreward/update'] = 'promocode/User_promo_reward/updateUserPromoReward'; // other service call
$route['promocode/userpromoreward/getbyuseridpromocode'] = 'promocode/User_promo_reward/getUserPromoRewardByUserProfileIdAndPromoCode'; // other service call

/*
 * agung transaction report
 */
$route['report/agungtransaction'] = 'agungreport/Agung_transaction_report/getAgungTransactionReport';
$route['payment/get/customerreport'] = 'payment/Payment/getPaymentByPaymentCodeAndDate';
$route['payment/get/bypaymentrequestid'] = 'paymentrequest/Payment_request/getPaymentRequestByPaymentRequestID';






