<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
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

final readonly class AllProductSupplyResult
{
    public function __construct(
        private string $id,
        private string $event,
        private string $supply_container,
        private string $supply_products,
    ) {}

    public function getId(): ProductSupplyUid
    {
        return new ProductSupplyUid($this->id);
    }

    public function getEvent(): ProductSupplyEventUid
    {
        return new ProductSupplyEventUid($this->event);
    }

    public function getSupplyContainer(): string
    {
        return $this->supply_container;
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
}