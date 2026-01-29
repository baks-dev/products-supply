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
 *
 */

declare(strict_types=1);

namespace BaksDev\Products\Supply\Messenger\ProductSupply\CheckSignOnProductSupplyProduct;

use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Products\Supply\Entity\Event\Lock\ProductSupplyLock;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Repository\AllProductSupplyProduct\AllProductSupplyProductInterface;
use BaksDev\Products\Supply\Repository\CurrentProductSupplyEvent\CurrentProductSupplyEventInterface;
use BaksDev\Products\Supply\Repository\ProductSign\ProductSignCountForSupply\ProductSignCountForSupplyInterface;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\Collection\ProductSupplyStatusNew;
use BaksDev\Products\Supply\UseCase\Admin\Lock\ProductSupplyLockDTO;
use BaksDev\Products\Supply\UseCase\Admin\Lock\ProductSupplyLockHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Получает количество зарезервированных ЧЗ на продукты в поставке
 */
#[AsMessageHandler(priority: 0)]
final readonly class CheckSignOnProductSupplyProductDispatcher
{
    public function __construct(
        #[Target('productsSupplyLogger')] private LoggerInterface $logger,
        private CentrifugoPublishInterface $centrifugo,
        private MessageDispatchInterface $messageDispatch,
        private ProductSupplyLockHandler $productSupplyLockHandler,
        private CurrentProductSupplyEventInterface $currentProductSupplyEventRepository,
        private AllProductSupplyProductInterface $allProductSupplyProductRepository,
        private ProductSignCountForSupplyInterface $productSignCountBySupplyRepository,
    ) {}

    public function __invoke(CheckSignOnProductSupplyProductMessage $message): void
    {
        /** Текущее событие поставки */
        $ProductSupplyEvent = $this->currentProductSupplyEventRepository
            ->find($message->getSupply());

        if(false === ($ProductSupplyEvent instanceof ProductSupplyEvent))
        {
            $this->logger->critical(
                message: 'Событие ProductSupplyEvent не найдено',
                context: [
                    self::class.':'.__LINE__,
                    var_export($message, true),
                ],
            );

            return;
        }

        /** Если статус поставки не new «Новая» - прерываем работу */
        if(false === $ProductSupplyEvent->getStatus()->equals(ProductSupplyStatusNew::class))
        {
            return;
        }

        $ProductSupplyProduct = $this->allProductSupplyProductRepository
            ->forSupply($message->getSupply())
            ->findAll();

        if(false === $ProductSupplyProduct)
        {
            return;
        }

        $total = 0;
        $reserve = 0;

        foreach($ProductSupplyEvent->getProduct() as $ProductSupplyProduct)
        {
            /** Суммируем все количество продуктов в поставке */
            $total += $ProductSupplyProduct->getTotal();

            $productSignReserve = $this->productSignCountBySupplyRepository
                ->forSupply($ProductSupplyEvent->getMain())
                ->forProduct($ProductSupplyProduct->getProduct())
                ->forOffer($ProductSupplyProduct->getOfferConst())
                ->forVariation($ProductSupplyProduct->getVariationConst())
                ->forModification($ProductSupplyProduct->getModificationConst())
                ->count();

            /** Суммируем все резервы на ЧЗ */
            $reserve += $productSignReserve;
        }

        /** Ошибка */
        if($reserve > $total)
        {
            $this->logger->warning(
                message: sprintf('Поставка %s: Количество зарезервированных ЧЗ больше количества продуктов',
                    $ProductSupplyEvent->getInvariable()->getNumber(),
                ),
                context: [
                    'зарезервировано' => $reserve,
                    'в поставке' => $total,
                    var_export($message, true),
                    self::class.':'.__LINE__],
            );

            return;
        }

        /** Повторяем сверку */
        if($reserve < $total)
        {
            $this->logger->info(
                message: sprintf('Поставка %s: Зарезервировано %s ЧЗ для %s единиц продукции. Продолжаем поиск свободных ЧЗ.',
                    $ProductSupplyEvent->getInvariable()->getNumber(),
                    $reserve,
                    $total,
                ),
                context: [self::class.':'.__LINE__],
            );

            /** Повтор проверки */
            $this->messageDispatch
                ->dispatch(
                    message: $message,
                    stamps: [new MessageDelay(sprintf('%s seconds', ($total - $reserve)))],
                    transport: 'products-supply',
                );
        }

        /** Разблокируем */
        if($reserve === $total)
        {
            $this->logger->info(
                message: sprintf('Поставка %s: Для всех продуктов (%s) из поставки найдены ЧЗ (%s)',
                    $ProductSupplyEvent->getInvariable()->getNumber(),
                    $reserve,
                    $total,
                ),
                context: [self::class.':'.__LINE__],
            );

            $this->unlock($ProductSupplyEvent);
        }
    }

    /** Разблокирует сущность и отправляет информацию об этом на фронтенд */
    private function unlock(ProductSupplyEvent $ProductSupplyEvent): void
    {
        $ProductSupplyLockDTO = new ProductSupplyLockDTO($ProductSupplyEvent->getId());
        $ProductSupplyEvent->getLock()->getDto($ProductSupplyLockDTO);

        $ProductSupplyLockDTO->unlock(); // снимаем блокировку

        $lockHandler = $this->productSupplyLockHandler->handle($ProductSupplyLockDTO);

        if(true === $lockHandler instanceof ProductSupplyLock)
        {
            $this->logger->warning(
                message: sprintf('Поставка %s: Сняли блокировку',
                    $ProductSupplyEvent->getInvariable()->getNumber(),
                ),
                context: [
                    'main' => (string) $ProductSupplyEvent->getMain(),
                    'event' => (string) $ProductSupplyEvent->getId(),
                    self::class.':'.__LINE__],
            );

            $socket = $this->centrifugo
                ->addData(['supply' => (string) $ProductSupplyEvent->getMain()])
                ->addData(['lock' => false])
                ->addData(['context' => 'На все количество продуктов в поставке были зарезервированы Честные знаки'])
                ->send('supplys'); // канал для перетаскивания

            if($socket && $socket->isError())
            {
                $this->logger->critical(
                    message: 'Ошибка при отправке информации о блокировке в Centrifugo',
                    context: [
                        $socket->getMessage(),
                        $ProductSupplyEvent->getMain(),
                        self::class.':'.__LINE__,
                    ],
                );
            }
        }

        if(false === $lockHandler instanceof ProductSupplyLock)
        {
            $this->logger->critical(
                message: sprintf('Ошибка при снятии блокировки с %s', $ProductSupplyEvent->getMain()),
                context: [self::class.':'.__LINE__],
            );
        }
    }
}