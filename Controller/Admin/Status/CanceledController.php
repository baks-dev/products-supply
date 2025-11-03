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

namespace BaksDev\Products\Supply\Controller\Admin\Status;

use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\Forms\Statuses\Canceled\CanceledProductSupplyDTO;
use BaksDev\Products\Supply\Forms\Statuses\Canceled\CanceledProductSupplyForm;
use BaksDev\Products\Supply\Forms\Statuses\ProductSupplyIdDTO;
use BaksDev\Products\Supply\Repository\CurrentProductSupplyEvent\CurrentProductSupplyEventInterface;
use BaksDev\Products\Supply\UseCase\Admin\Cancel\ProductSupplyStatusCanceledDTO;
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
final class CanceledController extends AbstractController
{
    /** Коллекция неудачных попыток обновления статуса поставок */
    private array|null $unsuccessful = null;

    private array $numbers = [];

    /**
     * Отмена поставок
     */
    #[Route('/admin/products/supply/canceled', name: 'admin.supply.canceled', methods: ['GET', 'POST'])]
    public function canceled(
        #[Target('productsSupplyLogger')] LoggerInterface $logger,
        Request $request,
        CurrentProductSupplyEventInterface $currentProductSupplyEventRepository,
        EditProductSupplyHandler $editProductSupplyHandler,
        CentrifugoPublishInterface $publish,
    ): Response
    {
        $canceledProductSuppliesForm = $this
            ->createForm(
                type: CanceledProductSupplyForm::class,
                data: $CancelProductSupplyDTO = new CanceledProductSupplyDTO(),
                options: ['action' => $this->generateUrl('products-supply:admin.supply.canceled')],
            )
            ->handleRequest($request);

        if(
            $canceledProductSuppliesForm->isSubmitted() &&
            $canceledProductSuppliesForm->isValid() &&
            $canceledProductSuppliesForm->has('canceled')
        )
        {
            $this->refreshTokenForm($canceledProductSuppliesForm);

            /**
             * @var ProductSupplyIdDTO $ProductSupplyIdDTO
             */
            foreach($CancelProductSupplyDTO->getSupplys() as $ProductSupplyIdDTO)
            {
                /** Активное событие поставки */
                $supplyEvent = $currentProductSupplyEventRepository->find($ProductSupplyIdDTO->getId());

                if(false === ($supplyEvent instanceof ProductSupplyEvent))
                {
                    $this->unsuccessful[] = $ProductSupplyIdDTO->getId();

                    $logger->critical(
                        message: sprintf('Не найдено событие ProductSupplyEvent по ID: %s',
                            $ProductSupplyIdDTO->getId()),
                        context: [self::class.':'.__LINE__],
                    );

                    continue;
                }

                $ProductSupplyStatusCanceledDTO = new ProductSupplyStatusCanceledDTO($supplyEvent->getId());
                $ProductSupplyStatusCanceledDTO->setComment($CancelProductSupplyDTO->getComment());

                $handle = $editProductSupplyHandler->handle($ProductSupplyStatusCanceledDTO);

                if(true === $handle instanceof ProductSupply)
                {
                    $logger->info(
                        message: sprintf('Статус поставки %s изменен на %s',
                            $handle->getId(), $ProductSupplyStatusCanceledDTO->getStatus()),
                        context: [self::class.':'.__LINE__],
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
                    'page.cancel',
                    'danger.cancel',
                    'products-supply.admin',
                    $this->unsuccessful,
                );

                return $this->redirectToReferer();
            }

            return new JsonResponse(
                [
                    'type' => 'success',
                    'header' => 'Отмена поставок',
                    'message' => 'Статусы поставок успешно обновлены',
                    'status' => 200,
                ],
                200,
            );
        }

        /**
         * @var ProductSupplyIdDTO $supply
         */
        foreach($CancelProductSupplyDTO->getSupplys() as $supply)
        {
            /** Активное событие поставки */
            $supplyEvent = $currentProductSupplyEventRepository->find($supply->getId());

            if(false === ($supplyEvent instanceof ProductSupplyEvent))
            {
                continue;
            }

            /**
             * Отправляем сокет для скрытия поставки у других менеджеров
             */
            $publish
                ->addData(['supply' => (string) $supplyEvent->getMain()])
                ->addData(['profile' => (string) $this->getCurrentProfileUid()])
                ->send('supplys');

            $this->numbers[] = $supplyEvent->getInvariable()->getContainer();
        }

        return $this->render([
            'form' => $canceledProductSuppliesForm->createView(),
            'numbers' => $this->numbers,
        ]);
    }
}