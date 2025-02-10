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

namespace BaksDev\Manufacture\Part\UseCase\Admin\Action;

use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEventInterface;
use BaksDev\Manufacture\Part\Type\Event\ManufacturePartEventUid;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusPackage;
use ReflectionProperty;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ManufacturePartEvent */
final class ManufacturePartActionDTO implements ManufacturePartEventInterface
{
    /**
     * Идентификатор события
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private readonly ManufacturePartEventUid $id;

    /**
     * Статус производственной партии
     */
    #[Assert\NotBlank]
    private readonly ManufacturePartStatus $status;

    /**
     * Рабочее состояние производственной партии
     */
    #[Assert\Valid]
    private Working\ManufacturePartWorkingDTO $working;


    public function __construct(ManufacturePartEventUid|false $id = false)
    {
        !$id ?: $this->id = $id;

        $this->working = new Working\ManufacturePartWorkingDTO();
        $this->status = new ManufacturePartStatus(ManufacturePartStatusPackage::class);
    }

    /**
     * Идентификатор события
     */
    public function getEvent(): ?ManufacturePartEventUid
    {
        return $this->id;
    }

    public function setId(ManufacturePartEventUid $id): self
    {
        if(false === (new ReflectionProperty(self::class, 'id')->isInitialized($this)))
        {
            $this->id = $id;
        }

        return $this;
    }


    /**
     * Рабочее состояние производственной партии
     */

    public function getWorking(): Working\ManufacturePartWorkingDTO
    {
        return $this->working;
    }

    public function setWorking(Working\ManufacturePartWorkingDTO $working): self
    {
        $this->working = $working;
        return $this;
    }

    /**
     * Status
     */
    public function getStatus(): ManufacturePartStatus
    {
        return $this->status;
    }

}