<?php

/**
 *  Copyright (c) 2022 cooldogedev
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is
 *  furnished to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 *  SOFTWARE.
 */

declare(strict_types=1);

namespace cooldogedev\BedrockEconomy\query\mysql\player;

use cooldogedev\BedrockEconomy\transaction\types\TransferTransaction;
use cooldogedev\libSQL\query\MySQLQuery;
use mysqli;

final class MySQLTransferQuery extends MySQLQuery
{
    public function __construct(protected TransferTransaction $transaction)
    {
    }

    public function onRun(mysqli $connection): void
    {
        $senderName = strtolower($this->getTransaction()->getSender());
        $receiverName = strtolower($this->getTransaction()->getReceiver());

        /*
         * Deduct the amount from the sender's balance
         */
        $statement = $connection->prepare($this->getDeductionQuery());
        $statement->bind_param("s", $senderName);
        $statement->execute();
        $successful = $statement->affected_rows > 0;
        $statement->close();

        if (!$successful) {
            $this->setResult(false);
            return;
        }

        /*
         * Add the amount to the receiver's balance
         */
        $statement = $connection->prepare($this->getAdditionQuery());
        $statement->bind_param("s", $receiverName);
        $statement->execute();
        $successful = $statement->affected_rows > 0;
        $statement->close();

        if (!$successful) {
            $this->setResult(false);
            return;
        }

        $this->setResult(true);
    }

    public function getTransaction(): TransferTransaction
    {
        return $this->transaction;
    }

    public function getDeductionQuery(): string
    {
        $statement = "MAX (balance - " . $this->getTransaction()->getAmount() . ", 0)";

        return "UPDATE " . $this->getTable() . " SET balance = " . $statement . " WHERE username = ?";
    }

    public function getAdditionQuery(): string
    {
        $statement = "MIN (balance + " . $this->getTransaction()->getAmount() . ", 0)";

        return "UPDATE " . $this->getTable() . " SET balance = " . $statement . " WHERE username = ?";
    }
}