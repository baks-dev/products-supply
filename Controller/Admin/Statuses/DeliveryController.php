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

declare (strict_types=1);

namespace BaksDev\Products\Supply\Controller\Admin\Statuses;

use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\Forms\Statuses\Delivery\DeliveryProductSupplyDTO;
use BaksDev\Products\Supply\Forms\Statuses\Delivery\DeliveryProductSupplyForm;
use BaksDev\Products\Supply\Forms\Statuses\ProductSupplyIdDTO;
use BaksDev\Products\Supply\Messenger\ProductStock\CreateWarehouse\CreateWarehouseProductStockMessage;
use BaksDev\Products\Supply\Repository\CurrentProductSupplyEvent\CurrentProductSupplyEventInterface;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\Collection\ProductSupplyStatusDelivery;
use BaksDev\Products\Supply\UseCase\Admin\Delivery\ProductSupplyStatusDeliveryDTO;
use BaksDev\Products\Supply\UseCase\Admin\Edit\EditProductSupplyHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Переводит поставку в статус delivery (Доставка)
 */
#[AsController]
#[RoleSecurity('ROLE_PRODUCT_SUPPLY_STATUS_DELIVERY')]
final class DeliveryController extends AbstractController
{
    public const string NAME = 'admin.supply.delivery';

    private const string STATUS = ProductSupplyStatusDelivery::STATUS;

    /** Коллекция удачных попыток обновления статуса поставок */
    private array|null $successful = null;

    /** Коллекция неудачных попыток обновления статуса поставок */
    private array|null $unsuccessful = null;

    /** Номера контейнеров обрабатываемых поставок */
    private array $numbers = [];

    /**
     * Доставка поставок
     */
    #[Route(path: '/admin/products/supply/delivery', name: self::NAME, methods: ['GET', 'POST'])]
    public function delivery(
        #[Target('productsSupplyLogger')] LoggerInterface $logger,
        MessageDispatchInterface $messageDispatch,
        Request $request,
        CentrifugoPublishInterface $publish,
        CurrentProductSupplyEventInterface $currentProductSupplyEventRepository,
        EditProductSupplyHandler $editProductSupplyHandler,
    ): Response
    {
        $DeliveryProductSupplyForm = $this
            ->createForm(
                DeliveryProductSupplyForm::class,
                $DeliveryProductSupplyDTO = new DeliveryProductSupplyDTO(),
                ['action' => $this->generateUrl('products-supply:'.self::NAME)],
            )
            ->handleRequest($request);

        if(
            $DeliveryProductSupplyForm->isSubmitted() &&
            $DeliveryProductSupplyForm->isValid() &&
            $DeliveryProductSupplyForm->has('delivery')
        )
        {

            $this->refreshTokenForm($DeliveryProductSupplyForm);

            /**
             * @var ProductSupplyIdDTO $ProductSupplyIdDTO
             */
            foreach($DeliveryProductSupplyDTO->getSupplys() as $ProductSupplyIdDTO)
            {

                /** Активное событие поставки */
                $ProductSupplyEvent = $currentProductSupplyEventRepository
                    ->find($ProductSupplyIdDTO->getId());

                /** Номер поставки */
                $number = $ProductSupplyEvent->getInvariable()->getNumber();

                if(false === ($ProductSupplyEvent instanceof ProductSupplyEvent))
                {
                    $this->unsuccessful[] = $number;

                    $logger->critical(
                        message: sprintf('Не найдено событие ProductSupplyEvent по ID: %s',
                            $ProductSupplyIdDTO->getId()),
                        context: [self::class.':'.__LINE__]
                    );

                    continue;
                }

                /**
                 * Проверка перемещения поставки из корректного статуса
                 */
                $previousStatus = $ProductSupplyEvent->getStatus()->previous(self::STATUS);

                if(false === $ProductSupplyEvent->getStatus()->equals($previousStatus))
                {
                    $this->unsuccessful[] = $number;

                    $logger->info(
                        message: sprintf('Попытка присвоить ГТД для поставки %s (%s) не из статуса: %s',
                            $ProductSupplyEvent->getMain(),
                            $ProductSupplyEvent->getInvariable()->getNumber(),
                            $previousStatus->getStatus()->getValue()
                        ),
                        context: [self::class.':'.__LINE__]
                    );

                    continue;
                }

                $ProductSupplyStatusDeliveryDTO = new ProductSupplyStatusDeliveryDTO($ProductSupplyEvent->getId());
                $ProductSupply = $editProductSupplyHandler->handle($ProductSupplyStatusDeliveryDTO);

                if(true === $ProductSupply instanceof ProductSupply)
                {
                    $this->successful[] = $number;

                    $logger->info(
                        message: sprintf('Статус поставки %s изменен на %s',
                            $ProductSupply->getId(),
                            $ProductSupplyStatusDeliveryDTO->getStatus()
                        ),
                        context: [self::class.':'.__LINE__]
                    );

                    /** Создаем заявку для поступления продукции из поставки на склад */
                    $messageDispatch
                        ->dispatch(
                            message: new CreateWarehouseProductStockMessage(
                                supply: $ProductSupply->getId(),
                                profile: $DeliveryProductSupplyDTO->getProfile(),
                                comment: $DeliveryProductSupplyDTO->getComment(),
                            ),
                            transport: 'products-sign',
                        );
                }

                if(false === $ProductSupply instanceof ProductSupply)
                {
                    $this->unsuccessful[] = $number;
                }
            }

            /** Ответ в случае какой-либо ошибки при изменении статуса */
            if(null !== $this->unsuccessful)
            {
                $this->addFlash(
                    'page.edit',
                    'danger.update',
                    'products-supply.admin',
                    $this->unsuccessful,
                );

                return $this->redirectToReferer();
            }

            return new JsonResponse(
                [
                    'type' => 'success',
                    'header' => 'Изменение статуса поставок',
                    'message' => sprintf(
                        'Статусы успешно обновлены для поставок с номерами: %s',
                        implode(', ', $this->successful)),
                    'status' => 200,
                ],
                200,
            );
        }

        /**
         * @var ProductSupplyIdDTO $supply
         */
        foreach($DeliveryProductSupplyDTO->getSupplys() as $supply)
        {
            /** Активное событие поставки */
            $ProductSupplyEvent = $currentProductSupplyEventRepository->find($supply->getId());

            /**
             * Проверка перемещения поставки из корректного статуса
             */
            $currentProductSupplyStatus = $ProductSupplyEvent->getStatus();
            $previousStatus = $currentProductSupplyStatus->previous(self::STATUS);

            if(false === ($currentProductSupplyStatus->equals($previousStatus)))
            {
                return new JsonResponse(null, 400);
            }

            if(false === ($ProductSupplyEvent instanceof ProductSupplyEvent))
            {
                continue;
            }

            /**
             * Отправляем сокет для скрытия поставки у других менеджеров
             */
            $publish
                ->addData(['supply' => (string) $ProductSupplyEvent->getMain()])
                ->addData(['profile' => (string) $this->getCurrentProfileUid()])
                ->send('supplys');

            $this->numbers[] = $ProductSupplyEvent->getInvariable()->getNumber();
        }

        return $this->render([
            'form' => $DeliveryProductSupplyForm->createView(),
            'numbers' => $this->numbers,
            'status' => self::STATUS,
        ]);
    }
}