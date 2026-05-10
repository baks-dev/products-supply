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

namespace BaksDev\Products\Supply\UseCase\Admin\New\Product;

use BaksDev\Products\Product\Repository\ProductDetail\ProductDetailByConstResult;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Invariable\ProductInvariableUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Supply\Entity\Event\Product\ProductSupplyProductInterface;
use BaksDev\Products\Supply\Type\Product\ProductSupplyProductUid;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ProductSupplyProduct */
final class NewProductSupplyProductDTO implements ProductSupplyProductInterface
{
    private ?ProductSupplyProductUid $id = null;

    /** Количество продуктов в поставке */
    #[Assert\NotBlank]
    private int $total;

    /** ID продукта (не уникальное) */
    #[Assert\Uuid]
    private ?ProductInvariableUid $product = null;

    private bool $received = false;

    /** HELPERS */

    /** Карточка товара */
    private ProductDetailByConstResult|null $card = null;

    /**
     * Id
     */
    public function getId(): ?ProductSupplyProductUid
    {
        return $this->id;
    }

    public function setId(?ProductSupplyProductUid $id): void
    {
        $this->id = $id;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * Total
     */
    public function setTotal(int $total): self
    {
        $this->total = $total;
        return $this;
    }

    /**
     * Product
     */
    public function getProduct(): ?ProductInvariableUid
    {
        return $this->product;
    }

    public function setProduct(ProductInvariableUid|false|null $product): self
    {
        $this->product = $product ?: null;
        return $this;
    }


    public function getReceived(): bool
    {
        return $this->received;
    }

    /**
     * Received
     */
    public function setReceived(bool $received): self
    {
        $this->received = $received;
        return $this;
    }

    /** HELPERS */

    /** Карточка товара */
    public function getCard(): ?ProductDetailByConstResult
    {
        return $this->card;
    }

    public function setCard(bool|ProductDetailByConstResult $card): void
    {
        $this->card = $card ?: null;
    }
}