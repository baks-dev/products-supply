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

use BaksDev\Centrifugo\Services\Token\TokenUserGenerator;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Form\Search\SearchForm;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Supply\Repository\AllProductSupply\AllProductSupplyInterface;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\Collection\ProductSupplyStatusCancel;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\Collection\ProductSupplyStatusCompleted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_SUPPLY_INDEX')]
final class IndexController extends AbstractController
{
    private array $supplys = [];

    private array $statuses = [];

    /**
     * Управление заказами (Канбан)
     */
    #[Route('/admin/products/supply', name: 'admin.supply.index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        TokenUserGenerator $tokenUserGenerator,
        AllProductSupplyInterface $allProductSupplyRepository,
    ): Response
    {
        /* Поиск */
        $search = new SearchDTO();
        $searchForm = $this->createForm(SearchForm::class, $search);
        $searchForm->handleRequest($request);

        /** @var ProductSupplyStatus $status */
        foreach(ProductSupplyStatus::cases() as $status)
        {
            if($status->equals(ProductSupplyStatusCancel::class))
            {
                continue;
            }

            if($status->equals(ProductSupplyStatusCompleted::class))
            {
                $allProductSupplyRepository->setLimit(10);
            }

            $productSupply = $allProductSupplyRepository
                ->search($search)
                ->status($status)
                ->forUser($this->getUsr())
                ->forProfile($this->getProfileUid())
                ->findAll();

            /** Получаем список поставок с ключом их статуса */
            $this->supplys[$status->getStatusValue()] =
                (false !== $productSupply) ? iterator_to_array($productSupply) : null;

            /** Текущие статусы поставок */
            $this->statuses[$status->getStatus()::priority()] = $status->getStatus();
        }

        return $this->render(
            [
                'query' => $this->supplys,
                'statuses' => $this->statuses,
                'search' => $searchForm->createView(),
                'token' => $tokenUserGenerator->generate($this->getUsr()),
                'current_profile' => $this->getCurrentProfileUid(),
            ]
        );
    }
}
