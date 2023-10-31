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

namespace BaksDev\Manufacture\Part\Messenger;

use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusDefect;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusPackage;
use BaksDev\Manufacture\Part\UseCase\Admin\Closed\ManufacturePartClosedDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\Closed\ManufacturePartClosedHandler;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final class ManufacturePartClosedByZero
{
    private EntityManagerInterface $entityManager;
    private ManufacturePartClosedHandler $manufacturePartClosedHandler;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        ManufacturePartClosedHandler $manufacturePartClosedHandler,
        LoggerInterface $messageDispatchLogger,
    )
    {
        $this->entityManager = $entityManager;
        $this->manufacturePartClosedHandler = $manufacturePartClosedHandler;
        $this->logger = $messageDispatchLogger;
    }

    /**
     * Закрываем заявку, если в рабочей партии отсутствуют товары
     */
    public function __invoke(ManufacturePartMessage $message): void
    {
        $this->entityManager->clear();

        $ManufacturePart = $this->entityManager->getRepository(ManufacturePart::class)->find($message->getId());

        if($ManufacturePart && $ManufacturePart->getQuantity() === 0)
        {
            /** Проверяем, что статус заявки - "На производстве" */
            $ManufacturePartEvent = $this->entityManager->getRepository(ManufacturePartEvent::class)->find($ManufacturePart->getEvent());

            if(
                $ManufacturePartEvent->getStatus()->equals(ManufacturePartStatusPackage::class) ||
                $ManufacturePartEvent->getStatus()->equals(ManufacturePartStatusDefect::class)
            )
            {
                /** Производственная партия выполнена (статус Closed) */
                $ManufacturePartCompletedDTO = new ManufacturePartClosedDTO($message->getEvent());
                $handle = $this->manufacturePartClosedHandler->handle($ManufacturePartCompletedDTO);

                if(!$handle instanceof ManufacturePart)
                {
                    throw new DomainException(sprintf('%s: Ошибка при закрытии (статус Closed) производственной партии', $handle));
                }

                $this->logger->info('Закрыли производственную партию м нулевым количеством', [
                    __FILE__.':'.__LINE__,
                    'class' => self::class,
                    'message' => sprintf("new %s(new %s('%s'),new %s('%s'));",
                        $message::class,
                        $message->getId()::class,
                        $message->getId(),
                        $message->getEvent()::class,
                        $message->getEvent()
                    )
                ]);
            }
        }
    }
}
