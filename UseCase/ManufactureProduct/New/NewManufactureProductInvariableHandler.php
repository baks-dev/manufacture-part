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

namespace BaksDev\Manufacture\Part\UseCase\ManufactureProduct\New;


use BaksDev\Core\Entity\AbstractHandler;
use BaksDev\Delivery\Type\Id\DeliveryUid;
use BaksDev\Manufacture\Part\Entity\ManufactureProduct\ManufactureProductInvariable;

final class NewManufactureProductInvariableHandler extends AbstractHandler
{
    /** @see ManufactureProductInvariable */
    public function handle(NewManufactureProductInvariableDTO $command): string|ManufactureProductInvariable
    {
        $this->setCommand($command);

        $ManufactureProductInvariable = $this
            ->getRepository(ManufactureProductInvariable::class)
            ->findOneBy([
                'invariable' => $command->getInvariable(),
                'type' => $command->getType(),
            ]);

        if(false === $ManufactureProductInvariable instanceof ManufactureProductInvariable)
        {
            $ManufactureProductInvariable = new ManufactureProductInvariable(
                $command->getInvariable(),
                $command->getType(),
            );

            $this->persist($ManufactureProductInvariable);

            $ManufactureProductInvariable->setEntity($command);

            /** Валидация всех объектов */
            if($this->validatorCollection->isInvalid())
            {
                return $this->validatorCollection->getErrorUniqid();
            }

            $this->flush();
        }

        $this->messageDispatch
            ->addClearCacheOther('manufacture-part')
            ->addClearCacheOther('wildberries-orders');

        return $ManufactureProductInvariable;
    }
}