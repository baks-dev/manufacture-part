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

namespace BaksDev\Manufacture\Part\Type\Status;

use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\Collection\ManufacturePartStatusInterface;
use InvalidArgumentException;

final class ManufacturePartStatus
{
    public const TYPE = 'manufacture_part_status';

    private ManufacturePartStatusInterface $status;

    public function __construct(self|string|ManufacturePartStatusInterface $status)
    {
        if(is_string($status) && class_exists($status))
        {
            $instance = new $status();

            if($instance instanceof ManufacturePartStatusInterface)
            {
                $this->status = $instance;
                return;
            }
        }

        if($status instanceof ManufacturePartStatusInterface)
        {
            $this->status = $status;
            return;
        }

        if($status instanceof self)
        {
            $this->status = $status->getManufacturePartStatus();
            return;
        }

        /** @var ManufacturePartStatusInterface $declare */
        foreach(self::getDeclared() as $declare)
        {
            $instance = new self($declare);

            if($instance->getManufacturePartStatusValue() === $status)
            {
                $this->status = new $declare;
                return;
            }
        }

        throw new InvalidArgumentException(sprintf('Not found ManufacturePartStatus %s', $status));

    }

    public function __toString(): string
    {
        return $this->status->getValue();
    }


    public function getManufacturePartStatus(): ManufacturePartStatusInterface
    {
        return $this->status;
    }


    public function getManufacturePartStatusValue(): string
    {
        return $this->status->getValue();
    }


    public function getColor(): string
    {
        return $this->status::color();
    }

    public static function cases(): array
    {
        $case = [];

        foreach(self::getDeclared() as $key => $declared)
        {
            /** @var ManufacturePartStatusInterface $declared */
            $class = new $declared;
            $case[$class::sort().$key] = new self($class);
        }

        ksort($case);

        return $case;
    }


    public static function getDeclared(): array
    {
        return array_filter(
            get_declared_classes(),
            static function($className) {
                return in_array(ManufacturePartStatusInterface::class, class_implements($className), true);
            }
        );
    }

    public function equals(mixed $status): bool
    {
        $status = new self($status);
        return $this->getManufacturePartStatusValue() === $status->getManufacturePartStatusValue();
    }
}
