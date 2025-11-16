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

namespace BaksDev\Manufacture\Part\UseCase\ManufactureProduct\Delete;


use BaksDev\Core\Entity\AbstractHandler;
use BaksDev\Manufacture\Part\Entity\ManufactureProduct\ManufactureProductInvariable;
use BaksDev\Manufacture\Part\Type\Id\ManufacturePartUid;
use BaksDev\Products\Product\Type\Invariable\ProductInvariableUid;

final class DeleteManufactureProductInvariableHandler extends AbstractHandler
{
    /** @see ManufactureProductInvariable */
    public function handle(DeleteManufactureProductInvariableDTO $command): string|bool
    {
        /** Валидация DTO  */
        $this->validatorCollection->add($command);

        if($command->getManufacture() instanceof ManufacturePartUid)
        {
            $collection = $this
                ->getRepository(ManufactureProductInvariable::class)
                ->findBy(['manufacture' => $command->getManufacture()]);
        }

        if($command->getInvariable() instanceof ProductInvariableUid)
        {
            $collection = $this
                ->getRepository(ManufactureProductInvariable::class)
                ->findBy(['invariable' => $command->getInvariable(), 'type' => $command->getType()]);
        }

        /** Валидация всех объектов */
        if($this->validatorCollection->isInvalid())
        {
            return $this->validatorCollection->getErrorUniqid();
        }

        if(empty($collection))
        {
            return false;
        }

        foreach($collection as $manufactureProductInvariable)
        {
            $this->remove($manufactureProductInvariable);
        }

        $this->flush();

        return true;
    }
}