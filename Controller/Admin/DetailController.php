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
use BaksDev\Core\Type\UidType\ParamConverter;
use BaksDev\Products\Product\Repository\ProductDetail\ProductDetailByConstInterface;
use BaksDev\Products\Product\Repository\ProductDetail\ProductDetailByConstResult;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusNew;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\Repository\CurrentProductSupplyEvent\CurrentProductSupplyEventInterface;
use BaksDev\Products\Supply\Repository\ProductSign\GroupProductSignsByProductSupply\GroupProductSignsByProductSupplyInterface;
use BaksDev\Products\Supply\Repository\ProductSupplyHistory\ProductSupplyHistoryInterface;
use BaksDev\Products\Supply\Type\ProductSign\Status\ProductSignStatusSupply;
use BaksDev\Products\Supply\Type\ProductSupplyUid;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\Collection\ProductSupplyStatusCompleted;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\ProductSupplyStatusCollection;
use BaksDev\Products\Supply\UseCase\Admin\Edit\EditProductSupplyDTO;
use BaksDev\Products\Supply\UseCase\Admin\Edit\EditProductSupplyForm;
use BaksDev\Products\Supply\UseCase\Admin\Edit\EditProductSupplyHandler;
use BaksDev\Products\Supply\UseCase\Admin\Edit\Product\EditProductSupplyProductDTO;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_SUPPLY_DETAIL')]
final class DetailController extends AbstractController
{
    #[Route('/admin/products/supply/detail/{id}', name: 'admin.supply.detail', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        CentrifugoPublishInterface $centrifugo,
        ProductSupplyStatusCollection $statuses,
        EditProductSupplyHandler $handler,

        CurrentProductSupplyEventInterface $currentProductSupplyEventRepository,
        ProductDetailByConstInterface $productDetailByConstRepository,
        GroupProductSignsByProductSupplyInterface $groupProductSignsByProductSupplyRepository,
        ProductSupplyHistoryInterface $productSupplyHistoryRepository,
        #[ParamConverter(ProductSupplyUid::class)] ProductSupplyUid $id,
    ): Response
    {

        /** Получаем активное событие */
        $ProductSupplyEvent = $currentProductSupplyEventRepository
            ->find($id);

        if(false === ($ProductSupplyEvent instanceof ProductSupplyEvent))
        {
            throw new RouteNotFoundException('404 Page Not Found');
        }

        /** Отправляем сокет для скрытия поставки у других менеджеров */
        $socket = $centrifugo
            ->addData(['supply' => (string) $ProductSupplyEvent->getMain()])
            ->addData(['profile' => (string) $this->getCurrentProfileUid()])
            ->send('remove');

        if($socket && $socket->isError())
        {
            return new JsonResponse($socket->getMessage());
        }

        $EditProductSupplyDTO = new EditProductSupplyDTO($ProductSupplyEvent->getId());
        $ProductSupplyEvent->getDto($EditProductSupplyDTO);

        /**
         * Продукты в поставке
         * @var EditProductSupplyProductDTO $product
         */
        foreach($EditProductSupplyDTO->getProduct() as $product)
        {
            if(true === $product->getProduct() instanceof ProductUid)
            {
                $ProductDetailByConstResult = $productDetailByConstRepository
                    ->product($product->getProduct())
                    ->offerConst($product->getOfferConst())
                    ->variationConst($product->getVariationConst())
                    ->modificationConst($product->getModificationConst())
                    ->findResult();

                if(true === ($ProductDetailByConstResult instanceof ProductDetailByConstResult))
                {
                    $product->setCard($ProductDetailByConstResult);
                }
            }
        }

        /** Форма редактирования поставки */
        $form = $this
            ->createForm(
                type: EditProductSupplyForm::class,
                data: $EditProductSupplyDTO,
                options: ['action' => $this->generateUrl('products-supply:admin.supply.detail',
                    ['id' => $ProductSupplyEvent->getMain()])]
            )
            ->handleRequest($request);

        if(false === $request->isXmlHttpRequest() && $form->isSubmitted() && false === $form->isValid())
        {
            $this->addFlash(
                'danger',
                'danger.update',
                'products-supply.admin',
            );

            return $this->redirectToReferer();
        }

        if($form->isSubmitted() && $form->isValid())
        {
            $this->refreshTokenForm($form);

            $handle = $handler->handle($EditProductSupplyDTO);

            if($handle instanceof ProductSupply)
            {
                $this->addFlash(
                    'success',
                    'success.update',
                    'products-supply.admin'
                );
            }
            else
            {
                $this->addFlash(
                    'danger',
                    'danger.update',
                    'products-supply.admin',
                    $handle);
            }

            return $this->redirectToReferer();
        }

        $productSignStatus = $ProductSupplyEvent->getStatus()->equals(ProductSupplyStatusCompleted::class)
            ? ProductSignStatusNew::STATUS
            : ProductSignStatusSupply::STATUS;

        /** Честные знаки */
        $ProductSigns = $groupProductSignsByProductSupplyRepository
            ->forSupply($ProductSupplyEvent->getMain())
            ->forStatus($productSignStatus)
            ->findAll();

        /** История изменений */
        $histories = $productSupplyHistoryRepository
            ->supply($ProductSupplyEvent->getMain())
            ->findAll();

        return $this->render(
            [
                'id' => $id,
                'form' => $form->createView(),
                'product_signs' => $ProductSigns,
                'statuses' => $statuses,
                'histories' => $histories,
            ]
        );
    }
}
