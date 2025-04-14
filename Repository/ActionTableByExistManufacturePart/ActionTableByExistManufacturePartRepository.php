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

declare(strict_types=1);

namespace BaksDev\Manufacture\Part\Repository\ActionTableByExistManufacturePart;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\Products\ManufacturePartProduct;
use BaksDev\Manufacture\Part\Entity\Working\ManufacturePartWorking;
use BaksDev\Manufacture\Part\Type\Event\ManufacturePartEventUid;
use BaksDev\Manufacture\Part\Type\Product\ManufacturePartProductUid;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusPackage;
use BaksDev\Users\UsersTable\Entity\Actions\Working\Trans\UsersTableActionsWorkingTrans;
use Generator;

final readonly class ActionTableByExistManufacturePartRepository implements ActionTableByExistManufacturePartInterface
{

    public function __construct(private DBALQueryBuilder $DBALQueryBuilder) {}

    /**
     * Возвращает коллекцию выполненных этапов производства указанного продукта
     */
    public function getCollection(ManufacturePartProductUid $product): Generator|false
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal
            ->from(ManufacturePartProduct::class, 'products')
            ->where('products.id = :products')
            ->setParameter(
                key: 'products',
                value: $product,
                type: ManufacturePartProductUid::TYPE
            );


        $dbal->join(
            'products',
            ManufacturePartEvent::class,
            'event',
            'event.id = products.event'
        );

        $dbal
            ->addSelect('working_event.id AS value')
            ->join(
                'event',
                ManufacturePartEvent::class,
                'working_event',
                'working_event.main = event.main AND working_event.status = :status'
            )
            ->setParameter(
                key: 'status',
                value: new ManufacturePartStatus(ManufacturePartStatusPackage::class),
                type: ManufacturePartStatus::TYPE
            );


        $dbal
            ->join(
                'working_event',
                ManufacturePartWorking::class,
                'working',
                'working.event = working_event.id AND working.profile IS NOT NULL'
            );

        $dbal
            ->addSelect('working_trans.name AS attr')
            ->leftJoin(
                'working',
                UsersTableActionsWorkingTrans::class,
                'working_trans',
                'working_trans.working = working.working AND working_trans.local = :local'
            );

        $dbal
            ->groupBy('working.working')
            ->addGroupBy('working_event.id')
            ->addGroupBy('working_trans.name');


        return $dbal->fetchAllHydrate(ManufacturePartEventUid::class);
    }
}