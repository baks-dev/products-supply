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

namespace BaksDev\Products\Supply\UseCase\Admin\Edit\Product;

use BaksDev\Products\Product\Repository\ProductDetail\ProductDetailByConstResult;
use BaksDev\Products\Product\Repository\ProductDetail\ProductDetailByInvariableResult;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Invariable\ProductInvariableUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Supply\Entity\Event\Product\ProductSupplyProductInterface;
use BaksDev\Products\Supply\Type\Product\ProductSupplyProductUid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Объект для изменения идентификаторов продукта в поставке
 *
 * @see ProductSupplyProduct
 */
final class EditProductSupplyProductDTO implements ProductSupplyProductInterface
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private ProductSupplyProductUid $id;

    /** Количество продуктов в поставке */
    #[Assert\NotBlank]
    private readonly int $total;

    /** ID Invariable продукта */
    #[Assert\Uuid]
    private ProductInvariableUid $product;

    private bool $received;

    /** HELPERS */

    /** Карточка товара */
    private ProductDetailByInvariableResult|null $card = null;

    public function __construct()
    {
        $this->id = clone new ProductSupplyProductUid();
    }

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
    public function getTotal(): int
    {
        return $this->total;
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

    public function received(): self
    {
        $this->received = true;
        return $this;
    }

    /** HELPERS */

    /** Карточка товара */
    public function getCard(): ?ProductDetailByInvariableResult
    {
        return $this->card;
    }

    public function setCard(ProductDetailByInvariableResult|null|false $card): void
    {
        $this->card = $card ?: null;
    }
}