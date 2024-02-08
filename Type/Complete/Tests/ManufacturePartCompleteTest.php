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

namespace BaksDev\Manufacture\Part\Type\Complete\Tests;

use BaksDev\Manufacture\Part\Type\Complete\Collection\ManufacturePartCompleteCollection;
use BaksDev\Manufacture\Part\Type\Complete\Collection\ManufacturePartCompleteInterface;
use BaksDev\Manufacture\Part\Type\Complete\ManufacturePartComplete;
use BaksDev\Manufacture\Part\Type\Complete\ManufacturePartCompleteType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * @group manufacture-part
 */
#[When(env: 'test')]
final class ManufacturePartCompleteTest extends KernelTestCase
{
    public function testUseCase(): void
    {
        /** @var ManufacturePartCompleteCollection $Collection */
        $Collection = self::getContainer()->get(ManufacturePartCompleteCollection::class);

        /** @var ManufacturePartCompleteInterface $case */
        foreach($Collection->cases() as $case)
        {
            $ManufacturePartComplete = new ManufacturePartComplete($case->getValue());

            self::assertTrue($ManufacturePartComplete->equals($case::class)); // немспейс интерфейса
            self::assertTrue($ManufacturePartComplete->equals($case)); // объект интерфейса
            self::assertTrue($ManufacturePartComplete->equals($case->getValue())); // срока
            self::assertTrue($ManufacturePartComplete->equals($ManufacturePartComplete)); // объект класса


            $Type = new ManufacturePartCompleteType();
            $platform = $this->getMockForAbstractClass(AbstractPlatform::class);

            $convertToDatabase = $Type->convertToDatabaseValue($ManufacturePartComplete, $platform);
            self::assertEquals($ManufacturePartComplete->getActionCompleteValue(), $convertToDatabase);

            $convertToPHP = $Type->convertToPHPValue($convertToDatabase, $platform);
            self::assertInstanceOf(ManufacturePartComplete::class, $convertToPHP);
            self::assertEquals($case, $convertToPHP->getActionComplete());

        }

        self::assertTrue(true);
    }
}