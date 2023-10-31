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

namespace BaksDev\Manufacture\Part\Entity;

use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Type\Event\ManufacturePartEventUid;
use BaksDev\Manufacture\Part\Type\Id\ManufacturePartUid;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/* ManufacturePart */

#[ORM\Entity]
#[ORM\Table(name: 'manufacture_part')]
class ManufacturePart
{
    public const TABLE = 'manufacture_part';

    /**
     * Идентификатор сущности
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Id]
    #[ORM\Column(type: ManufacturePartUid::TYPE)]
    private ManufacturePartUid $id;

    /**
     * Идентификатор События
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Column(type: ManufacturePartEventUid::TYPE, unique: true)]
    private ManufacturePartEventUid $event;

    /**
     * Общее количество в заявке
     */
    #[Assert\NotBlank]
    #[Assert\Range(min: 0)]
    #[ORM\Column(type: Types::INTEGER)]
    private int $quantity = 0;

    /**
     * Номер заявки на производство
     */
    #[ORM\Column(type: Types::STRING, length: 20, unique: true)]
    private string $number;


    public function __construct()
    {
        $this->id = new ManufacturePartUid();
        $this->number = number_format(microtime(true) * 100, 0, '.', '.');
    }

    /**
     * Идентификатор
     */
    public function getId(): ManufacturePartUid
    {
        return $this->id;
    }

    /**
     * Идентификатор События
     */
    public function getEvent(): ManufacturePartEventUid
    {
        return $this->event;
    }

    public function setEvent(ManufacturePartEventUid|ManufacturePartEvent $event): void
    {
        $this->event = $event instanceof ManufacturePartEvent ? $event->getId() : $event;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    /**
     * Quantity
     */
    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }
    
}