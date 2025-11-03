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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use BaksDev\Products\Supply\BaksDevProductsSupplyBundle;
use BaksDev\Products\Supply\Type\Event\ProductSupplyEventType;
use BaksDev\Products\Supply\Type\Event\ProductSupplyEventUid;
use BaksDev\Products\Supply\Type\Product\ProductSupplyProductType;
use BaksDev\Products\Supply\Type\Product\ProductSupplyProductUid;
use BaksDev\Products\Supply\Type\ProductSupplyType;
use BaksDev\Products\Supply\Type\ProductSupplyUid;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatusType;
use Symfony\Config\DoctrineConfig;

return static function(ContainerConfigurator $container, DoctrineConfig $doctrine) {

    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    /** Value Resolver */
    $services->set(ProductSupplyUid::class)->class(ProductSupplyUid::class);

    /** Types */
    $doctrine->dbal()->type(ProductSupplyUid::TYPE)->class(ProductSupplyType::class);
    $doctrine->dbal()->type(ProductSupplyEventUid::TYPE)->class(ProductSupplyEventType::class);
    $doctrine->dbal()->type(ProductSupplyProductUid::TYPE)->class(ProductSupplyProductType::class);
    $doctrine->dbal()->type(ProductSupplyStatus::TYPE)->class(ProductSupplyStatusType::class);

    $emDefault = $doctrine->orm()->entityManager('default')->autoMapping(true);

    $emDefault->mapping('products-supply')
        ->type('attribute')
        ->dir(BaksDevProductsSupplyBundle::PATH.'Entity')
        ->isBundle(false)
        ->prefix(BaksDevProductsSupplyBundle::NAMESPACE.'\\Entity')
        ->alias('products-supply');
};