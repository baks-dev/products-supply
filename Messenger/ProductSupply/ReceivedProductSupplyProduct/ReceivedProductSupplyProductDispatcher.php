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

namespace BaksDev\Products\Supply\Messenger\ProductSupply\ReceivedProductSupplyProduct;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Repository\ProductStocksEvent\ProductStocksEventInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusIncoming;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\Messenger\ProductSupply\CompletedStatusProductSupply\CompletedStatusProductSupplyMessage;
use BaksDev\Products\Supply\Repository\CurrentProductSupplyEvent\CurrentProductSupplyEventInterface;
use BaksDev\Products\Supply\Repository\OneProductSupplyProduct\OneProductSupplyProductInterface;
use BaksDev\Products\Supply\Type\ProductSupplyUid;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\Collection\ProductSupplyStatusDelivery;
use BaksDev\Products\Supply\UseCase\Admin\Edit\EditProductSupplyDTO;
use BaksDev\Products\Supply\UseCase\Admin\Edit\EditProductSupplyHandler;
use BaksDev\Products\Supply\UseCase\Admin\Edit\Product\EditProductSupplyProductDTO;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Отмечает продукт в поставке при его поступления на склад
 * next @see CompletedStatusProductSupplyDispatcher
 */
#[AsMessageHandler(priority: 0)]
final readonly class ReceivedProductSupplyProductDispatcher
{
    public function __construct(
        #[Target('productsSupplyLogger')] private LoggerInterface $logger,
        private MessageDispatchInterface $messageDispatch,
        private DeduplicatorInterface $deduplicator,
        private ProductStocksEventInterface $ProductStocksEventRepository,
        private CurrentProductSupplyEventInterface $currentProductSupplyEventRepository,
        private OneProductSupplyProductInterface $oneProductSupplyProductRepository,
        private EditProductSupplyHandler $editProductSupplyHandler,
    ) {}

    public function __invoke(ProductStockMessage $message): void
    {
        $DeduplicatorExecuted = $this->deduplicator
            ->namespace('products-supply')
            ->deduplication([
                (string) $message->getId(),
                self::class
            ]);

        if($DeduplicatorExecuted->isExecuted())
        {
            return;
        }

        $ProductStockEvent = $this->ProductStocksEventRepository
            ->forEvent($message->getEvent())
            ->find();

        if(false === ($ProductStockEvent instanceof ProductStockEvent))
        {
            $this->logger->warning(
                message: 'Не найдено ProductStockEvent',
                context: [
                    self::class.':'.__LINE__,
                    var_export($message, true),
                ],
            );

            return;
        }

        /**
         * Если статус НЕ является Incoming «Приход на склад» - прерываем
         */
        if(false === $ProductStockEvent->equalsProductStockStatus(ProductStockStatusIncoming::class))
        {
            return;
        }

        /** @var ProductStockProduct $stockProduct */
        $stockProduct = $ProductStockEvent->getProduct()->current();
        $stockSupply = new ProductSupplyUid($ProductStockEvent->getSupply()->getSupply());

        /** Активное событие поставки */
        $ProductSupplyEvent = $this->currentProductSupplyEventRepository
            ->find($stockSupply);

        if(false === ($ProductSupplyEvent instanceof ProductSupplyEvent))
        {
            $this->logger->warning(
                message: 'Не найдено ProductSupplyEvent',
                context: [
                    self::class.':'.__LINE__,
                    var_export($message, true),
                ],
            );

            return;
        }

        /** Идентификаторы продукта из поставки (supply), соответсвующее идентификаторам в заявке (stock) */
        $supplyProductBySupplyStock = $this->oneProductSupplyProductRepository
            ->forSupply($ProductSupplyEvent->getMain())
            ->forProduct($stockProduct->getProduct())
            ->forOffer($stockProduct->getOffer())
            ->forVariation($stockProduct->getVariation())
            ->forModification($stockProduct->getModification())
            ->find();

        /** Прерываем обработку, если:
         * - статус НЕ РАВЕН delivery (Доставка)
         * - продукт уже был отмечен как "принят на склад"
         */
        if(
            false === $supplyProductBySupplyStock->getStatus()->equals(ProductSupplyStatusDelivery::class) ||
            true === $supplyProductBySupplyStock->isReceived()
        )
        {
            return;
        }

        /**
         * Изменяем поставку
         */

        $EditProductSupplyDTO = new EditProductSupplyDTO($supplyProductBySupplyStock->getEvent());
        $ProductSupplyEvent->getDto($EditProductSupplyDTO);

        /**
         * Проверяем соответствие продукта ИЗ ПОСТАВКИ продукту ИЗ ЗАЯВКИ
         * @var EditProductSupplyProductDTO|null $supplyProductForReceived
         */
        $supplyProductForReceived = $EditProductSupplyDTO
            ->getProduct()
            ->findFirst(function($k, EditProductSupplyProductDTO $editProductSupplyProductDTO)
            use ($supplyProductBySupplyStock) {
                return
                    $editProductSupplyProductDTO->getProduct()->equals($supplyProductBySupplyStock->getProduct())
                    &&
                    ((is_null($editProductSupplyProductDTO->getOfferConst()) && is_null($supplyProductBySupplyStock->getOfferConst())) || $editProductSupplyProductDTO->getOfferConst()->equals($supplyProductBySupplyStock->getOfferConst()))
                    &&
                    ((is_null($editProductSupplyProductDTO->getVariationConst()) && is_null($supplyProductBySupplyStock->getVariationConst())) || $editProductSupplyProductDTO->getVariationConst()->equals($supplyProductBySupplyStock->getVariationConst()))
                    &&
                    ((is_null($editProductSupplyProductDTO->getModificationConst()) && is_null($supplyProductBySupplyStock->getModificationConst())) || $editProductSupplyProductDTO->getModificationConst()->equals($supplyProductBySupplyStock->getModificationConst()));
            });

        if(null === $supplyProductForReceived)
        {
            $this->logger->warning(
                message: sprintf('Не найдено продукта ИЗ ПОСТАВКИ %s, соответствущего продукту ИЗ ЗАЯВКИ %s',
                    $ProductSupplyEvent->getMain(), $message->getId()
                ),
                context: [
                    self::class.':'.__LINE__,
                    var_export($message, true),
                ],
            );

            return;
        }

        /** флаг received = true */
        $supplyProductForReceived->received();

        $ProductSupply = $this->editProductSupplyHandler->handle($EditProductSupplyDTO);

        if(false === $ProductSupply instanceof ProductSupply)
        {
            $this->logger->critical(
                message: sprintf(
                    '%s: Ошибка обновления продукта в поставке %s при принятии складской заявки',
                    $ProductSupply, $ProductSupplyEvent->getMain(),
                ),
                context: [
                    self::class.':'.__LINE__,
                    var_export($message, true),
                ]
            );
        }

        if(true === $ProductSupply instanceof ProductSupply)
        {
            $this->logger->info(
                message: sprintf(
                    'Успешно обновили продукт %s (%s) при принятии складской заявки %s в поставке',
                    $supplyProductForReceived->getId(),
                    //                    $supplyProductForReceived->getBarcode(),
                    $message->getId(),
                    $ProductSupply->getId()
                ),
                context: [self::class.':'.__LINE__],
            );

            /**
             * Бросаем сообщение для проверки остальных продуктов
             * и перевода поставки в статус completed (Выполнен)
             */
            $this->messageDispatch
                ->dispatch(
                    message: new CompletedStatusProductSupplyMessage(supply: $ProductSupply->getId()),
                    transport: 'products-supply',
                );
        }
    }
}