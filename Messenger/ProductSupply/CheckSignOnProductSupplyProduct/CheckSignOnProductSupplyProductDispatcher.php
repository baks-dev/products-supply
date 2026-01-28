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

        $productSignReserve = $this->productSignCountBySupplyRepository
            ->forSupply($ProductSupplyEvent->getMain())
            ->forProduct($message->getProduct())
            ->forOffer($message->getOfferConst())
            ->forVariation($message->getVariationConst())
            ->forModification($message->getModificationConst())
            ->count();

        /** Ошибка */
        if($productSignReserve > $message->getTotal())
        {
            $this->logger->debug(
                message: sprintf('Поставка %s: Количество зарезервированных ЧЗ больше количества продуктов в поставке',
                    $ProductSupplyEvent->getInvariable()->getNumber(),
                ),
                context: [
                    'зарезервировано' => $productSignReserve,
                    'в поставке' => $message->getTotal(),
                    var_export($message, true),
                    self::class.':'.__LINE__],
            );

            return;
        }

        /** Повторяем сверку */
        if($productSignReserve < $message->getTotal())
        {
            $this->logger->info(
                message: sprintf('Поставка %s: Продолжаем поиск свободных ЧЗ. Зарезервировано %s ЧЗ для %s продуктов в поставке.',
                    $ProductSupplyEvent->getInvariable()->getNumber(),
                    $productSignReserve,
                    $message->getTotal()
                ),
                context: [self::class.':'.__LINE__],
            );

            /** Повтор проверки */
            $this->messageDispatch
                ->dispatch(
                    message: $message,
                    stamps: [new MessageDelay('15 seconds')],
                    transport: 'products-supply',
                );
        }

        /** Разблокируем */
        if($productSignReserve === $message->getTotal())
        {
            $this->logger->info(
                message: sprintf('Поставка %s: Для всех продуктов (%s) из поставки найдены ЧЗ (%s)',
                    $ProductSupplyEvent->getInvariable()->getNumber(),
                    $productSignReserve,
                    $message->getTotal()
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

        $ProductSupplyLockDTO
            ->unlock() // снимаем блокировку
            ->setContext(self::class);

        $lockHandler = $this->productSupplyLockHandler->handle($ProductSupplyLockDTO);

        if(true === $lockHandler instanceof ProductSupplyLock)
        {
            $this->logger->warning(
                message: sprintf('Сняли блокировку с %s', $ProductSupplyEvent->getMain()),
                context: [self::class.':'.__LINE__],
            );

            $socket = $this->centrifugo
                ->addData(['supply' => (string) $ProductSupplyEvent->getMain()])
                ->addData(['lock' => false])
                ->addData(['context' => 'На все количество продуктов в поставке были зарезервированы Честные знаки'])
                ->send('supplys');

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