<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
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
 */

declare(strict_types=1);

namespace BaksDev\Manufacture\Part\Repository\InfoManufacturePart;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Type\Id\ManufacturePartUid;
use BaksDev\Products\Category\Entity\ProductCategory;
use BaksDev\Products\Category\Entity\Trans\ProductCategoryTrans;
use BaksDev\Users\Profile\Group\Entity\Users\ProfileGroupUsers;
use BaksDev\Users\Profile\UserProfile\Entity\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\UsersTable\Entity\Actions\Event\UsersTableActionsEvent;
use BaksDev\Users\UsersTable\Entity\Actions\Trans\UsersTableActionsTrans;

final class InfoManufacturePart implements InfoManufacturePartInterface
{
    private DBALQueryBuilder $DBALQueryBuilder;

    public function __construct(DBALQueryBuilder $DBALQueryBuilder,)
    {
        $this->DBALQueryBuilder = $DBALQueryBuilder;
    }


    /**
     * Возвращает информацию о производственной партии
     */
    public function fetchInfoManufacturePartAssociative(
        ManufacturePartUid $part,
        UserProfileUid $profile,
        ?UserProfileUid $authority
    ): ?array
    {
        $qb = $this->DBALQueryBuilder->createQueryBuilder(self::class)->bindLocal();

        $qb->select('part.id');
        $qb->addSelect('part.quantity');
        $qb->addSelect('part.number');

        $qb->from(ManufacturePart::TABLE, 'part');

        $qb
            ->where('part.id = :part')
            ->setParameter('part', $part, ManufacturePartUid::TYPE);


        $qb->addSelect('part_event.id AS event');

        $qb->addSelect('part_event.status');
        $qb->addSelect('part_event.complete');

        $qb->join(
            'part', ManufacturePartEvent::TABLE,
            'part_event',
            'part_event.id = part.event'
        );


        /** Партии других пользователей */
        if($authority)
        {
            /** Профили доверенных пользователей */
            $qb->leftJoin(
                'part',
                ProfileGroupUsers::TABLE,
                'profile_group_users',
                'profile_group_users.authority = :authority'
            );

            $qb
                //->andWhere('(part_event.profile = :profile OR part_event.profile = :authority OR part_event.profile = profile_group_users.profile)')
                ->andWhere('part_event.profile = profile_group_users.profile')
                ->setParameter('authority', $authority, UserProfileUid::TYPE)
                //->setParameter('profile', $profile, UserProfileUid::TYPE)
            ;
        }
        else
        {
            $qb
                ->andWhere('part_event.profile = :profile')
                ->setParameter('profile', $profile, UserProfileUid::TYPE);

        }

        

//        $qb->andWhere('part_event.profile = :profile');
//        $qb->setParameter('profile', $profile, UserProfileUid::TYPE);


        /** Ответственное лицо (Профиль пользователя) */

        $qb->addSelect('users_profile.event as users_profile_event');
        $qb->leftJoin(
            'part_event',
            UserProfile::TABLE,
            'users_profile',
            'users_profile.id = part_event.profile'
        );

        $qb->addSelect('users_profile_personal.username AS users_profile_username');
        $qb->leftJoin(
            'users_profile',
            UserProfilePersonal::TABLE,
            'users_profile_personal',
            'users_profile_personal.event = users_profile.event'
        );

        /**
         * Производственный процесс
         */
        $qb->addSelect('action_trans.name AS action_name');
        $qb->leftJoin(
            'part_event',
            UsersTableActionsTrans::TABLE,
            'action_trans',
            'action_trans.event = part_event.action AND action_trans.local = :local'
        );


        /** Категория производства */

        $qb->addSelect('actions_event.id AS actions_event');
        $qb->leftJoin(
            'part_event',
            UsersTableActionsEvent::TABLE,
            'actions_event',
            'actions_event.id = part_event.action'
        );

        $qb->addSelect('category.id AS category_id');
        $qb->leftJoin(
            'actions_event',
            ProductCategory::TABLE,
            'category',
            'category.id = actions_event.category'
        );

        $qb->addSelect('trans.name AS category_name');
        $qb->leftJoin(
            'category',
            ProductCategoryTrans::TABLE,
            'trans',
            'trans.event = category.event AND trans.local = :local'
        )
            ->bindLocal();


        return $qb
            ->enableCache('manufacture-part', 86400)
            ->fetchAssociative();
    }
}