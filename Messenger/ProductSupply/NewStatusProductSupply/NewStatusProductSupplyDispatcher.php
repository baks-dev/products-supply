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

namespace BaksDev\Products\Supply\Messenger\ProductSupply\NewStatusProductSupply;

use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Products\Product\Type\Barcode\ProductBarcode;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Messenger\ProductSign\ProcessReservation\ProcessReservationProductSignMessage;
use BaksDev\Products\Supply\Messenger\ProductSupply\CheckSignOnProductSupplyProduct\CheckSignOnProductSupplyProductMessage;
use BaksDev\Products\Supply\Messenger\ProductSupplyMessage;
use BaksDev\Products\Supply\Repository\CurrentProductSupplyEvent\CurrentProductSupplyEventInterface;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\Collection\ProductSupplyStatusNew;
use BaksDev\Products\Supply\UseCase\Admin\New\NewProductSupplyDTO;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * При присвоении поставке статуса New «Новая» -
 * запускает процесс резервирования ОДИНОГО Честный знака на ОДНУ единицу продукции в поставке
 * @see ProcessReservationProductSignDispatcher
 */
#[AsMessageHandler(priority: 0)]
final readonly class NewStatusProductSupplyDispatcher
{
    public function __construct(
        #[Target('productsSupplyLogger')] private LoggerInterface $logger,
        private CurrentProductSupplyEventInterface $currentProductSupplyEventRepository,
        private MessageDispatchInterface $messageDispatch,
    ) {}

    public function __invoke(ProductSupplyMessage $message): void
    {
        /** Текущее событие поставки */
        $ProductSupplyEvent = $this->currentProductSupplyEventRepository
            ->find($message->getId());

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

        /** Если статус поставки не New «Новая» - прерываем работу */
        if(false === $ProductSupplyEvent->getStatus()->equals(ProductSupplyStatusNew::class))
        {
            return;
        }

        $NewProductSupplyDTO = new NewProductSupplyDTO();
        $ProductSupplyEvent->getDto($NewProductSupplyDTO);

        /** Задержка из расчета - 1 секунда на 1 одного ЧЗ */
        $delay = 0;

        /** Процесс бронирования Честных знаков на продукты в поставке */
        foreach($NewProductSupplyDTO->getProduct() as $key => $product)
        {
            $total = $product->getTotal();

            /** Бронируем ОДИН ЧЗ на ОДНУ единицу продукции в поставке */
            for($i = 0; $i < $total; $i++)
            {
                //                $this->logger->info(
                //                    message: sprintf(
                //                        'Поставка %s: Резервирование ЧЗ: продукт %s, %s единица продукции из %s',
                //                        $ProductSupplyEvent->getInvariable()->getNumber(),
                //                        $key + 1, $i + 1, $total,
                //                    ),
                //                    context: [
                //                        self::class.':'.__LINE__,
                //                        var_export($product, true),
                //                    ],
                //                );

                $this->messageDispatch
                    ->dispatch(
                        message: new ProcessReservationProductSignMessage(
                            supply: $ProductSupplyEvent->getMain(),
                            user: $NewProductSupplyDTO->getPersonal()->getUsr(),
                            profile: $NewProductSupplyDTO->getPersonal()->getProfile(),
                            product: $product->getProduct(),
                            offer: $product->getOfferConst(),
                            variation: $product->getVariationConst(),
                            modification: $product->getModificationConst(),
                        ),
                        transport: 'products-supply',
                    );
            }

            /** Задержка для проверки равна сумме количества ВСЕХ продуктов в поставке */
            $delay += $total;
        }

        /**
         * Сообщение для проверки резервирования ЧЗ на все количество продукции в поставке
         */

        $this->logger->info(
            message: sprintf(
                'Поставка %s: Проверка резервирования ЧЗ с интервалом в %s секунд',
                $NewProductSupplyDTO->getInvariable()->getNumber(),
                $delay,
            ),
            context: [
                self::class.':'.__LINE__,
                var_export($message, true),
            ],
        );

        $this->messageDispatch
            ->dispatch(
                message: new CheckSignOnProductSupplyProductMessage($ProductSupplyEvent->getMain()),
                stamps: [new MessageDelay(sprintf('%s seconds', $delay))],
                transport: 'products-supply',
            );
    }
}