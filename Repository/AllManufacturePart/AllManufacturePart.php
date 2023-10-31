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
use BaksDev\Products\Category\Entity\ProductCategory;
use BaksDev\Products\Category\Entity\Trans\ProductCategoryTrans;
use BaksDev\Users\Profile\Group\Entity\Users\ProfileGroupUsers;
use BaksDev\Users\Profile\UserProfile\Entity\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\UsersTable\Entity\Actions\Event\UsersTableActionsEvent;
use BaksDev\Users\UsersTable\Entity\Actions\Trans\UsersTableActionsTrans;
use BaksDev\Users\UsersTable\Entity\Actions\Working\Trans\UsersTableActionsWorkingTrans;
use BaksDev\Users\UsersTable\Entity\Actions\Working\UsersTableActionsWorking;
use DateTimeImmutable;

final class AllManufacturePart implements AllManufacturePartInterface
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
        $qb = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $qb->select('part.id');
        $qb->addSelect('part.event');
        $qb->addSelect('part.number');
        $qb->addSelect('part.quantity');
        $qb->from(ManufacturePart::TABLE, 'part');


        //$qb->addSelect('part_event.marketplace');
        $qb->addSelect('part_event.status');
        $qb->addSelect('part_event.complete');


        $qb->join(
            'part',
            ManufacturePartEvent::TABLE,
            'part_event',
            'part_event.id = part.event '.(!$search->getQuery() && $filter->getStatus() ? ' AND part_event.status = :status' : '')
        );

        $qb->setParameter('status', $filter->getStatus(), ManufacturePartStatus::TYPE);


        /** Партии других пользователей */
        if($authority)
        {
            /** Профили доверенных пользователей */
            $qb->leftJoin(
                'part',
                ProfileGroupUsers::TABLE,
                'profile_group_users',
                'profile_group_users.authority = :authority '.($other ? '' : ' AND profile_group_users.profile = :profile')
            );

            $qb
                ->andWhere('part_event.profile = profile_group_users.profile')
                ->setParameter('authority', $authority, UserProfileUid::TYPE)
                ->setParameter('profile', $profile, UserProfileUid::TYPE);
        }
        else
        {
            $qb
                ->andWhere('part_event.profile = :profile')
                ->setParameter('profile', $profile, UserProfileUid::TYPE);
        }


        /** Ответственное лицо (Профиль пользователя) */

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


        $qb->addSelect('part_modify.mod_date AS part_date');
        $qb->join(
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
            $qb->andWhere('part_modify.mod_date BETWEEN :start AND :end');

            $qb->setParameter('start', $startOfDay->format("Y-m-d H:i:s"));
            $qb->setParameter('end', $endOfDay->format("Y-m-d H:i:s"));
        }




        $qb->addSelect('part_working.working AS part_working_uid');
        $qb->leftJoin(
            'part',
            ManufacturePartWorking::TABLE,
            'part_working',
            'part_working.event = part.event'
        );


        /**
         * Действие
         */
        $qb->leftJoin(
            'part_working',
            UsersTableActionsWorking::TABLE,
            'action_working',
            'action_working.id = part_working.working'
        );

        $qb->addSelect('action_working_trans.name AS part_working');

        $qb->leftJoin(
            'action_working',
            UsersTableActionsWorkingTrans::TABLE,
            'action_working_trans',
            'action_working_trans.working = action_working.id AND action_working_trans.local = :local'
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


        if($search->getQuery())
        {


            $qb
                ->createSearchQueryBuilder($search)
                ->addSearchEqualUid('part.id')
                ->addSearchLike('part.number');
        }


        $qb->orderBy('part_modify.mod_date', 'DESC');

        return $this->paginator->fetchAllAssociative($qb);

    }
}
