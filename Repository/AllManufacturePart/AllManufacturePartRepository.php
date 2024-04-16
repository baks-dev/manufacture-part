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

namespace BaksDev\Manufacture\Part\Repository\AllManufacturePart;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Services\Paginator\PaginatorInterface;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Entity\Modify\ManufacturePartModify;
use BaksDev\Manufacture\Part\Entity\Working\ManufacturePartWorking;
use BaksDev\Manufacture\Part\Forms\ManufactureFilter\ManufactureFilterInterface;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus;
use BaksDev\Products\Category\Entity\CategoryProduct;
use BaksDev\Products\Category\Entity\Trans\CategoryProductTrans;
use BaksDev\Users\Profile\Group\Entity\Users\ProfileGroupUsers;
use BaksDev\Users\Profile\UserProfile\Entity\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\UsersTable\Entity\Actions\Event\UsersTableActionsEvent;
use BaksDev\Users\UsersTable\Entity\Actions\Trans\UsersTableActionsTrans;
use BaksDev\Users\UsersTable\Entity\Actions\Working\Trans\UsersTableActionsWorkingTrans;
use BaksDev\Users\UsersTable\Entity\Actions\Working\UsersTableActionsWorking;
use DateTimeImmutable;

final class AllManufacturePartRepository implements AllManufacturePartInterface
{
    private PaginatorInterface $paginator;

    private DBALQueryBuilder $DBALQueryBuilder;

    public function __construct(
        DBALQueryBuilder $DBALQueryBuilder,
        PaginatorInterface $paginator,
    )
    {

        $this->paginator = $paginator;
        $this->DBALQueryBuilder = $DBALQueryBuilder;
    }


    /** Метод возвращает пагинатор ManufacturePart */
    public function fetchAllManufacturePartAssociative(
        SearchDTO $search,
        ManufactureFilterInterface $filter,
        UserProfileUid $profile,
        ?UserProfileUid $authority,
        bool $other

    ): PaginatorInterface
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal->select('part.id');
        $dbal->addSelect('part.event');
        $dbal->addSelect('part.number');
        $dbal->addSelect('part.quantity');
        $dbal->from(ManufacturePart::TABLE, 'part');


        //$dbal->addSelect('part_event.marketplace');
        $dbal->addSelect('part_event.status');
        $dbal->addSelect('part_event.complete');


//        $dbal->join(
//            'part',
//            ManufacturePartEvent::TABLE,
//            'part_event',
//            'part_event.id = part.event '.(!$search->getQuery() && $filter->getStatus() ? ' AND part_event.status = :status' : '')
//        );


//        /** Свои партии и партии доверенных профилей */
//        if($authority)
//        {
//            /** Профили доверенных пользователей */
//            $dbal
//                ->leftJoin(
//                    'part',
//                    ProfileGroupUsers::TABLE,
//                    'profile_group_users',
//                    'profile_group_users.authority = :authority '.($other ? '' : ' AND profile_group_users.profile = :profile')
//                );
//
//
//            $dbal->join(
//                'part',
//                ManufacturePartEvent::TABLE,
//                'part_event',
//                'part_event.id = part.event '.(!$search->getQuery() && $filter->getStatus() ? ' AND part_event.status = :status' : '')
//            );
//
//            $dbal
//                ->andWhere('part_event.profile = profile_group_users.profile')
//                ->setParameter('authority', $authority, UserProfileUid::TYPE);
//        }
//        else
//        {
//
//
//            $dbal->andWhere('part_event.profile = :profile');
//        }



