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
 */

namespace BaksDev\Manufacture\Part\Repository\UserTableActionOffers;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Users\UsersTable\Entity\Actions\Event\UsersTableActionsEvent;
use BaksDev\Users\UsersTable\Entity\Actions\Offer\UsersTableActionsOffer;
use BaksDev\Users\UsersTable\Entity\Actions\UsersTableActions;
use BaksDev\Users\UsersTable\Type\Actions\Id\UsersTableActionsUid;

/**
 * Ииспользуется для получения данных по offers в настройках произв-ного процесса
 */
class UserTableActionOffersRepository implements UserTableActionOffersInterface
{
    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
    ) {}

    public function findActionOffersByMain(UsersTableActionsUid $main): array|false
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal->select('actions_event.id');

        $dbal
            ->from(UsersTableActions::class, 'actions');

        $dbal->leftJoin(
            'actions',
            UsersTableActionsEvent::class,
            'actions_event',
            'actions.id = actions_event.main',
        );

        $dbal
            ->addSelect('actions_offer.offer AS actions_offer_offer')
            ->addSelect('actions_offer.variation AS actions_offer_variation')
            ->addSelect('actions_offer.modification AS actions_offer_modification')
            ->leftJoin(
                'actions_event',
                UsersTableActionsOffer::class,
                'actions_offer',
                'actions_offer.event = actions_event.id'
            );

        $dbal->where('actions.id = :main')
            ->setParameter(
                key: 'main',
                value: $main,
                type: UsersTableActionsUid::TYPE
            );

        $dbal->orderBy('actions_event.id', 'DESC');

        return $dbal->fetchAssociative();
    }
}