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

namespace BaksDev\Products\Supply\Repository\ProductsProduct\ProductChoice;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Category\Entity\CategoryProduct;
use BaksDev\Products\Category\Type\Id\CategoryProductUid;
use BaksDev\Products\Product\Entity\Category\ProductCategory;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Entity\Trans\ProductTrans;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Entity\User;
use BaksDev\Users\User\Type\Id\UserUid;
use Generator;

final  class ProductChoiceRepository implements ProductChoiceInterface
{
    private UserUid|false $user = false;

    private UserProfileUid|false $profile = false;

    private CategoryProductUid|false $category = false;

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
        private readonly UserProfileTokenStorageInterface $UserProfileTokenStorage,
    ) {}

    public function forUser(User|UserUid|null|false $user): self
    {
        if(empty($user))
        {
            $this->user = false;
            return $this;
        }

        if($user instanceof User)
        {
            $user = $user->getId();
        }

        $this->user = $user;

        return $this;
    }

    public function forProfile(UserProfile|UserProfileUid|null|false $profile): self
    {
        if(empty($profile))
        {
            $this->profile = false;
            return $this;
        }

        if($profile instanceof UserProfile)
        {
            $profile = $profile->getId();
        }

        $this->profile = $profile;

        return $this;
    }

    public function forCategory(CategoryProduct|CategoryProductUid $category): self
    {
        if($category instanceof CategoryProduct)
        {
            $category = $category->getId();
        }

        $this->category = $category;
        return $this;
    }

    /**
     * Метод возвращает все идентификаторы продуктов с названием, имеющиеся в наличии на данном складе
     */
    public function findAll(): Generator
    {
        $dbal = $this
            ->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();


        $dbal
            ->from(ProductStockTotal::class, 'stock')
            ->andWhere('stock.usr = :usr')
            ->setParameter(
                key: 'usr',
                value: $this->user instanceof UserUid ? $this->user : $this->UserProfileTokenStorage->getUser(),
                type: UserUid::TYPE,
            );

        /** Фильтр только по определенному профилю */
        if($this->profile instanceof UserProfileUid)
        {
            $dbal
                ->andWhere('stock.profile = :profile')
                ->setParameter(
                    key: 'profile',
                    value: $this->profile,
                    type: UserProfileUid::TYPE,
                );
        }

        $dbal->andWhere('(stock.total - stock.reserve)  > 0');

        $dbal->groupBy('stock.product');
        $dbal->addGroupBy('trans.name');

        $dbal->join(
            'stock',
            Product::class,
            'product',
            'product.id = stock.product',
        );

        if(true === $this->category instanceof CategoryProductUid)
        {
            $dbal
                ->join(
                    'product',
                    ProductCategory::class,
                    'category',
                    'category.event = product.event AND category.category = :category',
                )
                ->setParameter(
                    key: 'category',
                    value: $this->category,
                    type: CategoryProductUid::TYPE,
                );
        }

        $dbal->leftJoin(
            'product',
            ProductTrans::class,
            'trans',
            'trans.event = product.event AND trans.local = :local',
        );

        $dbal->addSelect('stock.product AS value');
        $dbal->addSelect('trans.name AS attr');
        $dbal->addSelect('(SUM(stock.total) - SUM(stock.reserve)) AS option');

        return $dbal
            ->enableCache('products-supply', 86400)
            ->fetchAllHydrate(ProductUid::class);
    }
}
