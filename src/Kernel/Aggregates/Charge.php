<?php

namespace Mundipagg\Core\Kernel\Aggregates;

use Mundipagg\Core\Kernel\Abstractions\AbstractEntity;
use Mundipagg\Core\Kernel\Exceptions\InvalidOperationException;
use Mundipagg\Core\Kernel\Exceptions\InvalidParamException;
use Mundipagg\Core\Kernel\ValueObjects\ChargeStatus;
use Mundipagg\Core\Kernel\ValueObjects\Id\OrderId;

final class Charge extends AbstractEntity
{
    /** @var OrderId */
    private $orderId;
    /**
     *
     * @var int 
     */
    private $amount;
    /**
     *
     * @var int 
     */
    private $paidAmount;
    /**
     * Holds the amount that will not be captured in any away.
     *
     * @var int
     */
    private $canceledAmount;
    /**
     * Holds the amount that was once captured but then returned to the client.
     *
     * @var int
     */
    private $refundedAmount;

    /**
     *
     * @var string 
     */
    private $code;
    /**
     *
     * @var ChargeStatus 
     */
    private $status;

    /** @var Transaction[] */
    private $transactions;

    /**
     * @return OrderId
     */
    public function getOrderId()
    {
        return $this->orderId;
    }

    /**
     * @param OrderId $orderId
     * @return Charge
     */
    public function setOrderId(OrderId $orderId)
    {
        $this->orderId = $orderId;
        return $this;
    }

    /**
     * @param int $amount
     */
    public function pay($amount)
    {
        if ($this->status->equals(ChargeStatus::paid())) {
            throw new InvalidOperationException(
                'You can\'t pay a charge that was payed already!'
            );
        }
        if (!$this->status->equals(ChargeStatus::pending())) {
            throw new InvalidOperationException(
                'You can\'t pay a charge that isn\'t pending!'
            );
        }

        $this->setPaidAmount($amount);

        $amountToCancel = $this->amount - $this->getPaidAmount();
        $this->setCanceledAmount($amountToCancel);

        $this->status = ChargeStatus::paid();
    }

    /**
     * @param int $amount
     */
    public function cancel($amount)
    {
        if ($this->status->equals(ChargeStatus::paid())) {
            $this->setRefundedAmount($amount);
            return;
        }

        //if the charge wasn't payed yet the charge should be canceled.
        $this->setCanceledAmount($this->amount);
        $this->status = ChargeStatus::canceled();
    }

    /**
     *
     * @return int
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     *
     * @param  int $amount
     * @return Charge
     */
    public function setAmount(int $amount)
    {
        if ($amount < 0) {
            throw new InvalidParamException("Amount should be greater or equal to 0!", $amount);
        }
        $this->amount = $amount;
        return $this;
    }

    /**
     *
     * @return int
     */
    public function getPaidAmount()
    {
        if ($this->paidAmount === null) {
            return 0;
        }

        return $this->paidAmount;
    }

    /**
     *
     * @param  int $paidAmount
     * @return Charge
     */
    public function setPaidAmount(int $paidAmount)
    {
        if ($paidAmount < 0) {
            $paidAmount = 0;
        }
        $this->paidAmount = $paidAmount;
        return $this;
    }

    /**
     * @return int
     */
    public function getCanceledAmount()
    {
        if ($this->canceledAmount === null) {
            return 0;
        }

        return $this->canceledAmount;
    }

    /**
     * @param int $canceledAmount
     * @return Charge
     */
    public function setCanceledAmount(int $canceledAmount)
    {
        if ($canceledAmount < 0) {
            $canceledAmount = 0;
        }

        if ($canceledAmount > $this->amount) {
            $canceledAmount = $this->amount;
        }

        $this->canceledAmount = $canceledAmount;
        return $this;
    }

    /**
     * @return int
     */
    public function getRefundedAmount()
    {
        if ($this->refundedAmount === null) {
            return 0;
        }

        return $this->refundedAmount;
    }

    /**
     * @param int $refundedAmount
     * @return Charge
     */
    public function setRefundedAmount(int $refundedAmount)
    {
        if ($refundedAmount < 0) {
            $refundedAmount = 0;
        }

        if ($refundedAmount > $this->paidAmount) {
            $refundedAmount = $this->paidAmount;
        }

        $this->refundedAmount = $refundedAmount;
        return $this;
    }

    /**
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     *
     * @param  string $code
     * @return Charge
     */
    public function setCode($code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     *
     * @return ChargeStatus
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     *
     * @param  ChargeStatus $status
     * @return Charge
     */
    public function setStatus(ChargeStatus $status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return null|Transaction
     */
    public function getLastTransaction()
    {
        $transactions = $this->getTransactions();
        if (count($transactions) === 0) {
            return null;
        }

        $newest = $transactions[0];

        foreach ($transactions as $transaction) {
            if (
                $newest->getCreatedAt()->getTimestamp() <
                $transaction->getCreatedAt()->getTimestamp()
            ) {
                $newest = $transaction;
            }
        }

        return $newest;
    }

    /**
     * @param Transaction $newTransaction
     * @return Charge
     */
    public function addTransaction(Transaction $newTransaction)
    {
        $transactions = $this->getTransactions();
        //cant add a transaction that was already added.
        foreach ($transactions as $transaction) {
            if (
                $transaction->getMundipaggId()->equals(
                    $newTransaction->getMundipaggId()
                )
            ) {
                return $this;
            }
        }

        $transactions[] = $newTransaction;
        $this->transactions = $transactions;

        return $this;
    }

    /**
     * @return Transaction[]
     */
    public function getTransactions()
    {
        if (!is_array($this->transactions)) {
            return [];
        }
        return $this->transactions;
    }

    public function updateTransaction(Transaction $updatedTransaction, $overwriteId = false)
    {
        $transactions = $this->getTransactions();

        foreach ($transactions as &$transaction) {
            if ($transaction->getMundipaggId()->equals($updatedTransaction->getMundipaggId()))
            {
                $transactionId = $transaction->getId();
                $transaction = $updatedTransaction;
                if ($overwriteId) {
                    $transaction->setId($transactionId);
                }
                $this->transactions = $transactions;
                return;
            }
        }

        $this->addTransaction($updatedTransaction);
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @link   https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since  5.4.0
     */
    public function jsonSerialize()
    {
        $obj = new \stdClass();

        $obj->id = $this->getId();
        $obj->mundipaggId = $this->getMundipaggId();
        $obj->orderId = $this->getOrderId();
        $obj->amount = $this->getAmount();
        $obj->paidAmount = $this->getPaidAmount();
        $obj->canceledAmount = $this->getCanceledAmount();
        $obj->refundedAmount = $this->getRefundedAmount();
        $obj->code = $this->getCode();
        $obj->status = $this->getStatus();
        //$obj->lastTransaction = $this->getLastTransaction();

        return $obj;
    }
}