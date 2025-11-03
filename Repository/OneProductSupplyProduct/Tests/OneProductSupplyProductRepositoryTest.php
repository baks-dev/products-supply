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

namespace BaksDev\Products\Supply\Repository\OneProductSupplyProduct\Tests;

use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Supply\Repository\OneProductSupplyProduct\OneProductSupplyProductInterface;
use BaksDev\Products\Supply\Repository\OneProductSupplyProduct\OneProductSupplyProductResult;
use BaksDev\Products\Supply\Type\ProductSupplyUid;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

#[Group('products-supply')]
#[When(env: 'test')]
class OneProductSupplyProductRepositoryTest extends KernelTestCase
{
    public function testRepository(): void
    {
        self::assertTrue(true);

        /** @var OneProductSupplyProductInterface $OneProductSupplyProductInterface */
        $OneProductSupplyProductInterface = self::getContainer()->get(OneProductSupplyProductInterface::class);

        $result = $OneProductSupplyProductInterface
            ->forSupply(new ProductSupplyUid)
            ->forProduct(new ProductUid)
            ->forOffer(new ProductOfferConst)
            ->forVariation(new ProductVariationConst)
            ->forModification(new ProductModificationConst)
            ->find();

        if(false !== $result)
        {
            // Вызываем все геттеры
            $reflectionClass = new \ReflectionClass(OneProductSupplyProductResult::class);
            $methods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);

            foreach($methods as $method)
            {
                // Методы без аргументов
                if($method->getNumberOfParameters() === 0)
                {
                    // Вызываем метод
                    $data = $method->invoke($result);
                }
            }
        }
    }
}