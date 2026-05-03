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

namespace BaksDev\Products\Supply\Messenger\ProductSupply\UpdateProductSupply;


use BaksDev\Products\Supply\Entity\Event\Product\ProductSupplyProduct;
use BaksDev\Products\Supply\UseCase\Admin\ProductSupply\ProductSupplyProductDTO;
use BaksDev\Products\Supply\UseCase\Admin\ProductSupply\ProductSupplyProductHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Добавляет количество товара к товару в поставке после сканировния */
#[Autoconfigure(shared: false)]
#[AsMessageHandler(priority: 0)]
final readonly class UpdateProductSupplyDispatcher
{
    public function __construct(
        #[Target('productsSignLogger')] private LoggerInterface $logger,
        private ProductSupplyProductHandler $ProductSupplyProductHandler
    ) {}

    public function __invoke(UpdateProductSupplyMessage $message): void
    {
        $ProductSupplyProductDTO = new ProductSupplyProductDTO();

        $ProductSupplyProductDTO
            ->setEvent($message->getEvent())
            ->setProduct($message->getProduct())
            ->setAppend($message->getTotal());

        $ProductSupplyProduct = $this->ProductSupplyProductHandler
            ->handle($ProductSupplyProductDTO);

        if(false === ($ProductSupplyProduct instanceof ProductSupplyProduct))
        {
            $this->logger->critical(
                'products-supply: ошибка при добавлении единицы продукции к поставке',
                [self::class.':'.__LINE__, $message],
            );
        }
    }
}
