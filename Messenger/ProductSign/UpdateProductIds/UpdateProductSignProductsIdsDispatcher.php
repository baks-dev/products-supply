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

namespace BaksDev\Products\Supply\Messenger\ProductSign\UpdateProductIds;

use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignStatusHandler;
use BaksDev\Products\Supply\Entity\Event\Product\ProductSupplyProduct;
use BaksDev\Products\Supply\Repository\CurrentProductSupplyProductEvent\CurrentProductSupplyProductEventInterface;
use BaksDev\Products\Supply\Repository\ProductSign\AllProductSignEventsByProductSupply\AllProductSignByProductSupplyInterface;
use BaksDev\Products\Supply\UseCase\Admin\Edit\Product\EditProductSupplyProductDTO;
use BaksDev\Products\Supply\UseCase\Admin\ProductsSign\Edit\UpdateProductIdsProductSignDTO;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Обновляет идентификаторы продуктов у Честных знаков, забронированных для поставки продуктов
 */
#[AsMessageHandler(priority: 0)]
final readonly class UpdateProductSignProductsIdsDispatcher
{
    public function __construct(
        #[Target('productsSupplyLogger')] private LoggerInterface $logger,
        private AllProductSignByProductSupplyInterface $allProductSignByProductSupplyRepository,
        private CurrentProductSupplyProductEventInterface $currentProductSupplyProductEventRepository,
        private ProductSignStatusHandler $ProductSignStatusHandler,
    ) {}

    public function __invoke(UpdateProductSignProductsIdsMessage $message): void
    {
        $supplyProduct = $this->currentProductSupplyProductEventRepository
            ->forSupply($message->getSupply())
            ->find($message->getBarcode());

        if(false === ($supplyProduct instanceof ProductSupplyProduct))
        {
            $this->logger->critical(
                message: 'Продукт для обновления идентификаторов не найден',
                context: [
                    self::class.':'.__LINE__,
                    var_export($message, true),
                ],
            );

            return;
        }

        /** Идентификаторы продукта в поставке */
        $EditProductSupplyProductDTO = new EditProductSupplyProductDTO();
        $supplyProduct->getDto($EditProductSupplyProductDTO);

        /** Ищем Честные знаки, связанные с поставкой, в которых еще не установлены идентификаторы */
        $ProductSignEvents = $this->allProductSignByProductSupplyRepository
            ->forSupply($message->getSupply())
            ->forCode($message->getBarcode())
            ->forUser($message->getUsr())
            ->forProfile($message->getProfile())
            ->findAll();

        if(false === $ProductSignEvents->valid())
        {
            $this->logger->warning(
                message: 'products-sign: Не найдены Честные знаки, связанные с поставкой '.$message->getSupply(),
                context: [
                    self::class.':'.__LINE__,
                    var_export($message, true),
                ],
            );

            return;
        }

        /**
         * Модифицируем идентификаторы продукта в Честном знаке
         * @var ProductSignEvent $signEvent
         */
        foreach($ProductSignEvents as $signEvent)
        {
            /** Редактируем текущее событие */
            $UpdateProductSignProductsIdsDTO = new UpdateProductIdsProductSignDTO();
            $signEvent->getDto($UpdateProductSignProductsIdsDTO);

            $UpdateProductSignProductsIdsDTO->getInvariable()
                ->setProduct($EditProductSupplyProductDTO->getProduct())
                ->setOffer($EditProductSupplyProductDTO->getOfferConst())
                ->setVariation($EditProductSupplyProductDTO->getVariationConst())
                ->setModification($EditProductSupplyProductDTO->getModificationConst());

            $handle = $this->ProductSignStatusHandler->handle($UpdateProductSignProductsIdsDTO);

            if(false === ($handle instanceof ProductSign))
            {
                $this->logger->critical(
                    message: sprintf(
                        'products-sign: Не удалось обновить идентификаторы продукта в Честном знаке %s',
                        $signEvent->getMain()
                    ),
                    context: [
                        self::class.':'.__LINE__,
                        var_export($message, true),
                    ],
                );

                continue;
            }

            if(true === ($handle instanceof ProductSign))
            {
                $this->logger->info(
                    message: sprintf(
                        'products-sign: Успешно обновили идентификаторы продукта в Честном знаке %s',
                        $handle->getId()
                    ),
                    context: [
                        self::class.':'.__LINE__,
                        var_export($message, true),
                    ],
                );
            }
        }
    }
}
