<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 *
 */

namespace BaksDev\Products\Supply\Repository\AllProductSupply;

use BaksDev\Products\Supply\Type\Event\ProductSupplyEventUid;
use BaksDev\Products\Supply\Type\ProductSupplyUid;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus;
use DateTimeImmutable;

final readonly class AllProductSupplyResult
{
    public function __construct(
        private string $id,
        private string $event,
        private string $supply_status,
        private string $supply_number,
        private string $supply_products,
        private string $supply_mod_date,
        private ?bool $supply_lock,
        private ?string $supply_context,
        private ?string $supply_created,
        private ?string $supply_arrival,
    ) {}

    public function getId(): ProductSupplyUid
    {
        return new ProductSupplyUid($this->id);
    }

    public function getEvent(): ProductSupplyEventUid
    {
        return new ProductSupplyEventUid($this->event);
    }

    public function getSupplyStatus(): ProductSupplyStatus
    {
        return new ProductSupplyStatus($this->supply_status);
    }

    public function getSupplyNumber(): string
    {
        return $this->supply_number;
    }

    public function getSupplyProducts(): array|null
    {
        if(is_null($this->supply_products))
        {
            return null;
        }

        if(false === json_validate($this->supply_products))
        {
            return null;
        }

        $products = json_decode($this->supply_products, true, 512, JSON_THROW_ON_ERROR);

        if(null === current($products))
        {
            return null;
        }

        return $products;
    }

    public function getDateModify(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->supply_mod_date);
    }

    public function getSupplyCreated(): ?DateTimeImmutable
    {
        return $this->supply_created ? new DateTimeImmutable($this->supply_created) : null;
    }

    public function getSupplyArrival(): ?DateTimeImmutable
    {
        return $this->supply_arrival ? new DateTimeImmutable($this->supply_arrival) : null;
    }

    public function getSupplyLock(): ?bool
    {
        return $this->supply_lock === true;
    }

    public function getSupplyContext(): ?string
    {
        return $this->supply_context;
    }
}