        /** Партии доверенных профилей */
        if($authority)
        {
            $dbal->leftJoin(
                'part',
                ProfileGroupUsers::TABLE,
                'profile_group_users',
                'profile_group_users.authority = :authority'
            )
                ->setParameter('authority', $authority, UserProfileUid::TYPE);

            $dbal->join(
                'part',
                ManufacturePartEvent::TABLE,
                'part_event',
                'part_event.id = part.event AND (part_event.profile = profile_group_users.profile OR part_event.profile = :profile)'
                .(!$search->getQuery() && $filter->getStatus() ? ' AND part_event.status = :status' : '')
            );
        }
        else
        {
            $dbal->join(
                'part',
                ManufacturePartEvent::TABLE,
                'part_event',
                'part_event.id = part.event AND part_event.profile = :profile'
                .(!$search->getQuery() && $filter->getStatus() ? ' AND part_event.status = :status' : '')
            );
        }

        $dbal
            ->setParameter('profile', $profile, UserProfileUid::TYPE)
            ->setParameter('status', $filter->getStatus(), ManufacturePartStatus::TYPE);


        /** Ответственное лицо (Профиль пользователя) */

        $dbal->leftJoin(
            'part_event',
            UserProfile::TABLE,
            'users_profile',
            'users_profile.id = part_event.profile'
        );

        $dbal->addSelect('users_profile_personal.username AS users_profile_username');

        $dbal->leftJoin(
            'users_profile',
            UserProfilePersonal::TABLE,
            'users_profile_personal',
            'users_profile_personal.event = users_profile.event'
        );


        $dbal->addSelect('part_modify.mod_date AS part_date');
        $dbal->join(
            'part',
            ManufacturePartModify::TABLE,
            'part_modify',
            'part_modify.event = part.event'
        );


        if(!$search->getQuery() && $filter->getDate())
        {
            $date = $filter->getDate() ?: new DateTimeImmutable();

            // Начало дня
            $startOfDay = $date->setTime(0, 0, 0);
            // Конец дня
            $endOfDay = $date->setTime(23, 59, 59);

            //($date ? ' AND part_modify.mod_date = :date' : '')
            $dbal->andWhere('part_modify.mod_date BETWEEN :start AND :end');

            $dbal->setParameter('start', $startOfDay->format("Y-m-d H:i:s"));
            $dbal->setParameter('end', $endOfDay->format("Y-m-d H:i:s"));
        }


        $dbal->addSelect('part_working.working AS part_working_uid');
        $dbal->leftJoin(
            'part',
            ManufacturePartWorking::TABLE,
            'part_working',
            'part_working.event = part.event'
        );


        /**
         * Действие
         */
        $dbal->leftJoin(
            'part_working',
            UsersTableActionsWorking::TABLE,
            'action_working',
            'action_working.id = part_working.working'
        );

        $dbal->addSelect('action_working_trans.name AS part_working');

        $dbal->leftJoin(
            'action_working',
            UsersTableActionsWorkingTrans::TABLE,
            'action_working_trans',
            'action_working_trans.working = action_working.id AND action_working_trans.local = :local'
        );

        /**
         * Производственный процесс
         */
        $dbal->addSelect('action_trans.name AS action_name');
        $dbal->leftJoin(
            'part_event',
            UsersTableActionsTrans::TABLE,
            'action_trans',
            'action_trans.event = part_event.action AND action_trans.local = :local'
        );


        /** Категория производства */

        $dbal->addSelect('actions_event.id AS actions_event');
        $dbal->leftJoin(
            'part_event',
            UsersTableActionsEvent::TABLE,
            'actions_event',
            'actions_event.id = part_event.action'
        );

        $dbal->addSelect('category.id AS category_id');
        $dbal->leftJoin(
            'actions_event',
            CategoryProduct::TABLE,
            'category',
            'category.id = actions_event.category'
        );

        $dbal->addSelect('trans.name AS category_name');
        $dbal->leftJoin(
            'category',
            CategoryProductTrans::TABLE,
            'trans',
            'trans.event = category.event AND trans.local = :local'
        )
            ->bindLocal();


        if($search->getQuery())
        {


            $dbal
                ->createSearchQueryBuilder($search)
                ->addSearchEqualUid('part.id')
                ->addSearchLike('part.number');
        }


        $dbal->orderBy('part_modify.mod_date', 'DESC');

        return $this->paginator->fetchAllAssociative($dbal);

    }
}
