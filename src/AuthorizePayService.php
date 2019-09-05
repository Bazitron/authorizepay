<?php
namespace Spdevs\AuthorizePay;

use App\Entities\PaymentEntity;
use net\authorize\api\constants\ANetEnvironment;
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

class AuthorizePayService
{
    use AuthorizeStatuses;

    const TRANSACTION_STATUS_AUTHORIZED_PENDING_CAPTURE = 'authorizedPendingCapture';
    const TRANSACTION_STATUS_CAPTURED_PENDING_SETTLEMENT = 'capturedPendingSettlement';
    const TRANSACTION_STATUS_COMMUNICATION_ERROR = 'communicationError';
    const TRANSACTION_STATUS_REFUND_SETTLED_SUCCESSFULLY = 'refundSettledSuccessfully';
    const TRANSACTION_STATUS_REFUND_PENDING_SETTLEMENT = 'refundPendingSettlement';
    const TRANSACTION_STATUS_APPROVED_REVIEW = 'approvedReview';
    const TRANSACTION_STATUS_DECLINED = 'declined';
    const TRANSACTION_STATUS_COULD_NOT_VOID = 'couldNotVoid';
    const TRANSACTION_STATUS_EXPIRED = 'expired';
    const TRANSACTION_STATUS_GENERAL_ERROR = 'generalError';
    const TRANSACTION_STATUS_PENDING_FINAL_SETTLEMENT = 'pendingFinalSettlement';
    const TRANSACTION_STATUS_PENDING_SETTLEMENT = 'pendingSettlement';
    const TRANSACTION_STATUS_FAILED_REVIEW = 'failedReview';
    const TRANSACTION_STATUS_SETTLED_SUCCESSFULLY = 'settledSuccessfully';
    const TRANSACTION_STATUS_SETTLEMENT_ERROR = 'settlementError';
    const TRANSACTION_STATUS_UNDER_REVIEW = 'underReview';
    const TRANSACTION_STATUS_UPDATING_SETTLEMENT = 'updatingSettlement';
    const TRANSACTION_STATUS_VOIDED = 'voided';
    const TRANSACTION_STATUS_FDSPENDING_REVIEW = 'FDSPendingReview';
    const TRANSACTION_STATUS_FDSAUTHORIZED_PENDING_REVIEW = 'FDSAuthorizedPendingReview';
    const TRANSACTION_STATUS_RETURNED_ITEM = 'returnedItem';
    const TRANSACTION_STATUS_CHARGEBACK = 'chargeback';
    const TRANSACTION_STATUS_CHARGEBACK_REVERSAL = 'chargebackReversal';
    const TRANSACTION_STATUS_AUTHORIZED_PENDING_RELEASE = 'authorizedPendingRelease';

    /**
     * @var PaymentEntity
     */
    protected $paymentEntity;

    /**
     * @var AnetAPI\MerchantAuthenticationType
     */
    private $merchantAuthentication;

    /**
     * @var string
     */
    private $endpoint;

    protected $errorCode;
    protected $errorText;
    protected $transId;
    protected $responseCode;
    protected $authCode;
    protected $messageCode;
    protected $messageDescription;
    protected $accountType;

    /**
     * @return mixed
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * @return mixed
     */
    public function getErrorText()
    {
        return $this->errorText;
    }

    /**
     * @return mixed
     */
    public function getTransId()
    {
        return $this->transId;
    }

    /**
     * @return mixed
     */
    public function getResponseCode()
    {
        return $this->responseCode;
    }

    /**
     * @return mixed
     */
    public function getAuthCode()
    {
        return $this->authCode;
    }

    /**
     * @return mixed
     */
    public function getMessageCode()
    {
        return $this->messageCode;
    }

    /**
     * @return mixed
     */
    public function getMessageDescription()
    {
        return $this->messageDescription;
    }

    /**
     * @return mixed
     */
    public function getAccountType()
    {
        return $this->accountType;
    }

