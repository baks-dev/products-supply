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

namespace BaksDev\Products\Supply\Controller\Admin;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\UseCase\Admin\File\ProductSupplyFilesHandler;
use BaksDev\Products\Supply\UseCase\Admin\New\NewProductSupplyDTO;
use BaksDev\Products\Supply\UseCase\Admin\New\NewProductSupplyForm;
use BaksDev\Products\Supply\UseCase\Admin\New\NewProductSupplyHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Создает поставку и ОПЦИОНАЛЬНО загружает Честные знаки
 */
#[AsController]
#[RoleSecurity('ROLE_PRODUCT_SUPPLY_NEW')]
final class NewController extends AbstractController
{
    public const string NAME = 'admin.supply.new';

    #[Route(path: '/admin/products/supply/new', name: self::NAME, methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        NewProductSupplyHandler $productSupplyHandler,
        ProductSupplyFilesHandler $ProductSupplyFilesHandler,
    ): Response
    {
        $NewProductSupplyDTO = new NewProductSupplyDTO();

        $ProductSupplyFilesForm = $this->createForm(
            type: NewProductSupplyForm::class,
            data: $NewProductSupplyDTO,
            options: ['action' => $this->generateUrl('products-supply:'.self::NAME)],
        )
            ->handleRequest($request);

        if(
            $ProductSupplyFilesForm->isSubmitted() &&
            $ProductSupplyFilesForm->isValid() &&
            $ProductSupplyFilesForm->has('product_supply_new')
        )
        {
            $this->refreshTokenForm($ProductSupplyFilesForm);

            /**
             * Создаем новую поставку
             */

            $ProductSupply = $productSupplyHandler->handle($NewProductSupplyDTO);

            $this->addFlash(
                $ProductSupply instanceof ProductSupply ? 'success' : 'danger',
                $ProductSupply instanceof ProductSupply ? 'success.new' : 'danger.new',
                'products-supply.admin',
                $ProductSupply instanceof ProductSupply ? null : $ProductSupply,
            );

            /**
             * Загружаем файлы с честными знаками
             */

            $ProductSupplyFilesDTO = $NewProductSupplyDTO->getFiles();

            if(false === empty($ProductSupplyFilesDTO->getFiles()->current()->files))
            {
                /**  */
                $handleFiles = $ProductSupplyFilesHandler->handle($ProductSupplyFilesDTO);

                $this->addFlash(
                    true === $handleFiles ? 'success' : 'danger',
                    true === $handleFiles ? 'file.success' : 'file.danger',
                    'products-supply.admin',
                );
            }

            return $this->redirectToReferer();
        }

        return $this->render(['form' => $ProductSupplyFilesForm->createView()]);
    }
}