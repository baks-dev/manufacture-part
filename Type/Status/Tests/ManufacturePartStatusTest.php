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

namespace BaksDev\Manufacture\Part\Type\Status\Tests;

use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\Collection\ManufacturePartStatusCollection;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatusType;
use BaksDev\Wildberries\Orders\Type\WildberriesStatus\Status\Collection\WildberriesStatusInterface;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * @group manufacture-part
 */
#[When(env: 'test')]
final class ManufacturePartStatusTest extends KernelTestCase
{
    public function testUseCase(): void
    {
        /** @var ManufacturePartStatusCollection $ManufacturePartStatusCollection */
        $ManufacturePartStatusCollection = self::getContainer()->get(ManufacturePartStatusCollection::class);

        /** @var WildberriesStatusInterface $case */
        foreach($ManufacturePartStatusCollection->cases() as $case)
        {
            $ManufacturePartStatus = new ManufacturePartStatus($case->getValue());

            self::assertTrue($ManufacturePartStatus->equals($case::class)); // немспейс интерфейса
            self::assertTrue($ManufacturePartStatus->equals($case)); // объект интерфейса
            self::assertTrue($ManufacturePartStatus->equals($case->getValue())); // срока
            self::assertTrue($ManufacturePartStatus->equals($ManufacturePartStatus)); // объект класса

            $ManufacturePartStatusType = new ManufacturePartStatusType();
            $platform = $this->getMockForAbstractClass(AbstractPlatform::class);

            $convertToDatabase = $ManufacturePartStatusType->convertToDatabaseValue($ManufacturePartStatus, $platform);
            self::assertEquals($ManufacturePartStatus->getManufacturePartStatusValue(), $convertToDatabase);

            $convertToPHP = $ManufacturePartStatusType->convertToPHPValue($convertToDatabase, $platform);
            self::assertInstanceOf(ManufacturePartStatus::class, $convertToPHP);
            self::assertEquals($case, $convertToPHP->getManufacturePartStatus());

        }

    }
}