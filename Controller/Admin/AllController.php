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

use BaksDev\Centrifugo\Services\Token\TokenUserGenerator;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Form\Search\SearchForm;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Supply\Forms\ProductSupplyFilter\ProductSupplyFilterDTO;
use BaksDev\Products\Supply\Forms\ProductSupplyFilter\ProductSupplyFilterForm;
use BaksDev\Products\Supply\Repository\AllProductSupply\AllProductSupplyInterface;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\ProductSupplyStatusCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_SUPPLY')]
final class AllController extends AbstractController
{
    #[Route('/admin/products/supply/all/{page<\d+>}', name: 'admin.supply.all', methods: ['GET', 'POST',])]
    public function index(
        Request $request,
        AllProductSupplyInterface $allProductSupplyRepository,
        ProductSupplyStatusCollection $statuses,
        TokenUserGenerator $tokenUserGenerator,
        int $page = 0,
    ): Response
    {

        /** Поиск */
        $searchForm = $this
            ->createForm(
                type: SearchForm::class,
                data: $search = new SearchDTO(),
                options: ['action' => $this->generateUrl('products-supply:admin.supply.all')],
            )
            ->handleRequest($request);

        /** Фильтр */
        $filterForm = $this
            ->createForm(
                type: ProductSupplyFilterForm::class,
                data: $filter = new ProductSupplyFilterDTO(),
                options: ['action' => $this->generateUrl('products-supply:admin.supply.all')],
            )
            ->handleRequest($request);

        /** Получаем список */
        $supplys = $allProductSupplyRepository
            ->filter($filter)
            ->search($search)
            ->findPaginator();

        return $this->render(
            [
                'query' => $supplys,
                'status' => $statuses->cases(),
                'token' => $tokenUserGenerator->generate($this->getUsr()),
                'search' => $searchForm->createView(),
                'filter' => $filterForm->createView(),
            ],
        );
    }
}