    /**
     * @param mixed $accountType
     */
    public function setAccountType($accountType): void
    {
        $this->accountType = $accountType ?: '';
    }

    public function __construct()
    {
        $this->merchantAuthentication = $this->merchantAuthentication();

        if(app()->environment('production')) {
            $this->endpoint = ANetEnvironment::PRODUCTION;
        } else {
            $this->endpoint = ANetEnvironment::SANDBOX;
        }
    }

    /**
     * @return AnetAPI\MerchantAuthenticationType
     */
    private function merchantAuthentication(): AnetAPI\MerchantAuthenticationType
    {
        $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        $merchantAuthentication->setName( config('authorizepay.MERCHANT_LOGIN_ID') );
        $merchantAuthentication->setTransactionKey( config('authorizepay.MERCHANT_TRANSACTION_KEY') );
        return $merchantAuthentication;
    }

    protected function _sanitizeResponse($response): bool
    {
        if (null === $response) {
            $this->_setError(
                trans('authorizepay.ERROR_NO_RESPONSE_RETURNED'),
                'ERROR_NO_RESPONSE_RETURNED'
            );
            return false;
        }

        /** @var AnetAPI\TransactionResponseType $transactionResponse */
        $transactionResponse = $response->getTransactionResponse();

        if ('Ok' != $response->getMessages()->getResultCode()) {
            if ($transactionResponse !== null && $transactionResponse->getErrors() !== null) {
                $this->_setError(
                    $transactionResponse->getErrors()[0]->getErrorText(),
                    $transactionResponse->getErrors()[0]->getErrorCode()
                );
            } else {
                $this->_setError(
                    $response->getMessages()->getMessage()[0]->getText(),
                    $response->getMessages()->getMessage()[0]->getCode()
                );
            }
            return false;
        }

        if ($transactionResponse === null || $transactionResponse->getMessages() === null) {
            if ($transactionResponse->getErrors() !== null) {
                $this->_setError(
                    $transactionResponse->getErrors()[0]->getErrorText(),
                    $transactionResponse->getErrors()[0]->getErrorCode()
                );
            }
            return false;
        }
        $this->transId = $transactionResponse->getTransId();
        $this->responseCode = $transactionResponse->getResponseCode();
        $this->authCode = $transactionResponse->getAuthCode();
        $this->messageCode = $transactionResponse->getMessages()[0]->getCode();
        $this->messageDescription = $transactionResponse->getMessages()[0]->getDescription();
        $this->accountType = $transactionResponse->getAccountType();
        return true;
    }

    protected function _setError($text, $code = null)
    {
        $this->errorText = $text;
        $this->errorCode = $code;
    }

    /**
     * @return AnetAPI\OrderType
     */
    protected function _makeOrderType(): AnetAPI\OrderType
    {
        $order = new AnetAPI\OrderType();
        $order->setInvoiceNumber( $this->paymentEntity->getInvoiceId() );
        $order->setDescription( $this->paymentEntity->getDescription() );

        return $order;
    }

    /**
     * @return AnetAPI\CustomerAddressType
     */
    protected function _makeCustomerAddress(): AnetAPI\CustomerAddressType
    {
        $address = new AnetAPI\CustomerAddressType();
        $address->setFirstName( $this->paymentEntity->getFirstName() );
        $address->setLastName( $this->paymentEntity->getLastName() );
        $address->setAddress( $this->paymentEntity->getBillingAddress() );
        $address->setCity( $this->paymentEntity->getBillingCity() );
        $address->setState( $this->paymentEntity->getBillingState() );
        $address->setZip( sprintf('%05d', (int) $this->paymentEntity->getBillingZip()) );
        $address->setCountry( $this->paymentEntity->getBillingCountry() );
        return $address;
    }

