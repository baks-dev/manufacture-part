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

namespace BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus;


use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\Collection\ManufacturePartStatusInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Статус производства OPEN «Открыта»
 */
#[AutoconfigureTag('baks.manufacture.status')]
class ManufacturePartStatusOpen implements ManufacturePartStatusInterface
{
    /**
     * Статус производства "Открыта"
     */
    public const STATUS = 'open';

    private static int $sort = 100;

    private static string $color = '#4684D0';

    public function __toString(): string
    {
       return $this->getValue();
    }

    /**
     * Возвращает значение (value)
     */
    public function getValue(): string
    {
        return self::STATUS;
    }

    /**
     * Сортировка
     */
    public static function sort(): int
    {
        return self::$sort;
    }

    /**
     * Цвет
     */
    public static function color(): string
    {
        return self::$color;
    }

    /**
     * Проверяет, относится ли строка к данному объекту
     */
    public static function statusEquals(string $status): bool
    {
        return mb_strtolower($status) === self::STATUS;
    }

}
