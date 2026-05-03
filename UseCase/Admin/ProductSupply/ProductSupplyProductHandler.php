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
 */

declare(strict_types=1);

namespace BaksDev\Products\Supply\UseCase\Admin\ProductSupply;


use BaksDev\Core\Entity\AbstractHandler;
use BaksDev\Products\Supply\Entity\Event\Product\ProductSupplyProduct;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;


final class ProductSupplyProductHandler extends AbstractHandler
{
    /** @see ProductSupplyProduct */
    public function handle(ProductSupplyProductDTO $command): string|ProductSupplyProduct
    {
        $ProductSupplyProduct = $this->getRepository(ProductSupplyProduct::class)
            ->findOneBy([
                'event' => $command->getEvent(),
                'product' => $command->getProduct(),
            ]);


        if(false === ($ProductSupplyProduct instanceof ProductSupplyProduct))
        {
            $ProductSupplyEvent = $this
                ->getRepository(ProductSupplyEvent::class)
                ->find($command->getEvent());

            if(false === ($ProductSupplyEvent instanceof ProductSupplyEvent))
            {
                $this->validatorCollection->error(
                    sprintf(
                        'ProductSupplyEvent по идентификатору %s не найден',
                        $command->getEvent(),
                    ),
                    [self::class.':'.__LINE__],
                );

                return 'Invalid Argument ProductSupplyEvent';
            }

            $ProductSupplyProduct = new ProductSupplyProduct($ProductSupplyEvent);
            $ProductSupplyProduct->setEntity($command);

            $this->persist($ProductSupplyProduct);
        }

        $ProductSupplyProduct->addTotal($command->getAppend());
        $this->validatorCollection->add($ProductSupplyProduct);

        /** Валидация всех объектов */
        if($this->validatorCollection->isInvalid())
        {
            return $this->validatorCollection->getErrorUniqid();
        }

        $this->flush();

        $this->messageDispatch
            ->addClearCacheOther('product-supply');

        return $ProductSupplyProduct;
    }
}