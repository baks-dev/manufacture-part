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

namespace BaksDev\Manufacture\Part\UseCase\Admin\Delete\Tests;


use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Repository\ManufacturePartCurrentEvent\ManufacturePartCurrentEventInterface;
use BaksDev\Manufacture\Part\Type\Id\ManufacturePartUid;
use BaksDev\Manufacture\Part\UseCase\Admin\Completed\Tests\ManufacturePartCompletedHandlerTest;
use BaksDev\Manufacture\Part\UseCase\Admin\Delete\ManufacturePartDeleteDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\Delete\ManufacturePartDeleteHandler;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\ManufacturePartHandler;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\Tests\ManufacturePartEditHandlerTest;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\Tests\ManufacturePartNewHandlerTest;
use BaksDev\Manufacture\Part\UseCase\Admin\Package\Tests\ManufacturePartPackageHandlerTest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * @group manufacture-part
 * @group manufacture-part-usecase
 *
 * @depends BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\Tests\ManufacturePartNewHandlerTest::class
 * @depends BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\Tests\ManufacturePartEditHandlerTest::class
 * @depends BaksDev\Manufacture\Part\UseCase\Admin\Package\Tests\ManufacturePartPackageHandlerTest::class
 */
#[When(env: 'test')]
class ManufacturePartDeleteHandlerTest extends KernelTestCase
{
    public static function tearDownAfterClass(): void
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $main = $em->getRepository(ManufacturePart::class)
            ->findOneBy(['id' => ManufacturePartUid::TEST]);

        if($main)
        {
            $em->remove($main);
        }


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
        /** @var ManufacturePartCurrentEventInterface $ManufacturePartCurrentEvent */
        $ManufacturePartCurrentEvent = self::getContainer()->get(ManufacturePartCurrentEventInterface::class);
        $ManufacturePartEvent = $ManufacturePartCurrentEvent
            ->fromPart(ManufacturePartUid::TEST)
            ->find();
        self::assertNotNull($ManufacturePartEvent);

        //dump((string) $ManufacturePartEvent->getId());

        /** @see ManufacturePartDeleteDTO */
        $ManufacturePartDeleteDTO = new ManufacturePartDeleteDTO();
        $ManufacturePartEvent->getDto($ManufacturePartDeleteDTO);


        /** @var ManufacturePartHandler $ManufacturePartHandler */
        $ManufacturePartDeleteHandler = self::getContainer()->get(ManufacturePartDeleteHandler::class);
        $handle = $ManufacturePartDeleteHandler->handle($ManufacturePartDeleteDTO);

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

        self::assertFalse($dbal->fetchExist());

    }
}