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

namespace BaksDev\Manufacture\Part\Type\Complete;


use BaksDev\Manufacture\Part\Type\Complete\Collection\ManufacturePartCompleteInterface;
use BaksDev\Manufacture\Part\Type\Complete\Collection\ManufacturePartCompleteNothing;

final class ManufacturePartComplete
{
    public const TYPE = 'manufacture_part_complete';

    private ?ManufacturePartCompleteInterface $complete = null;


    public function __construct(self|string|ManufacturePartCompleteInterface $complete)
    {

        if($complete instanceof ManufacturePartCompleteInterface)
        {
            $this->complete = $complete;
        }

        if($complete instanceof $this)
        {
            $this->complete = $complete->getActionComplete();
        }

        if(is_string($complete))
        {
            if(class_exists($complete))
            {
                $this->complete = new $complete();
                return;
            }

            /** @var ManufacturePartCompleteInterface $class */
            foreach(self::getDeclaredActionsComplete() as $class)
            {
                if($class::equals($complete))
                {
                    $this->complete = new $class;
                    break;
                }
            }
        }

    }

    public function __toString(): string
    {
        return $this->complete ? $this->complete->getValue() : '';
    }


    /** Возвращает значение ColorsInterface */
    public function getActionComplete(): ManufacturePartCompleteInterface
    {
        return $this->complete;
    }


    /** Возвращает значение ColorsInterface */
    public function getActionCompleteValue(): string
    {

        return $this->complete?->getValue() ?: '';
    }


    public static function cases(): array
    {
        $case = [];

        $key = 1;
        foreach(self::getDeclaredActionsComplete() as $complete)
        {
            /** @var ManufacturePartCompleteInterface $complete */
            $actions = new $complete;
            $case[ $complete === ManufacturePartCompleteNothing::class ? 0 : $key] = new self($actions);
            $key++;
        }

        ksort($case);

        return $case;
    }


    public static function getDeclaredActionsComplete(): array
    {
        return array_filter(
            get_declared_classes(),
            static function($className) {
                return in_array(ManufacturePartCompleteInterface::class, class_implements($className), true);
            },
        );
    }
}