    /**
     * @return AnetAPI\CustomerDataType
     */
    protected function _makeCustomerData(): AnetAPI\CustomerDataType
    {
        $customer = new AnetAPI\CustomerDataType();
        $customer->setType('individual'); //todo: move to config()
        $customer->setId('999999');
        $customer->setEmail( $this->paymentEntity->getEmail() );

        return $customer;
    }

    /**
     * @return AnetAPI\SettingType
     */
    protected function _makeSetting(): AnetAPI\SettingType
    {
        $setting = new AnetAPI\SettingType();
        $setting->setSettingName('duplicateWindow');
        $setting->setSettingValue('60');

        return $setting;
    }

    /**
     * @return AnetAPI\CreditCardType
     */
    protected function _makeCreditCard() : AnetAPI\CreditCardType
    {
        $creditCard = new AnetAPI\CreditCardType();
        $creditCard->setCardNumber($this->paymentEntity->getCardNumber());
        $creditCard->setExpirationDate($this->paymentEntity->getCardExpiration());
        if($this->paymentEntity->getCardCvv()) {
            $creditCard->setCardCode($this->paymentEntity->getCardCvv());
        }

        return $creditCard;
    }

    /**
     * @return AnetAPI\PaymentType
     */
    protected function _makePayment() : AnetAPI\PaymentType
    {
        $payment = new AnetAPI\PaymentType();
        $payment->setCreditCard( $this->_makeCreditCard() );

        return $payment;
    }

    /**
     * @param string $type
     * @return AnetAPI\CreateTransactionRequest
     * @throws \Exception
     */
    protected function _makeTransactionRequest($type): AnetAPI\CreateTransactionRequest
    {
        $request = new AnetAPI\CreateTransactionRequest();
        $request->setMerchantAuthentication($this->merchantAuthentication);
        $request->setRefId('ref' . time() );

        switch ($type) {
            case 'authCaptureTransaction': //todo: move to const
                $transactionRequestType = $this->_makeTransactionRequestTypeCapture($type);
                break;
            case 'refundTransaction':
                $transactionRequestType = $this->_makeTransactionRequestTypeRefund($type);
                break;
            case 'voidTransaction':
                $transactionRequestType = $this->_makeTransactionRequestTypeVoid($type);
                break;
            default:
                throw new \Exception('Undefined transaction type'); //ToDo: move to custom Exception type
        }

        $request->setTransactionRequest($transactionRequestType);

        return $request;
    }

    /**
     * @param $controller
     * @return AnetAPI\AnetApiResponseType
     */
    protected function _execute($controller): AnetAPI\AnetApiResponseType
    {
        return $controller->executeWithApiResponse($this->endpoint);
    }

    /**
     * @param $transactionId
     * @return AnetAPI\GetTransactionDetailsRequest
     */
    protected function _makeTransactionDetailsRequest($transactionId): AnetAPI\GetTransactionDetailsRequest
    {
        $request = new AnetAPI\GetTransactionDetailsRequest();
        $request->setMerchantAuthentication($this->merchantAuthentication);
        $request->setTransId($transactionId);

        return $request;
    }

    /**
     * @param AnetAPI\GetTransactionDetailsRequest $request
     * @return AnetController\GetTransactionDetailsController
     */
    protected function _makeGetTransactionDetailsController(AnetAPI\GetTransactionDetailsRequest $request): AnetController\GetTransactionDetailsController
    {
        return new AnetController\GetTransactionDetailsController($request);
    }

    /**
     * @param AnetAPI\CreateTransactionRequest $request
     * @return AnetController\CreateTransactionController
     */
    protected function _makeCreateTransactionController(AnetAPI\CreateTransactionRequest $request): AnetController\CreateTransactionController
    {
        return new AnetController\CreateTransactionController($request);
    }

