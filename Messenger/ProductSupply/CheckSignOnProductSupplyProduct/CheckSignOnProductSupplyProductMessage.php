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

namespace BaksDev\Products\Supply\Messenger\ProductSupply\CheckSignOnProductSupplyProduct;

use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Supply\Type\ProductSupplyUid;

final readonly class CheckSignOnProductSupplyProductMessage
{
    /** ID поставки */
    private string $supply;

    /** ID продукта */
    private ?string $product;

    /** Постоянный уникальный идентификатор ТП */
    private ?string $offerConst;

    /** Постоянный уникальный идентификатор варианта */
    private ?string $variationConst;

    /** Постоянный уникальный идентификатор модификации */
    private ?string $modificationConst;


    public function __construct(
        ProductSupplyUid $supply,

        ?ProductUid $product,
        ?ProductOfferConst $offerConst,
        ?ProductVariationConst $variationConst,
        ?ProductModificationConst $modificationConst,
        private int $total,
    )
    {
        $this->supply = (string) $supply;

        $this->product = $product ? (string) $product : null;
        $this->offerConst = $offerConst ? (string) $offerConst : null;
        $this->variationConst = $variationConst ? (string) $variationConst : null;
        $this->modificationConst = $modificationConst ? (string) $modificationConst : null;
    }

    /**
     * Идентификатор поставки
     */
    public function getSupply(): ProductSupplyUid
    {
        return new ProductSupplyUid($this->supply);
    }

    /**
     * Product
     */
    public function getProduct(): ?ProductUid
    {
        return $this->product ? new ProductUid($this->product) : null;
    }

    /**
     * Offer
     */
    public function getOfferConst(): ?ProductOfferConst
    {
        return $this->offerConst ? new ProductOfferConst($this->offerConst) : null;
    }

    /**
     * Variation
     */
    public function getVariationConst(): ?ProductVariationConst
    {
        return $this->variationConst ? new ProductVariationConst($this->variationConst) : null;
    }

    /**
     * Modification
     */
    public function getModificationConst(): ?ProductModificationConst
    {
        return $this->modificationConst ? new ProductModificationConst($this->modificationConst) : null;
    }

    /**
     * Total
     */
    public function getTotal(): int
    {
        return $this->total;
    }
}