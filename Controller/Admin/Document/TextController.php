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
use BaksDev\Core\Type\UidType\ParamConverter;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Supply\Repository\ProductSign\ProductSignCodesBySupply\ProductSignCodesBySupplyInterface;
use BaksDev\Products\Supply\Type\ProductSupplyUid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Формирует документ с кодами Честного знака в формате .txt (small, big)
 */
#[AsController]
final class TextController extends AbstractController
{
    private const string SMALL = 'small';

    private const string BIG = 'big';

    #[Route(
        path: '/products/supply/document/sign/text/{size}/{supply}/{article}/{product}/{offer}/{variation}/{modification}/{part}',
        name: 'document.sign.text',
        methods: ['GET'])
    ]
    public function text(
        string $size,
        string $article,
        string $part,

        ProductSignCodesBySupplyInterface $productSignCodesBySupplyRepositories,

        #[ParamConverter(ProductSupplyUid::class)] ProductSupplyUid $supply,
        #[ParamConverter(ProductUid::class)] ProductUid $product,
        #[ParamConverter(ProductOfferConst::class)] ?ProductOfferConst $offer = null,
        #[ParamConverter(ProductVariationConst::class)] ?ProductVariationConst $variation = null,
        #[ParamConverter(ProductModificationConst::class)] ?ProductModificationConst $modification = null,
    ): Response
    {

        if(self::BIG !== $size && self::SMALL !== $size)
        {
            $this->addFlash('danger', 'Не верный формат для формирования txt файла с Честными знаками');
            return $this->redirectToReferer();
        }

        $codes = $productSignCodesBySupplyRepositories
            ->forPart($part)
            ->forSupply($supply)
            ->forProduct($product)
            ->forOffer($offer)
            ->forVariation($variation)
            ->forModification($modification)
            ->findAll();

        if($codes === false)
        {
            $this->addFlash('danger', 'Честных знаков не найдено');
            return $this->redirectToReferer();
        }

        $response = new StreamedResponse(function() use ($codes, $size) {

            $handle = fopen('php://output', 'wb+');

            /**
             * Запись данных
             */


            if(self::SMALL === $size)
            {
                foreach($codes as $code)
                {
                    fwrite($handle, $code->getSmallCode().PHP_EOL);
                }
            }

            if(self::BIG === $size)
            {
                foreach($codes as $code)
                {
                    fwrite($handle, $code->getBigCode().PHP_EOL);
                }
            }

            fclose($handle);
        });

        $fileName = sprintf('%s[%s].%s.%s', $article, $part, $size, 'txt');

        $response->headers->set('Content-Type', 'text/plain');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$fileName);

        return $response;
    }
}