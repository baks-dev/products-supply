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

namespace BaksDev\Products\Supply\Controller\Admin\Document;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Type\UidType\ParamConverter;
use BaksDev\Products\Supply\Repository\ProductSign\ProductSignCodesBySupply\ProductSignCodesBySupplyInterface;
use BaksDev\Products\Supply\Type\ProductSupplyUid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Формирует документ для печати с кодами Честного знака
 */
#[AsController]
#[RoleSecurity(['ROLE_PRODUCT_SUPPLY_EDIT'])]
final class PrintController extends AbstractController
{
    #[Route(
        path: '/product/supply/document/sign/print/{supply}',
        name: 'document.sign.print',
        methods: ['GET'])
    ]
    public function print(
        ProductSignCodesBySupplyInterface $productSignCodesBySupplyRepositories,
        #[ParamConverter(ProductSupplyUid::class)] ProductSupplyUid $supply,
    ): Response
    {
        $codes = $productSignCodesBySupplyRepositories
            ->forSupply($supply)
            ->findAll();

        return $this->render(
            ['codes' => $codes],
            dir: 'admin.supply.print',
        );
    }
}
