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

namespace BaksDev\Products\Supply\Controller\Admin;

use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\UseCase\Admin\NewEdit\NewEditProductSupplyDTO;
use BaksDev\Products\Supply\UseCase\Admin\NewEdit\NewEditProductSupplyForm;
use BaksDev\Products\Supply\UseCase\Admin\NewEdit\NewEditProductSupplyHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Создает поставку и ОПЦИОНАЛЬНО загружает Честные знаки
 */
#[AsController]
#[RoleSecurity('ROLE_PRODUCT_SUPPLY_NEW')]
final class AddController extends AbstractController
{
    public const string NAME = 'admin.newedit.new';

    #[Route(path: '/admin/products/supply/add', name: self::NAME, methods: ['GET', 'POST'])]
    public function new(
        #[Target('productsSupplyLogger')] LoggerInterface $logger,
        Request $request,
        CentrifugoPublishInterface $centrifugo,
        NewEditProductSupplyHandler $NewEditProductSupplyHandler,
    ): Response
    {
        $AddProductSupplyDTO = new NewEditProductSupplyDTO();

        $AddProductSupplyForm = $this
            ->createForm(
                type: NewEditProductSupplyForm::class,
                data: $AddProductSupplyDTO,
                options: ['action' => $this->generateUrl('products-supply:'.self::NAME)],
            )
            ->handleRequest($request);

        if(
            $AddProductSupplyForm->isSubmitted() &&
            $AddProductSupplyForm->isValid() &&
            $AddProductSupplyForm->has('product_supply_add')
        )
        {
            $this->refreshTokenForm($AddProductSupplyForm);

            /**
             * Создаем новую поставку с блокировкой изменений
             */

            $ProductSupply = $NewEditProductSupplyHandler->handle($AddProductSupplyDTO);

            if(false === $ProductSupply instanceof ProductSupply)
            {
                $flash = $this->addFlash(
                    'danger',
                    'danger.new',
                    'products-supply.admin',
                );

                return $flash ?: $this->redirectToRoute('products-supply:admin.supply.index');
            }


            /**
             * Блокируем перетаскивание карточки
             */

            $socket = $centrifugo
                ->addData(['supply' => (string) $ProductSupply->getId()])
                ->addData(['lock' => true]) // разблокировка перетаскивания карточки на UI
                ->send('supplys'); // канал поставок

            if($socket && $socket->isError())
            {
                $logger->warning(
                    message: 'Ошибка при отправке информации о блокировке в Centrifugo',
                    context: [
                        self::class.':'.__LINE__,
                        $socket->getMessage(),
                        $ProductSupply->getId(),
                    ],
                );
            }

            /**
             * Результат выполнения запроса
             */

            $flash = $this->addFlash(
                'success',
                'success.new',
                'products-supply.admin',
            );

            return $flash ?: $this->redirectToRoute('products-supply:admin.supply.index');
        }

        return $this->render(['form' => $AddProductSupplyForm->createView()]);
    }
}