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

namespace BaksDev\Manufacture\Part\Repository\AllWorkingByManufacturePart;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Type\Id\ManufacturePartUid;
use BaksDev\Users\UsersTable\Entity\Actions\Event\UsersTableActionsEvent;
use BaksDev\Users\UsersTable\Entity\Actions\Working\Trans\UsersTableActionsWorkingTrans;
use BaksDev\Users\UsersTable\Entity\Actions\Working\UsersTableActionsWorking;
use InvalidArgumentException;

final  class AllWorkingByManufacturePartRepository implements AllWorkingByManufacturePartInterface
{
    private ManufacturePartUid|false $part = false;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    public function forPart(ManufacturePart|ManufacturePartUid|string $part): self
    {
        if(empty($part))
        {
            $this->part = false;
            return $this;
        }

        if(is_string($part))
        {
            $part = new ManufacturePartUid($part);
        }

        if($part instanceof ManufacturePart)
        {
            $part = $part->getId();
        }

        $this->part = $part;

        return $this;
    }


    /**
     * Возвращает этапы производства указанной производственной партии
     */
    public function findAll(): array|bool
    {
        if(false === ($this->part instanceof ManufacturePartUid))
        {
            throw new InvalidArgumentException('Invalid Argument ManufacturePart');
        }

        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal
            ->from(ManufacturePart::class, 'part')
            ->where('part.id = :part')
            ->setParameter(
                'part',
                $this->part,
                ManufacturePartUid::TYPE,
            );


        $dbal
            ->select('part_event.comment AS part_comment')
            ->join(
                'part',
                ManufacturePartEvent::class,
                'part_event',
                'part_event.id = part.event',
            );


        /** Этапы производства */


        $dbal->join(
            'part_event',
            UsersTableActionsEvent::class,
            'action_event',
            'action_event.id = part_event.action',
        );

        $dbal
            ->addSelect('action_working.id AS working_id')
            ->leftJoin(
                'action_event',
                UsersTableActionsWorking::class,
                'action_working',
                'action_working.event = action_event.id',
            );


        $dbal
            ->addSelect('action_working_trans.name AS working_name')
            ->leftJoin(
                'action_event',
                UsersTableActionsWorkingTrans::class,
                'action_working_trans',
                'action_working_trans.working = action_working.id AND action_working_trans.local = :local',
            );

        $dbal->orderBy('action_working.sort');

        /* Кешируем результат DBAL */
        return $dbal
            ->enableCache('manufacture-part', 86400)
            ->fetchAllAssociative();

    }


}