<?php

namespace Saseul\Custom\Transaction;

use Saseul\Constant\Decision;
use Saseul\Core\Env;
use Saseul\Custom\Status\Coin;
use Saseul\Custom\Status\Fee;

class Withdraw extends AbstractTransaction
{
    private $withdrawal_amount;
    private $fee;
    private $from_balance;
    private $from_deposit;
    private $coin_fee;

    public function initialize(
        $transaction,
        $thash,
        $public_key,
        $signature
    ): void {
        parent::initialize($transaction, $thash, $public_key, $signature);

        $this->withdrawal_amount = $transaction['amount'] ?? null;
        $this->fee = $transaction['fee'] ?? null;
    }

    public function getValidity(): bool
    {
        return parent::getValidity()
            && $this->isValidWithdrawalAmount()
            && $this->isValidFee();
    }

    public function loadStatus(): void
    {
        Coin::loadBalance($this->from);
        Coin::loadDeposit($this->from);
    }

    public function getStatus(): void
    {
        $this->from_balance = Coin::getBalance($this->from);
        $this->from_deposit = Coin::getDeposit($this->from);
        $this->coin_fee = Fee::GetFee();
    }

    public function makeDecision(): string
    {
        if ((int) $this->withdrawal_amount + (int) $this->fee > (int) $this->from_deposit) {
            return Decision::REJECT;
        }

        return Decision::ACCEPT;
    }

    public function setStatus(): void
    {
        $this->from_deposit = (int) $this->from_deposit - (int) $this->withdrawal_amount;
        $this->from_deposit = (int) $this->from_deposit - (int) $this->fee;
        $this->from_balance = (int) $this->from_balance + (int) $this->withdrawal_amount;
        $this->coin_fee = (int) $this->coin_fee + (int) $this->fee;

        Coin::setBalance($this->from, $this->from_balance);
        Coin::setDeposit($this->from, $this->from_deposit);
        Fee::SetFee($this->coin_fee);
    }

    // TODO: Genesis의 Coin Amount와 비교하는게 맞는지 확인 필요
    private function isValidWithdrawalAmount(): bool
    {
        return is_numeric($this->withdrawal_amount)
            && ((int) $this->withdrawal_amount > 0)
            && ((int) $this->withdrawal_amount <= Env::$genesis['coin_amount']);
    }

    private function isValidFee(): bool
    {
        return is_numeric($this->fee)
            && ((int) $this->fee >= 0);
    }
}
