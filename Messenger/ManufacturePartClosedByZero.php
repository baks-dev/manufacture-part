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

namespace BaksDev\Manufacture\Part\Messenger;

use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Repository\ManufacturePartCurrentEvent\ManufacturePartCurrentEventInterface;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusDefect;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusPackage;
use BaksDev\Manufacture\Part\UseCase\Admin\Closed\ManufacturePartClosedDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\Closed\ManufacturePartClosedHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final readonly class ManufacturePartClosedByZero
{
    public function __construct(
        #[Target('manufacturePartLogger')] private LoggerInterface $logger,
        private ManufacturePartClosedHandler $manufacturePartClosedHandler,
        private ManufacturePartCurrentEventInterface $ManufacturePartCurrentEvent
    ) {}

    /**
     * Закрываем заявку, если в рабочей партии отсутствуют товары
     */
    public function __invoke(ManufacturePartMessage $message): bool
    {
        $ManufacturePartEvent = $this->ManufacturePartCurrentEvent
            ->fromPart($message->getId())
            ->find();

        if(false === ($ManufacturePartEvent instanceof ManufacturePartEvent))
        {
            return false;
        }

        if(false === empty($ManufacturePartEvent->getQuantity()))
        {
            return false;
        }


        /**
         * Проверяем, что статус заявки - PACKAGE «На сборке (упаковке)» || DEFECT «Дефект при производстве»
         */
        if(
            false === (
                $ManufacturePartEvent->getStatus()->equals(ManufacturePartStatusPackage::class) ||
                $ManufacturePartEvent->getStatus()->equals(ManufacturePartStatusDefect::class)
            )
        )
        {
            return false;
        }

        /** Производственная партия закрыта (статус Closed) */
        $ManufacturePartCompletedDTO = new ManufacturePartClosedDTO();
        $ManufacturePartEvent->getDto($ManufacturePartCompletedDTO);

        $handle = $this->manufacturePartClosedHandler->handle($ManufacturePartCompletedDTO);

        if(false === ($handle instanceof ManufacturePart))
        {
            $this->logger->critical(
                sprintf('manufacture-part: Ошибка %s при закрытии производственной партии c нулевым количеством', $handle),
                [$message, self::class.':'.__LINE__,]
            );

            return false;
        }

        $this->logger->info(
            'Закрыли производственную партию c нулевым количеством',
            [$message, self::class.':'.__LINE__,]
        );

        return true;

    }
}
