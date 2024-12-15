<?php
/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
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
use BaksDev\Products\Category\Entity\CategoryProduct;
use BaksDev\Products\Category\Entity\Trans\CategoryProductTrans;
use BaksDev\Users\Profile\Group\Entity\Users\ProfileGroupUsers;
use BaksDev\Users\Profile\UserProfile\Entity\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\UsersTable\Entity\Actions\Event\UsersTableActionsEvent;
use BaksDev\Users\UsersTable\Entity\Actions\Trans\UsersTableActionsTrans;

final readonly class InfoManufacturePartRepository implements InfoManufacturePartInterface
{

    public function __construct(private DBALQueryBuilder $DBALQueryBuilder) {}

    /**
     * Возвращает информацию о производственной партии
     */
    public function fetchInfoManufacturePartAssociative(
        ManufacturePartUid $part,
        UserProfileUid $profile,
        ?UserProfileUid $authority
    ): array|bool
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal
            ->select('part.id')
            ->addSelect('part.quantity')
            ->addSelect('part.number')
            ->from(ManufacturePart::class, 'part')
            ->where('part.id = :part')
            ->setParameter('part', $part, ManufacturePartUid::TYPE);


        $dbal
            ->addSelect('part_event.id AS event')
            ->addSelect('part_event.status')
            ->addSelect('part_event.complete')
            ->join(
                'part', ManufacturePartEvent::class,
                'part_event',
                'part_event.id = part.event'
            );


        /** Партии других пользователей */
        if($authority)
        {

            /** Профили доверенных пользователей */
            $dbal->leftJoin(
                'part',
                ProfileGroupUsers::class,
                'profile_group_users',
                'profile_group_users.authority = :authority'
            );

            $dbal
                ->andWhere('(part_event.profile = profile_group_users.profile OR part_event.profile = :profile)')
                ->setParameter('authority', $authority, UserProfileUid::TYPE) // к партиям других пользователей
                ->setParameter('profile', $profile, UserProfileUid::TYPE) // к своим партиям
            ;
        }
        else
        {
            $dbal
                ->andWhere('part_event.profile = :profile')
                ->setParameter('profile', $profile, UserProfileUid::TYPE);

        }


        //        $dbal->andWhere('part_event.profile = :profile');
        //        $dbal->setParameter('profile', $profile, UserProfileUid::TYPE);


        /** Ответственное лицо (Профиль пользователя) */

        $dbal
            ->addSelect('users_profile.event as users_profile_event')
            ->leftJoin(
                'part_event',
                UserProfile::class,
                'users_profile',
                'users_profile.id = part_event.profile'
            );

        $dbal
            ->addSelect('users_profile_personal.username AS users_profile_username')
            ->leftJoin(
                'users_profile',
                UserProfilePersonal::class,
                'users_profile_personal',
                'users_profile_personal.event = users_profile.event'
            );

        /**
         * Производственный процесс
         */
        $dbal
            ->addSelect('action_trans.name AS action_name')
            ->leftJoin(
                'part_event',
                UsersTableActionsTrans::class,
                'action_trans',
                'action_trans.event = part_event.action AND action_trans.local = :local'
            );

        /** Категория производства */

        $dbal
            ->addSelect('actions_event.id AS actions_event')
            ->leftJoin(
                'part_event',
                UsersTableActionsEvent::class,
                'actions_event',
                'actions_event.id = part_event.action'
            );

        $dbal
            ->addSelect('category.id AS category_id')
            ->leftJoin(
                'actions_event',
                CategoryProduct::class,
                'category',
                'category.id = actions_event.category'
            );

        $dbal
            ->addSelect('trans.name AS category_name')
            ->leftJoin(
                'category',
                CategoryProductTrans::class,
                'trans',
                'trans.event = category.event AND trans.local = :local'
            );


        return $dbal
            ->enableCache('manufacture-part', 86400)
            ->fetchAssociative();
    }
}