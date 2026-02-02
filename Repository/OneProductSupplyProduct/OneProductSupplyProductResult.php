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

declare(strict_types=1);

namespace BaksDev\Products\Supply\Repository\OneProductSupplyProduct;

use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Supply\Type\Event\ProductSupplyEventUid;
use BaksDev\Products\Supply\Type\ProductSupplyUid;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus;

final readonly class OneProductSupplyProductResult
{
    public function __construct(
        private string $id,
        private string $event,
        private string $status,
        private string $product,
        private ?string $offer_const,
        private ?string $variation_const,
        private ?string $modification_const,
        private bool $received,
    ) {}

    public function getId(): ProductSupplyUid
    {
        return new ProductSupplyUid($this->id);
    }

    public function getEvent(): ProductSupplyEventUid
    {
        return new ProductSupplyEventUid($this->event);
    }

    public function getStatus(): ProductSupplyStatus
    {
        return new ProductSupplyStatus($this->status);
    }

    public function getProduct(): ProductUid
    {
        return new ProductUid($this->product);
    }

    public function getOfferConst(): ?ProductOfferConst
    {
        return null !== $this->offer_const ? new ProductOfferConst($this->offer_const) : null;
    }

    public function getVariationConst(): ?ProductVariationConst
    {
        return null !== $this->variation_const ? new ProductVariationConst($this->variation_const) : null;
    }

    public function getModificationConst(): ?ProductModificationConst
    {
        return null !== $this->modification_const ? new ProductModificationConst($this->modification_const) : null;
    }

    public function isReceived(): bool
    {
        return $this->received;
    }
}