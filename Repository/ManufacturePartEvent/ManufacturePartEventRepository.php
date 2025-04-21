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

namespace BaksDev\Manufacture\Part\Repository\ManufacturePartEvent;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Type\Event\ManufacturePartEventUid;
use InvalidArgumentException;


final class ManufacturePartEventRepository implements ManufacturePartEventInterface
{
    private ManufacturePartEventUid|false $event = false;

    public function __construct(private readonly ORMQueryBuilder $ORMQueryBuilder) {}

    public function forEvent(ManufacturePartEvent|ManufacturePartEventUid|string $event): self
    {
        if(empty($event))
        {
            $this->event = false;
            return $this;
        }

        if(is_string($event))
        {
            $event = new ManufacturePartEventUid($event);
        }

        if($event instanceof ManufacturePartEvent)
        {
            $event = $event->getId();
        }

        $this->event = $event;

        return $this;
    }

    /**
     * Метод возвращает событие партии по идентификатору
     */
    public function find(): ManufacturePartEvent|false
    {
        if(false === ($this->event instanceof ManufacturePartEventUid))
        {
            throw new InvalidArgumentException('Invalid Argument ManufacturePartEvent');
        }

        $orm = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $orm
            ->select('event')
            ->from(ManufacturePartEvent::class, 'event')
            ->where('event.id = :event')
            ->setParameter(
                key: 'event',
                value: $this->event,
                type: ManufacturePartEventUid::TYPE
            );

        return $orm
            ->enableCache('manufacture-part')
            ->getOneOrNullResult() ?: false;
    }
}