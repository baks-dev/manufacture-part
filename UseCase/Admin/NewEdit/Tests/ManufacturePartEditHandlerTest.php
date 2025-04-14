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

namespace BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\Tests;


use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Repository\ManufacturePartCurrentEvent\ManufacturePartCurrentEventInterface;
use BaksDev\Manufacture\Part\Type\Id\ManufacturePartUid;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\Collection\ManufacturePartStatusCollection;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\ManufacturePartDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\ManufacturePartHandler;
use BaksDev\Products\Category\Type\Id\CategoryProductUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\UsersTable\Type\Actions\Event\UsersTableActionsEventUid;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * @group manufacture-part
 * @group manufacture-part-usecase
 *
 * @depends BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\Tests\ManufacturePartNewHandlerTest::class
 */
#[When(env: 'test')]
class ManufacturePartEditHandlerTest extends KernelTestCase
{
    public function testUseCase(): void
    {
        /**
         * Инициируем статус для итератора тегов
         * @var ManufacturePartStatusCollection $ManufacturePartStatus
         */
        $ManufacturePartStatus = self::getContainer()->get(ManufacturePartStatusCollection::class);
        $ManufacturePartStatus->cases();

        /** @var ManufacturePartCurrentEventInterface $ManufacturePartCurrentEvent */
        $ManufacturePartCurrentEvent = self::getContainer()->get(ManufacturePartCurrentEventInterface::class);
        $ManufacturePartEvent = $ManufacturePartCurrentEvent
            ->fromPart(ManufacturePartUid::TEST)
            ->find();
        self::assertNotNull($ManufacturePartEvent);

        /** @see ManufacturePartDTO */
        $ManufacturePartDTO = new ManufacturePartDTO();
        $ManufacturePartEvent->getDto($ManufacturePartDTO);


        /** @see ManufacturePartDTO */
        $ManufacturePartDTO = new ManufacturePartDTO();
        $ManufacturePartEvent->getDto($ManufacturePartDTO);

        self::assertSame('comment', $ManufacturePartDTO->getComment());
        $ManufacturePartDTO->setComment('edit_comment');


        self::assertFalse($ManufacturePartDTO->getAction()->equals(UsersTableActionsEventUid::TEST));
        $ManufacturePartDTO->setAction(new UsersTableActionsEventUid());

        $ManufacturePartDTO->setCategory(new CategoryProductUid());


        /** @var ManufacturePartHandler $ManufacturePartHandler */
        $ManufacturePartHandler = self::getContainer()->get(ManufacturePartHandler::class);
        $handle = $ManufacturePartHandler->handle($ManufacturePartDTO);

        self::assertTrue(($handle instanceof ManufacturePart), $handle.': Ошибка ManufacturePart');

    }


    public function testComplete(): void
    {
        /** @var ManufacturePartCurrentEventInterface $ManufacturePartCurrentEvent */
        $ManufacturePartCurrentEvent = self::getContainer()->get(ManufacturePartCurrentEventInterface::class);
        $ManufacturePartEvent = $ManufacturePartCurrentEvent
            ->fromPart(ManufacturePartUid::TEST)
            ->find();
        self::assertNotNull($ManufacturePartEvent);

        /** @see ManufacturePartDTO */
        $ManufacturePartDTO = new ManufacturePartDTO();
        $ManufacturePartEvent->getDto($ManufacturePartDTO);

        self::assertSame('edit_comment', $ManufacturePartDTO->getComment());
        self::assertTrue($ManufacturePartDTO->getAction()->equals(UsersTableActionsEventUid::TEST));
    }
}