    /**
     * Overwrite method
     * @param string $type
     * @return AnetAPI\TransactionRequestType
     */
    protected function _makeTransactionRequestTypeCapture($type): AnetAPI\TransactionRequestType
    {
        $transaction = new AnetAPI\TransactionRequestType();
        $transaction->setTransactionType($type);
        $transaction->setAmount( $this->paymentEntity->getAmount() );
        $transaction->setOrder( $this->_makeOrderType() );
        $transaction->setPayment( $this->_makePayment() );
        $transaction->setBillTo( $this->_makeCustomerAddress() );
        $transaction->setCustomer( $this->_makeCustomerData() );
        $transaction->addToTransactionSettings( $this->_makeSetting() );

        return $transaction;
    }

    /**
     * @param string $type
     * @return AnetAPI\TransactionRequestType
     */
    protected function _makeTransactionRequestTypeVoid($type): AnetAPI\TransactionRequestType
    {
        $transactionRequestType = new AnetAPI\TransactionRequestType();
        $transactionRequestType->setTransactionType($type);
        $transactionRequestType->setRefTransId($this->paymentEntity->getTransactionId());

        return $transactionRequestType;
    }

    /**
     * @param string $type
     * @return AnetAPI\TransactionRequestType
     */
    protected function _makeTransactionRequestTypeRefund($type): AnetAPI\TransactionRequestType
    {
        $transactionRequestType = new AnetAPI\TransactionRequestType();
        $transactionRequestType->setTransactionType($type);
        $transactionRequestType->setRefTransId($this->paymentEntity->getTransactionId());
        $transactionRequestType->setAmount($this->paymentEntity->getAmount());
        $transactionRequestType->setPayment($this->_makePayment());

        return $transactionRequestType;
    }

    /**
     * @param $id
     * @return AnetAPI\TransactionDetailsType|null
     */
    public function getTransaction($id): ?AnetAPI\TransactionDetailsType
    {
        $request = $this->_makeTransactionDetailsRequest($id);
        $controller = $this->_makeGetTransactionDetailsController($request);
        $response = $this->_execute($controller);

        return $response->getTransaction();
    }

    private function _createTransaction(PaymentEntity $paymentEntity, $type): bool
    {
        //ToDo: filter $type

        $this->paymentEntity = $paymentEntity;

        $request = $this->_makeTransactionRequest($type);
        $controller = $this->_makeCreateTransactionController($request);
        $response = $this->_execute($controller);

        return $this->_sanitizeResponse($response);
    }

    /**
     * @param PaymentEntity $paymentEntity
     * @return bool
     */
    public function captureTransaction(PaymentEntity $paymentEntity): bool
    {
        return $this->_createTransaction($paymentEntity, 'authCaptureTransaction');
    }

    /**
     * @param PaymentEntity $paymentEntity
     * @return bool
     */
    public function refundTransaction(PaymentEntity $paymentEntity): bool
    {
        return $this->_createTransaction($paymentEntity, 'refundTransaction');
    }

    /**
     * @param PaymentEntity $paymentEntity
     * @return bool
     */
    public function voidTransaction(PaymentEntity $paymentEntity): bool
    {
        return $this->_createTransaction($paymentEntity, 'voidTransaction');
    }

    /**
     * ToDo: drop it!
     * @param PaymentEntity $paymentEntity
     * @return bool
     * @throws \Exception
     */
    public function refund(PaymentEntity $paymentEntity): bool
    {
        $this->paymentEntity = $paymentEntity;

        $transaction = $this->getTransaction($this->paymentEntity->getTransactionId());
        if(null === $transaction) {
            $this->_setError(
                trans('authorizepay.ERROR_TRANSACTION_NOT_EXIST'),
                'ERROR_TRANSACTION_NOT_EXIST'
            );
            return false;
        }

        if(self::isSettled($transaction->getTransactionStatus())) {
            $request = $this->_makeTransactionRequest('refundTransaction'); //todo: move to const
        } else {
            $request = $this->_makeTransactionRequest('voidTransaction');
        }

        $controller = $this->_makeCreateTransactionController($request);
        $response = $this->_execute($controller);

        return $this->_sanitizeResponse($response);
    }
}
