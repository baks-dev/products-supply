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
use BaksDev\Products\Supply\UseCase\Admin\File\ProductSupplyFilesDTO;
use BaksDev\Products\Supply\UseCase\Admin\File\ProductSupplyFilesForm;
use BaksDev\Products\Supply\UseCase\Admin\File\ProductSupplyFilesHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Загрузка Честных знаков на сервер
 */
#[AsController]
#[RoleSecurity('ROLE_PRODUCT_SUPPLY_NEW')]
final class SignController extends AbstractController
{
    public const string NAME = 'admin.supply.sign';

    #[Route(path: '/admin/products/supply/sign', name: self::NAME, methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        ProductSupplyFilesHandler $ProductSupplyFilesHandler,
    ): Response
    {
        $ProductSupplyFilesForm = $this->createForm(
            ProductSupplyFilesForm::class,
            $ProductSupplyFilesDTO = new ProductSupplyFilesDTO(),
            ['action' => $this->generateUrl('products-supply:'.self::NAME)]
        )
            ->handleRequest($request);

        if(
            $ProductSupplyFilesForm->isSubmitted() &&
            $ProductSupplyFilesForm->isValid() &&
            $ProductSupplyFilesForm->has('product_supply_files')
        )
        {
            $this->refreshTokenForm($ProductSupplyFilesForm);

            $handle = $ProductSupplyFilesHandler->handle($ProductSupplyFilesDTO);

            $this->addFlash(
                true === $handle ? 'success' : 'danger',
                true === $handle ? 'file.success' : 'file.danger',
                'products-supply.admin'
            );

            return $this->redirectToReferer();
        }

        return $this->render(['form' => $ProductSupplyFilesForm->createView()]);
    }
}
