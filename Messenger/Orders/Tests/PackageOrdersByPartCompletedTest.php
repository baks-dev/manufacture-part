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

namespace BaksDev\Manufacture\Part\Messenger\Orders\Tests;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Manufacture\Part\Messenger\ManufacturePartMessage;
use BaksDev\Manufacture\Part\Messenger\Orders\PackageOrdersByPartCompletedDispatcher;
use BaksDev\Manufacture\Part\Type\Event\ManufacturePartEventUid;
use BaksDev\Manufacture\Part\Type\Id\ManufacturePartUid;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;


/**
 * @group manufacture-part
 */
#[When(env: 'test')]
class PackageOrdersByPartCompletedTest extends KernelTestCase
{
    public function testUseCase(): void
    {
        // Бросаем событие консольной комманды
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $event = new ConsoleCommandEvent(new Command(), new StringInput(''), new NullOutput());
        $dispatcher->dispatch($event, 'console.command');

        /** @var PackageOrdersByPartCompletedDispatcher $PackageOrdersByPartCompleted */
        $PackageOrdersByPartCompleted = self::getContainer()->get(PackageOrdersByPartCompletedDispatcher::class);


        $ManufacturePartMessage = new ManufacturePartMessage(
            new ManufacturePartUid('01951617-5dcb-7634-bcc0-f574233f73e4'),
            new ManufacturePartEventUid('01951618-7d4c-70ef-8dcd-8551151816c5')
        );

        $dispatch = $PackageOrdersByPartCompleted($ManufacturePartMessage);

        self::assertFalse($dispatch);
        //self::assertTrue($dispatch);
    }
}