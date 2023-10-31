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

namespace BaksDev\Manufacture\Part\Repository\ActiveWorkingManufacturePart;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Entity\Working\ManufacturePartWorking;
use BaksDev\Manufacture\Part\Repository\ManufacturePartActionEvent\ManufacturePartActionEventInterface;
use BaksDev\Manufacture\Part\Type\Id\ManufacturePartUid;
use BaksDev\Users\Profile\UserProfile\Entity\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\UsersTable\Entity\Actions\Event\UsersTableActionsEvent;
use BaksDev\Users\UsersTable\Entity\Actions\Working\Trans\UsersTableActionsWorkingTrans;
use BaksDev\Users\UsersTable\Entity\Actions\Working\UsersTableActionsWorking;
use BaksDev\Users\UsersTable\Type\Actions\Event\UsersTableActionsEventUid;
use BaksDev\Users\UsersTable\Type\Actions\Working\UsersTableActionsWorkingUid;
use Doctrine\DBAL\ArrayParameterType;

final class ActiveWorkingManufacturePart implements ActiveWorkingManufacturePartInterface
{

    private DBALQueryBuilder $DBALQueryBuilder;
    private ManufacturePartActionEventInterface $manufacturePartActionEvent;

    public function __construct(
        DBALQueryBuilder $DBALQueryBuilder,
        ManufacturePartActionEventInterface $manufacturePartActionEvent,
    )
    {

        $this->DBALQueryBuilder = $DBALQueryBuilder;
        $this->manufacturePartActionEvent = $manufacturePartActionEvent;
    }

    /**
     * Метод возвращает рабочее состояние заявки на производство продукции
     * !! то, которое необходимо выполнить
     */
    public function findNextWorkingByManufacturePart(ManufacturePartUid $part): ?UsersTableActionsWorkingUid
    {

        $action = $this->manufacturePartActionEvent->getManufacturePartActionEventUid($part);

        /**
         * Получаем все выполненные действия производственной партии
         */
        $qb = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $qb->from(ManufacturePart::TABLE, 'part');
        $qb->where('part.id = :part');
        $qb->setParameter('part', $part, ManufacturePartUid::TYPE);


        //$qb->addSelect('part_event.action');
        $qb->leftJoin(
            'part',
            ManufacturePartEvent::TABLE,
            'part_event',
            'part_event.main = part.id'
        );


        $qb->addSelect('part_working.working');
        $qb->leftJoin(
            'part_event',
            ManufacturePartWorking::TABLE,
            'part_working',
            'part_working.event = part_event.id'
        );

        $qb->andWhere('part_working.working IS NOT NULL');

        $qb->groupBy('part_event.action');
        $qb->addGroupBy('part_working.working');

        $exist = $qb->fetchAllAssociative();
        $workings = array_column($exist, "working");


        /**
         * Получаем активное действия
         */
        $qb = $this->DBALQueryBuilder->createQueryBuilder(self::class)
            ->bindLocal();

        $qb->from(UsersTableActionsEvent::TABLE, 'action_event');
        $qb->where('action_event.id = :action');
        $qb->setParameter('action', $action, UsersTableActionsEventUid::TYPE);

        $qb->addSelect('action_working.id');
        $qb->join(
            'action_event',
            UsersTableActionsWorking::TABLE,
            'action_working',
            'action_working.event = action_event.id'
        );

        $qb->addSelect('working_trans.name');
        $qb->leftJoin(
            'action_working',
            UsersTableActionsWorkingTrans::TABLE,
            'working_trans',
            'working_trans.working = action_working.id AND working_trans.local = :local'
        );

        if($workings)
        {
            $qb->andWhere('action_working.id NOT IN (:working)');
            $qb->setParameter('working', $workings, ArrayParameterType::STRING);
        }

        $qb->orderBy('action_working.sort', 'ASC');
        $qb->setMaxResults(1);

        $result = $qb->enableCache('manufacture-part',86400)->fetchAssociative();
        
        return $result ? new UsersTableActionsWorkingUid($result['id'], $result['name']) : null;

    }



    /**
     * Возвращает Выполненные этапы производства с указанием сотрудника, выполнившим этап
     */
    public function fetchCompleteWorkingByManufacturePartAssociative(ManufacturePartUid $part): ?array
    {

        $qb = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();


        //$qb->addSelect('part_event.id AS part_event');
        $qb->from(ManufacturePartEvent::TABLE, 'part_event');
        $qb->where('part_event.main = :part');
        $qb->setParameter('part', $part, ManufacturePartUid::TYPE);

        $qb->join(
            'part_event',
            ManufacturePartWorking::TABLE,
            'part_working',
            'part_working.event = part_event.id'
        );

        $qb->addSelect('part.number AS part_number');
        $qb->addSelect('part.quantity AS part_quantity');
        $qb->leftJoin(
            'part_event',
            ManufacturePart::TABLE,
            'part',
            'part.id = part_event.main'
        );


        /** Исполнитель действия (Профиль пользователя) */

        $qb->leftJoin(
            'part_working',
            UserProfile::TABLE,
            'users_profile',
            'users_profile.id = part_working.profile'
        );

        $qb->addSelect('users_profile_personal.username AS users_profile_username');

        $qb->leftJoin(
            'users_profile',
            UserProfilePersonal::TABLE,
            'users_profile_personal',
            'users_profile_personal.event = users_profile.event'
        );


        /** Этапы производства */
//        $qb->addSelect('action_event.id AS action_event');
//        $qb->join(
//            'part_event',
//            UsersTableActionsEvent::TABLE,
//            'action_event',
//            'action_event.id = part_event.action'
//        );


        //$qb->addSelect('action_working.id AS working_id');

        $qb->leftJoin(
            'part_event',
            UsersTableActionsWorking::TABLE,
            'action_working',
            'action_working.id =  part_working.working'
        );

        $qb->addSelect('action_working_trans.name AS working_name');

        $qb->leftJoin(
            'action_working',
            UsersTableActionsWorkingTrans::TABLE,
            'action_working_trans',
            'action_working_trans.working = action_working.id AND action_working_trans.local = :local'
        );

        $qb->orderBy('action_working.sort');

        /* Кешируем результат DBAL */
        return $qb
            ->enableCache('manufacture-part', 86400)
            ->fetchAllAssociative();

    }
}
