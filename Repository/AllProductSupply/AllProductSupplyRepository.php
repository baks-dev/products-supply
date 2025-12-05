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

namespace BaksDev\Products\Supply\Repository\AllProductSupply;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Products\Supply\Entity\Event\Invariable\ProductSupplyInvariable;
use BaksDev\Products\Supply\Entity\Event\Personal\ProductSupplyPersonal;
use BaksDev\Products\Supply\Entity\Event\Product\ProductSupplyProduct;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\ProductSupplyStatusInterface;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Entity\User;
use BaksDev\Users\User\Type\Id\UserUid;
use Generator;

final class AllProductSupplyRepository implements AllProductSupplyInterface
{
    private SearchDTO|false $search = false;

    private UserUid|false $user = false;

    private UserProfileUid|false $profile = false;

    private ProductSupplyStatus|false $status = false;

    private ?int $limit = null;

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
    ) {}

    public function search(SearchDTO $search): self
    {
        $this->search = $search;
        return $this;
    }

    public function setLimit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function status(ProductSupplyStatus|ProductSupplyStatusInterface|string $status): self
    {
        $this->status = new ProductSupplyStatus($status);
        return $this;
    }

    public function forUser(User|UserUid $user): self
    {
        if($user instanceof User)
        {
            $user = $user->getId();
        }

        $this->user = $user;
        return $this;
    }

    public function forProfile(UserProfile|UserProfileUid $profile): self
    {
        if($profile instanceof UserProfile)
        {
            $profile = $profile->getId();
        }

        $this->profile = $profile;
        return $this;
    }

    public function findAll(): Generator|false
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class);

        $dbal
            ->addSelect('product_supply.id')
            ->addSelect('product_supply.event')
            ->from(ProductSupply::class, 'product_supply');

        $status = $this->status instanceof ProductSupplyStatus;

        $dbal
            ->addSelect('product_supply_event.status AS supply_status')
            ->join(
                'product_supply',
                ProductSupplyEvent::class,
                'product_supply_event',
                'product_supply_event.id = product_supply.event'.
                ($status ? ' AND product_supply_event.status = :status' : '')
            );

        if($status)
        {
            $dbal->setParameter(
                key: 'status',
                value: $this->status,
                type: ProductSupplyStatus::TYPE,
            );
        }

        /** Invariable */
        $dbal
            ->addSelect('product_supply_invariable.number AS supply_number')
            ->join(
                'product_supply_event',
                ProductSupplyInvariable::class,
                'product_supply_invariable',
                'product_supply_invariable.event = product_supply_event.id'
            );

        /**
         * Пользователь
         */

        $profile = $this->profile instanceof UserProfileUid;

        $dbal
            //            ->addSelect('product_supply_personal.usr AS supply_usr')
            //            ->addSelect('product_supply_personal.profile AS supply_profile')
            ->join(
                'product_supply_event',
                ProductSupplyPersonal::class,
                'product_supply_personal',
                '
                    product_supply_personal.event = product_supply_event.id AND
                    product_supply_personal.usr = :usr'.
                (
                $profile
                    ? ' AND product_supply_personal.profile = :profile'
                    : ' AND product_supply_personal.profile IS NULL'
                )
            )->setParameter(
                key: 'usr',
                value: $this->user,
                type: UserUid::TYPE,
            );

        if($profile)
        {
            $dbal->setParameter(
                key: 'profile',
                value: $this->profile,
                type: UserProfileUid::TYPE,
            );
        }

        /** Продукты в Поставке */
        $dbal
            ->join(
                'product_supply_event',
                ProductSupplyProduct::class,
                'product_supply_product',
                'product_supply_product.event = product_supply_event.id'
            );

        $dbal->addSelect(
            "JSON_AGG
			( 
					JSONB_BUILD_OBJECT
					(
						'product', product_supply_product.product,
						'offer_const', product_supply_product.offer_const,
						'variation_const', product_supply_product.variation_const,
						'modification_const', product_supply_product.modification_const
					)
			)
			AS supply_products",
        );

        if($this->search && $this->search->getQuery())
        {
            $dbal
                ->createSearchQueryBuilder($this->search)
                ->addSearchLike('product_supply_product.barcode')
                ->addSearchLike('product_supply_invariable.number');
        }

        if(null !== $this->limit)
        {
            $dbal->setMaxResults($this->limit);
        }

        $dbal->allGroupByExclude();

        $result = $dbal->fetchAllHydrate(AllProductSupplyResult::class);

        return true === $result->valid() ? $result : false;
    }
}