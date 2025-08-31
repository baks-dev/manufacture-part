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
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Type\Id\ManufacturePartUid;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\Collection\ManufacturePartStatusCollection;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\ManufacturePartDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\ManufacturePartHandler;
use BaksDev\Products\Category\Type\Id\CategoryProductUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use BaksDev\Users\UsersTable\Type\Actions\Event\UsersTableActionsEventUid;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: 'test')]
#[Group('manufacture-part')]
class ManufacturePartNewHandlerTest extends KernelTestCase
{
    public static function setUpBeforeClass(): void
    {
        /**
         * Инициируем статус для итератора тегов
         * @var ManufacturePartStatusCollection $ManufacturePartStatus
         */
        $ManufacturePartStatus = self::getContainer()->get(ManufacturePartStatusCollection::class);
        $ManufacturePartStatus->cases();


        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $main = $em->getRepository(ManufacturePart::class)
            ->findOneBy(['id' => ManufacturePartUid::TEST]);

        if($main)
        {
            $em->remove($main);
        }

        /* WbBarcodeEvent */

        $event = $em->getRepository(ManufacturePartEvent::class)
            ->findBy(['main' => ManufacturePartUid::TEST]);

        foreach($event as $remove)
        {
            $em->remove($remove);
        }

        $em->flush();
        $em->clear();
    }


    public function testUseCase(): void
    {
        /** @see ManufacturePartDTO */
        $ManufacturePartDTO = new ManufacturePartDTO();


        $ManufacturePartDTO->setComment('comment');
        self::assertSame('comment', $ManufacturePartDTO->getComment());

        $ManufacturePartDTO->setAction($UsersTableActionsEventUid = clone new UsersTableActionsEventUid());
        self::assertSame($UsersTableActionsEventUid, $ManufacturePartDTO->getAction());


        $ManufacturePartDTO->setCategory($CategoryProductUid = clone new CategoryProductUid());
        self::assertSame($CategoryProductUid, $ManufacturePartDTO->getCategory());

        $ManufacturePartDTO->setFixed($UserProfileUid = new UserProfileUid());
        self::assertSame($UserProfileUid, $ManufacturePartDTO->getFixed());
        /** */

        $ManufacturePartInvariableDTO = $ManufacturePartDTO->getInvariable();

        $ManufacturePartInvariableDTO->setUsr($UserUid = new UserUid(UserUid::TEST));
        self::assertSame($UserUid, $ManufacturePartInvariableDTO->getUsr());

        $ManufacturePartInvariableDTO->setProfile($UserProfileUid = new UserProfileUid(UserProfileUid::TEST));
        self::assertSame($UserProfileUid, $ManufacturePartInvariableDTO->getProfile());

        $ManufacturePartInvariableDTO->setNumber('174.456.053.366');
        self::assertSame('174.456.053.366', $ManufacturePartInvariableDTO->getNumber());

        /** @var ManufacturePartHandler $ManufacturePartHandler */
        $ManufacturePartHandler = self::getContainer()->get(ManufacturePartHandler::class);
        $handle = $ManufacturePartHandler->handle($ManufacturePartDTO);

        self::assertTrue(($handle instanceof ManufacturePart), $handle.': Ошибка ManufacturePart');

    }


    public function testComplete(): void
    {
        /** @var DBALQueryBuilder $dbal */
        $dbal = self::getContainer()->get(DBALQueryBuilder::class);

        $dbal->createQueryBuilder(self::class);

        $dbal->from(ManufacturePart::class)
            ->where('id = :id')
            ->setParameter('id', ManufacturePartUid::TEST);

        self::assertTrue($dbal->fetchExist());
    }
}