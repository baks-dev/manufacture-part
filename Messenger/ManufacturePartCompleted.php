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

use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Entity\Products\ManufacturePartProduct;
use BaksDev\Manufacture\Part\Repository\ActiveWorkingManufacturePart\ActiveWorkingManufacturePartInterface;
use BaksDev\Manufacture\Part\Repository\ManufacturePartCurrentEvent\ManufacturePartCurrentEventInterface;
use BaksDev\Manufacture\Part\Repository\ManufacturePartEvent\ManufacturePartEventInterface;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusCompleted;
use BaksDev\Manufacture\Part\UseCase\Admin\Completed\ManufacturePartCompletedDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\Completed\ManufacturePartCompletedHandler;
use BaksDev\Users\UsersTable\Type\Actions\Working\UsersTableActionsWorkingUid;
use DomainException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Проверяем, имеется ли не выполненное действие, если нет - заявка выполнена (применяем статус Complete)
 */
#[AsMessageHandler(priority: 999)]
final readonly class ManufacturePartCompleted
{
    public function __construct(
        #[Target('manufacturePartLogger')] private LoggerInterface $logger,
        private ManufacturePartEventInterface $ManufacturePartEventRepository,
        private ManufacturePartCurrentEventInterface $ManufacturePartCurrentEvent,
        private ActiveWorkingManufacturePartInterface $activeWorkingManufacturePart,
        private ManufacturePartCompletedHandler $manufacturePartCompletedHandler,
        private CentrifugoPublishInterface $CentrifugoPublish,
        private DeduplicatorInterface $deduplicator,
    ) {}


    public function __invoke(ManufacturePartMessage $message): void
    {
        $DeduplicatorExecuted = $this
            ->deduplicator
            ->namespace('manufacture-part')
            ->deduplication([(string) $message->getId(), self::class]);

        if($DeduplicatorExecuted->isExecuted())
        {
            return;
        }

        $ManufacturePartEvent = $this->ManufacturePartCurrentEvent
            ->fromPart($message->getId())
            ->find();

        if(false === ($ManufacturePartEvent instanceof ManufacturePartEvent))
        {
            return;
        }

        $this->logger->info(
            'Проверяем, что производственная партия не выполнена',
            [var_export($message, true), self::class.':'.__LINE__]
        );

        if(true === $ManufacturePartEvent->getStatus()->equals(ManufacturePartStatusCompleted::class))
        {
            return;
        }

        $working = $this->activeWorkingManufacturePart
            ->findNextWorkingByManufacturePart($message->getId());

        /** Если имеется этап производства - партия еще на стадии производства */
        if($working instanceof UsersTableActionsWorkingUid)
        {
            return;
        }

        $products = $ManufacturePartEvent->getProduct();

        /** @var ManufacturePartProduct $product */

        foreach($products as $product)
        {
            $identifier = $product->getProduct();
            false === $product->getOffer() ?: $identifier = $product->getOffer();
            false === $product->getVariation() ?: $identifier = $product->getVariation();
            false === $product->getModification() ?: $identifier = $product->getModification();

            /** Отправляем сокет с идентификатором */
            $this->CentrifugoPublish
                ->addData(['identifier' => $identifier]) // ID упаковки
                ->send('remove');
        }


        /** Производственная партия полностью выполнена (статус Complete) */
        $ManufacturePartCompletedDTO = new ManufacturePartCompletedDTO($message->getEvent());
        $handle = $this->manufacturePartCompletedHandler->handle($ManufacturePartCompletedDTO);

        if(false === ($handle instanceof ManufacturePart))
        {
            throw new DomainException(sprintf('%s: Ошибка при полном выполнении (статус Complete) производственной партии', $handle));
        }

        $this->logger->info('Производственная партия выполнена');

        $DeduplicatorExecuted->save();

    }
}
