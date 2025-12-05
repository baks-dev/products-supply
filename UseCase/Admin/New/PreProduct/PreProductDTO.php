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

declare(strict_types=1);

namespace BaksDev\Products\Supply\UseCase\Admin\New\PreProduct;

use BaksDev\Products\Category\Type\Id\CategoryProductUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;

/** @see */
final class PreProductDTO
{
    /** Категория */
    private ?CategoryProductUid $category = null;

    /** Продукт */
    private ?ProductUid $preProduct = null;

    /** Торговое предложение */
    private ?ProductOfferConst $preOfferConst = null;

    /** Множественный вариант */
    private ?ProductVariationConst $preVariationConst = null;

    /** Модификация множественного варианта */
    private ?ProductModificationConst $preModificationConst = null;

    /** Количество */
    private ?int $preTotal = null;

    // CATEGORY
    public function getCategory(): ?CategoryProductUid
    {
        return $this->category;
    }

    public function setCategory(?CategoryProductUid $category): self
    {
        $this->category = $category;
        return $this;
    }

    // PRODUCT
    public function getPreProduct(): ?ProductUid
    {
        return $this->preProduct;
    }

    public function setPreProduct(?ProductUid $product): self
    {
        $this->preProduct = $product;
        return $this;
    }

    // OFFER
    public function getPreOfferConst(): ?ProductOfferConst
    {
        return $this->preOfferConst;
    }

    public function setPreOfferConst(?ProductOfferConst $offer): self
    {
        $this->preOfferConst = $offer;
        return $this;
    }

    // VARIATION
    public function getPreVariationConst(): ?ProductVariationConst
    {
        return $this->preVariationConst;
    }

    public function setPreVariationConst(?ProductVariationConst $preVariationConst): self
    {
        $this->preVariationConst = $preVariationConst;
        return $this;
    }

    // MODIFICATION
    public function getPreModificationConst(): ?ProductModificationConst
    {
        return $this->preModificationConst;
    }

    public function setPreModificationConst(?ProductModificationConst $preModificationConst): self
    {
        $this->preModificationConst = $preModificationConst;
        return $this;
    }

    // TOTAL
    public function getPreTotal(): ?int
    {
        return $this->preTotal;
    }

    public function setPreTotal(int $total): self
    {
        $this->preTotal = $total;
        return $this;
    }
}