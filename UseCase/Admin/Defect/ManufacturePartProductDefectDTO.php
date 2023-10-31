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

namespace BaksDev\Manufacture\Part\UseCase\Admin\Defect;

use BaksDev\Manufacture\Part\Type\Event\ManufacturePartEventUid;
use BaksDev\Manufacture\Part\Type\Product\ManufacturePartProductUid;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ManufacturePartProduct */
final class ManufacturePartProductDefectDTO
{
    /**
     * Идентификатор
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private readonly ManufacturePartProductUid $id;


    /**
     * Идентификатор события прозводственной партии, на котором произошел дефект
     */
    #[Assert\Uuid]
    private ?ManufacturePartEventUid $event = null;

    /**
     * Количество дефектной продукции
     */
    #[Assert\NotBlank]
    #[Assert\Range(min: 1)]
    private int $total = 1;


    public function __construct(ManufacturePartProductUid $id)
    {
        $this->id = $id;
    }

    public function getId(): ManufacturePartProductUid
    {
        return $this->id;
    }

    /**
     * Идентификатор события прозводственной партии, на котором произошел дефект
     */

    public function getEvent(): ?ManufacturePartEventUid
    {
        return $this->event;
    }

    public function setEvent(?ManufacturePartEventUid $event): self
    {
        $this->event = $event;
        return $this;
    }


    /**
     * Количество дефектной продукции
     */

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): self
    {
        $this->total = $total;
        return $this;
    }

}