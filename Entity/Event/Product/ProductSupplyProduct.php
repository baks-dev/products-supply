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

namespace BaksDev\Products\Supply\Entity\Event\Product;

use BaksDev\Core\Entity\EntityEvent;
use BaksDev\Products\Product\Type\Barcode\ProductBarcode;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Type\Product\ProductSupplyProductUid;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'product_supply_product')]
class ProductSupplyProduct extends EntityEvent
{
    /**
     * Идентификатор
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Id]
    #[ORM\Column(type: ProductSupplyProductUid::TYPE, nullable: false)]
    private ProductSupplyProductUid $id;

    /**
     * Идентификатор События
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\ManyToOne(targetEntity: ProductSupplyEvent::class, inversedBy: 'product')]
    #[ORM\JoinColumn(name: 'event', referencedColumnName: 'id')]
    private ProductSupplyEvent $event;

    /**
     * Количество продуктов в поставке
     */
    #[Assert\NotBlank]
    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private readonly int $total;

    /**
     * Штрихкод товара
     */
    #[ORM\Column(type: ProductBarcode::TYPE, nullable: false)]
    private readonly ProductBarcode $barcode;

    /**
     * Продукт
     */

    /** ID продукта (не уникальное) */
    #[Assert\Uuid]
    #[ORM\Column(type: ProductUid::TYPE, nullable: true)]
    private ?ProductUid $product = null;

    /** Константа ТП */
    #[Assert\Uuid]
    #[ORM\Column(name: 'offer_const', type: ProductOfferConst::TYPE, nullable: true)]
    private ?ProductOfferConst $offerConst = null;

    /** Константа множественного варианта */
    #[Assert\Uuid]
    #[ORM\Column(name: 'variation_const', type: ProductVariationConst::TYPE, nullable: true)]
    private ?ProductVariationConst $variationConst = null;

    /** Константа модификации множественного варианта */
    #[Assert\Uuid]
    #[ORM\Column(name: 'modification_const', type: ProductModificationConst::TYPE, nullable: true)]
    private ?ProductModificationConst $modificationConst = null;

    /** Принят ли на склад */
    #[ORM\Column(type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    private bool $received = false;

    public function __construct(ProductSupplyEvent $event)
    {
        $this->id = new ProductSupplyProductUid();
        $this->event = $event;
    }

    public function __clone()
    {
        $this->id = clone $this->id;
    }

    public function getId(): ProductSupplyProductUid
    {
        return $this->id;
    }

    public function getEvent(): ProductSupplyEvent
    {
        return $this->event;
    }

    public function getDto($dto): mixed
    {
        if($dto instanceof ProductSupplyProductInterface)
        {
            return parent::getDto($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

    public function setEntity($dto): mixed
    {
        if($dto instanceof ProductSupplyProductInterface)
        {
            return parent::setEntity($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

    public function getTotal(): int
    {
        return $this->total;
    }
}