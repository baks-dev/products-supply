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

namespace BaksDev\Products\Supply\Controller\Admin\Status;

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
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\Collection\ProductSupplyStatusClearance;
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

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_SUPPLY_STATUS')]
final class DeliveryController extends AbstractController
{
    /** Коллекция неудачных попыток обновления статуса поставок */
    private array|null $unsuccessful = null;

    /** Номера контейнеров обрабатываемых поставок */
    private array $numbers = [];

    /**
     * Доставка поставок
     */
    #[Route('/admin/products/supply/delivery', name: 'admin.supply.delivery', methods: ['GET', 'POST'])]
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
                ['action' => $this->generateUrl('products-supply:admin.supply.delivery')],
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
                /** Скрываем поставку у всех пользователей */
                $publish
                    ->addData(['supply' => (string) $ProductSupplyIdDTO->getId()])
                    ->send('supplys');

                $ProductSupplyEvent = $currentProductSupplyEventRepository
                    ->find($ProductSupplyIdDTO->getId());

                if(false === ($ProductSupplyEvent instanceof ProductSupplyEvent))
                {
                    $this->unsuccessful[] = $ProductSupplyIdDTO->getId();

                    $logger->critical(
                        message: sprintf('Не найдено событие ProductSupplyEvent по ID: %s',
                            $ProductSupplyIdDTO->getId()),
                        context: [self::class.':'.__LINE__]
                    );

                    continue;
                }

                /** Проверяем, что поставка перемещена из статуса clearance (Растормаживается) */
                if(false === $ProductSupplyEvent->getStatus()->equals(ProductSupplyStatusClearance::class))
                {
                    $this->unsuccessful[] = $ProductSupplyIdDTO->getId();

                    $logger->critical(
                        message: sprintf('Попытка присвоить ГТД для поставки %s (%s) не из статуса: %s',
                            $ProductSupplyEvent->getMain(),
                            $ProductSupplyEvent->getInvariable()->getContainer(),
                            ProductSupplyStatusClearance::STATUS
                        ),
                        context: [self::class.':'.__LINE__]
                    );

                    continue;
                }

                $ProductSupplyStatusDeliveryDTO = new ProductSupplyStatusDeliveryDTO($ProductSupplyEvent->getId());
                $handle = $editProductSupplyHandler->handle($ProductSupplyStatusDeliveryDTO);

                if(true === $handle instanceof ProductSupply)
                {
                    $logger->info(
                        message: sprintf('Статус поставки %s изменен на %s',
                            $handle->getId(), $ProductSupplyStatusDeliveryDTO->getStatus()),
                        context: [self::class.':'.__LINE__]
                    );

                    /** Создаем заявку для поступления продукции из поставки на склад */
                    $messageDispatch
                        ->dispatch(
                            message: new CreateWarehouseProductStockMessage(
                                supply: $handle->getId(),
                                profile: $DeliveryProductSupplyDTO->getProfile(),
                                comment: $DeliveryProductSupplyDTO->getComment(),
                            ),
                            transport: 'products-sign',
                        );
                }

                if(false === $handle instanceof ProductSupply)
                {
                    $this->unsuccessful[] = $ProductSupplyIdDTO->getId();
                }
            }

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
                    'message' => 'Статусы поставок успешно обновлены',
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
            $supplyEvent = $currentProductSupplyEventRepository->find($supply->getId());

            if(false === ($supplyEvent instanceof ProductSupplyEvent))
            {
                continue;
            }

            $this->numbers[] = $supplyEvent->getInvariable()->getContainer();
        }

        return $this->render([
            'form' => $DeliveryProductSupplyForm->createView(),
            'numbers' => $this->numbers,
            'status' => ProductSupplyStatusDelivery::STATUS,
        ]);
    }
}
