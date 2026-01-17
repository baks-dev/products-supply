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

namespace BaksDev\Products\Supply\Messenger\ProductSupply\CompletedStatusProductSupply;

use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\Repository\CurrentProductSupplyEvent\CurrentProductSupplyEventInterface;
use BaksDev\Products\Supply\UseCase\Admin\Completed\ProductSupplyStatusCompletedDTO;
use BaksDev\Products\Supply\UseCase\Admin\Edit\EditProductSupplyHandler;
use BaksDev\Products\Supply\UseCase\Admin\Edit\Product\EditProductSupplyProductDTO;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Обновляет идентификаторы продукта в поставке
 */
#[AsMessageHandler(priority: 0)]
final readonly class CompletedStatusProductSupplyDispatcher
{
    public function __construct(
        #[Target('productsSupplyLogger')] private LoggerInterface $logger,
        private CurrentProductSupplyEventInterface $currentProductSupplyEventRepository,
        private EditProductSupplyHandler $editProductSupplyHandler,
    ) {}

    public function __invoke(CompletedStatusProductSupplyMessage $message): void
    {
        /** Активное событие поставки c продуктом без идентификаторов */
        $currentSupply = $this->currentProductSupplyEventRepository->find($message->getSupply());

        if(false === ($currentSupply instanceof ProductSupplyEvent))
        {
            $this->logger->critical(
                message: 'Не найдено событие ProductSupplyEvent',
                context: [
                    self::class.':'.__LINE__,
                    var_export($message, true),
                ],
            );

            return;
        }

        $ProductSupplyStatusCompletedDTO = new ProductSupplyStatusCompletedDTO($currentSupply->getId());
        $currentSupply->getDto($ProductSupplyStatusCompletedDTO);

        /**
         * Проверяем, что все продукты из поставки поступили на склад
         *
         * @var EditProductSupplyProductDTO $product
         */
        $existNotReceived = $ProductSupplyStatusCompletedDTO->getProduct()
            ->exists(function($key, $product) {
                return false === $product->getReceived();
            });

        /** Если не все продукты поступили на склад - прерываем  */
        if(true === $existNotReceived)
        {
            return;
        }

        $handle = $this->editProductSupplyHandler->handle($ProductSupplyStatusCompletedDTO);

        if(false === $handle instanceof ProductSupply)
        {
            $this->logger->critical(
                message: sprintf(
                    '%s: Ошибка изменения статуса с %s на %s в поставке %s',
                    $handle,
                    $currentSupply->getStatus(),
                    $ProductSupplyStatusCompletedDTO->getStatus(),
                    $currentSupply->getMain(),
                ),
                context: [
                    self::class.':'.__LINE__,
                    var_export($message, true),
                ],
            );
        }

        if(true === $handle instanceof ProductSupply)
        {
            $this->logger->info(
                message: sprintf(
                    'Успешно переместили поставку %s со статуса %s в статус %s',
                    $handle->getId(), $currentSupply->getStatus(), $ProductSupplyStatusCompletedDTO->getStatus(),
                ),
                context: [
                    self::class.':'.__LINE__,
                ],
            );
        }
    }
}