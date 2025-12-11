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
    private ?ProductUid $product = null;

    /** Константа ТП */
    #[Assert\Uuid]
    private ?ProductOfferConst $offerConst = null;

    /** Константа множественного варианта */
    #[Assert\Uuid]
    private ?ProductVariationConst $variationConst = null;

    /** Константа модификации множественного варианта */
    #[Assert\Uuid]
    private ?ProductModificationConst $modificationConst = null;

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

    /**
     * Total
     */
    public function setTotal(int $total): NewProductSupplyProductDTO
    {
        $this->total = $total;
        return $this;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * Product
     */
    public function getProduct(): ?ProductUid
    {
        return $this->product;
    }

    public function setProduct(ProductUid|false|null $product): self
    {
        $this->product = $product ?: null;
        return $this;
    }

    /**
     * Offer
     */
    public function getOfferConst(): ?ProductOfferConst
    {
        return $this->offerConst;
    }

    public function setOfferConst(ProductOfferConst|false|null $offerConst): self
    {
        $this->offerConst = $offerConst ?: null;
        return $this;
    }

    /**
     * Variation
     */
    public function getVariationConst(): ?ProductVariationConst
    {
        return $this->variationConst;
    }

    public function setVariationConst(ProductVariationConst|false|null $variationConst): self
    {
        $this->variationConst = $variationConst ?: null;
        return $this;
    }

    /**
     * Modification
     */
    public function getModificationConst(): ?ProductModificationConst
    {
        return $this->modificationConst;
    }

    public function setModificationConst(ProductModificationConst|false|null $modificationConst): self
    {
        $this->modificationConst = $modificationConst ?: null;
        return $this;
    }

    /**
     * Received
     */
    public function setReceived(bool $received): NewProductSupplyProductDTO
    {
        $this->received = $received;
        return $this;
    }

    public function getReceived(): bool
    {
        return $this->received;
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