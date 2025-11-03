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

namespace BaksDev\Products\Supply\Repository\ProductSign\OneProductSignSupply;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\Event\Supply\ProductSignSupply;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
use BaksDev\Products\Sign\Entity\Modify\ProductSignModify;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Supply\Type\ProductSign\Status\ProductSignStatusUndefined;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Entity\User;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\ORM\Query\Expr\Join;
use InvalidArgumentException;

final class OneProductSignEventRepository implements OneProductSignEventInterface
{
    private ?UserUid $user = null;

    private ?UserProfileUid $profile = null;

    /** Продукция */

    private ProductUid|false $product = false;

    private ProductOfferConst|false $offer = false;

    private ProductVariationConst|false $variation = false;

    private ProductModificationConst|false $modification = false;

    public function __construct(
        private readonly ORMQueryBuilder $ORMQueryBuilder
    ) {}

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

    public function forProduct(Product|ProductUid|false $product): self
    {
        if($product instanceof Product)
        {
            $product = $product->getId();
        }

        $this->product = $product;
        return $this;
    }

    public function forOfferConst(ProductOfferConst|false $offer): self
    {
        $this->offer = $offer;
        return $this;
    }

    public function forVariationConst(ProductVariationConst|false $variation): self
    {
        $this->variation = $variation;
        return $this;
    }

    public function forModificationConst(ProductModificationConst|false $modification): self
    {
        $this->modification = $modification;
        return $this;
    }

    /**
     * Метод возвращает один Честный знак на указанную продукцию со статусом Undefined «Не определен»
     */
    public function getOne(): ProductSignEvent|string|false
    {
        if(
            false === ($this->user instanceof UserUid) ||
            false === ($this->profile instanceof UserProfileUid)
        )
        {
            throw new InvalidArgumentException('Не определено обязательное свойство user, profile');
        }

        $orm = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $orm->from(ProductSignInvariable::class, 'invariable');

        $orm->join(
            ProductSign::class,
            'main',
            Join::WITH,
            'main.id = invariable.main'
        );

        $orm
            ->select('event')
            ->join(
                ProductSignEvent::class,
                'event',
                Join::WITH,
                '
                    event.id = main.event AND
                    event.status = :status
            ')
            ->setParameter(
                key: 'status',
                value: ProductSignStatusUndefined::class,
                type: ProductSignStatus::TYPE,
            );

        /**
         * Поставка
         */
        $orm
            ->leftJoin(
                ProductSignSupply::class,
                'supply',
                Join::WITH,
                'supply.event = event.id
            ');

        /** Поставка НЕ ДОЛЖНА быть назначена! */
        $orm->where('supply.event IS NULL');

        $orm
            ->leftJoin(
                ProductSignModify::class,
                'modify',
                Join::WITH,
                'modify.event = main.event',
            );

        /**
         * User
         */

        $orm
            ->andWhere('invariable.usr = :usr')
            ->setParameter(
                key: 'usr',
                value: $this->user,
                type: UserUid::TYPE,
            );

        /**
         * Profile
         */

        $orm
            ->andWhere('(invariable.seller IS NULL OR invariable.seller = :seller)')
            ->setParameter(
                key: 'seller',
                value: $this->profile,
                type: UserProfileUid::TYPE,
            );

        /**
         * Product
         */

        if($this->offer instanceof ProductOfferConst)
        {
            $orm
                ->andWhere('invariable.product = :product')
                ->setParameter(
                    key: 'product',
                    value: $this->product,
                    type: ProductUid::TYPE,
                );
        }
        else
        {
            $orm->andWhere('invariable.product IS NULL');
        }

        /**
         * Offer
         */

        if($this->offer instanceof ProductOfferConst)
        {
            $orm
                ->andWhere('invariable.offer = :offer')
                ->setParameter(
                    key: 'offer',
                    value: $this->offer,
                    type: ProductOfferConst::TYPE,
                );
        }
        else
        {
            $orm->andWhere('invariable.offer IS NULL');
        }

        /**
         * Variation
         */

        if($this->variation instanceof ProductVariationConst)
        {
            $orm
                ->andWhere('invariable.variation = :variation')
                ->setParameter(
                    key: 'variation',
                    value: $this->variation,
                    type: ProductVariationConst::TYPE,
                );
        }
        else
        {
            $orm->andWhere('invariable.variation IS NULL');
        }

        /**
         * Modification
         */

        if($this->modification instanceof ProductModificationConst)
        {
            $orm
                ->andWhere('invariable.modification = :modification')
                ->setParameter(
                    key: 'modification',
                    value: $this->modification,
                    type: ProductModificationConst::TYPE,
                );
        }
        else
        {
            $orm->andWhere('invariable.modification IS NULL');
        }


        /**
         *  Сортировка по профилю:
         *  если у владельца имеется Честный знак - возвращает
         *  если нет у владельца - берет у партнера
         */
        $orm
            ->addOrderBy("CASE invariable.profile WHEN :profile THEN false ELSE true END")
            ->setParameter(
                key: 'profile',
                value: $this->profile,
                type: UserProfileUid::TYPE,
            );

        /** Сортируем по дате, выбирая самый старый знак и мешок */
        $orm->addOrderBy('modify.modDate');
        $orm->addOrderBy('invariable.part');

        $orm->setMaxResults(1);

        try
        {
            $result = $orm->getOneOrNullResult();
            return $result ?: false;
        }
        catch(\Exception $exception)
        {
            return $exception->getMessage();
        }
    }
}
