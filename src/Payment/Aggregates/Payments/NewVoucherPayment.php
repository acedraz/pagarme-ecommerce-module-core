<?php

namespace Mundipagg\Core\Payment\Aggregates\Payments;

use MundiAPILib\Models\CreateCreditCardPaymentRequest;
use Mundipagg\Core\Kernel\Abstractions\AbstractModuleCoreSetup as MPSetup;
use Mundipagg\Core\Kernel\Exceptions\InvalidParamException;
use Mundipagg\Core\Payment\ValueObjects\AbstractCardIdentifier;
use Mundipagg\Core\Payment\ValueObjects\CardToken;
use Mundipagg\Core\Payment\ValueObjects\PaymentMethod;

final class NewVoucherPayment extends AbstractCreditCardPayment
{
    /** @var bool */
    private $saveOnSuccess;

    public function __construct()
    {
        $this->saveOnSuccess = false;
        parent::__construct();
    }

    /**
     * @return bool
     */
    public function isSaveOnSuccess()
    {
        $order = $this->getOrder();
        if ($order === null) {
            return false;
        }

        if (!MPSetup::getModuleConfiguration()->getVoucherConfig()->isSaveCards()) {
            return false;
        }

        $customer = $this->getCustomer();

        if ($customer === null) {
            return false;
        }

        return $this->saveOnSuccess;
    }

    /**
     * @param bool $saveOnSuccess
     */
    public function setSaveOnSuccess($saveOnSuccess)
    {
        $this->saveOnSuccess = boolval($saveOnSuccess);
    }

    public function jsonSerialize()
    {
        $obj = parent::jsonSerialize();

        $obj->cardToken = $this->identifier;

        return $obj;
    }

    public function setIdentifier(AbstractCardIdentifier $identifier)
    {
        $this->identifier = $identifier;
    }

    public function setCardToken(CardToken $cardToken)
    {
        $this->setIdentifier($cardToken);
    }

    /**
     * @return CreateCreditCardPaymentRequest
     */
    protected function convertToPrimitivePaymentRequest()
    {
        $paymentRequest = parent::convertToPrimitivePaymentRequest();

        $paymentRequest->cardToken = $this->getIdentifier()->getValue();

        return $paymentRequest;
    }

    static public function getBaseCode()
    {
        return PaymentMethod::voucher()->getMethod();
    }

    protected function getMetadata()
    {
        $newCardMetadata = new \stdClass;

        $newCardMetadata->saveOnSuccess = $this->isSaveOnSuccess();

        return $newCardMetadata;
    }

    /**
     * @param int $installments
     */
    public function setInstallments(int $installments)
    {
        if ($installments < 1) {
            throw new InvalidParamException(
                "Installments should be at least 1",
                $installments
            );
        }

        $this->installments = $installments;
    }
}