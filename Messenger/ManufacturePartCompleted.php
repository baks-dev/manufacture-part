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

use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Repository\ActiveWorkingManufacturePart\ActiveWorkingManufacturePartInterface;
use BaksDev\Manufacture\Part\Repository\ManufacturePartCurrentEvent\ManufacturePartCurrentEventInterface;
use BaksDev\Manufacture\Part\Repository\ProductsByManufacturePart\ProductsByManufacturePartInterface;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusDefect;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusPackage;
use BaksDev\Manufacture\Part\UseCase\Admin\Completed\ManufacturePartCompletedDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\Completed\ManufacturePartCompletedHandler;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final class ManufacturePartCompleted
{
    private ActiveWorkingManufacturePartInterface $activeWorkingManufacturePart;
    private ManufacturePartCompletedHandler $manufacturePartCompletedHandler;
    private CentrifugoPublishInterface $CentrifugoPublish;
    private ProductsByManufacturePartInterface $productsByManufacturePart;
    private LoggerInterface $logger;
    private ManufacturePartCurrentEventInterface $manufacturePartCurrentEvent;

    public function __construct(
        ActiveWorkingManufacturePartInterface $activeWorkingManufacturePart,
        ManufacturePartCompletedHandler $manufacturePartCompletedHandler,
        ProductsByManufacturePartInterface $productsByManufacturePart,
        CentrifugoPublishInterface $CentrifugoPublish,
        LoggerInterface $manufacturePartLogger,
        ManufacturePartCurrentEventInterface $manufacturePartCurrentEvent
    )
    {
        $this->activeWorkingManufacturePart = $activeWorkingManufacturePart;
        $this->manufacturePartCompletedHandler = $manufacturePartCompletedHandler;
        $this->CentrifugoPublish = $CentrifugoPublish;
        $this->productsByManufacturePart = $productsByManufacturePart;
        $this->logger = $manufacturePartLogger;
        $this->manufacturePartCurrentEvent = $manufacturePartCurrentEvent;
    }

    /**
     * Проверяем, имеется ли не выпаленное действие, если нет - заявка выполнена
     * (применяем статус Complete)
     */
    public function __invoke(ManufacturePartMessage $message): void
    {
        $ManufacturePartEvent = $this->manufacturePartCurrentEvent->findByManufacturePart($message->getId());

        if(!$ManufacturePartEvent)
        {
            return;
        }

        $this->logger->info('Проверяем, что производственная партия не выполнена', [
            self::class.':'.__LINE__,
            'class' => self::class,
            'message' => sprintf("new %s(new %s('%s'),new %s('%s'));",
                $message::class,
                $message->getId()::class,
                $message->getId(),
                $message->getEvent()::class,
                $message->getEvent()
            )
        ]);


        /** Проверяем, что статус заявки - PACKAGE «На сборке (упаковке)» || DEFECT «Дефект при производстве» */
        if(
            $ManufacturePartEvent->getStatus()->equals(ManufacturePartStatusPackage::class) === true ||
            $ManufacturePartEvent->getStatus()->equals(ManufacturePartStatusDefect::class) === true
        )
        {
            $working = $this->activeWorkingManufacturePart
                ->findNextWorkingByManufacturePart($message->getId());

            /** Если имеется этап производства */
            if($working)
            {
                return;
            }

            /** Производственная партия выполнена (статус Complete) */
            $ManufacturePartCompletedDTO = new ManufacturePartCompletedDTO($message->getEvent());
            $handle = $this->manufacturePartCompletedHandler->handle($ManufacturePartCompletedDTO);

            /** Получаем всю продукцию в партии и снимаем блокировку */
            $ProductsManufacture = $this->productsByManufacturePart
                ->getAllProductsByManufacturePart($message->getId());

            foreach($ProductsManufacture as $complete)
            {
                $identifier = $complete['product_event'];

                if($complete['product_offer_id'])
                {
                    $identifier = $complete['product_offer_id'];
                }
                if($complete['product_variation_id'])
                {
                    $identifier = $complete['product_variation_id'];
                }

                if($complete['product_modification_id'])
                {
                    $identifier = $complete['product_modification_id'];
                }

                /** Отправляем сокет сокет с идентификатором */
                $this->CentrifugoPublish
                    ->addData(['identifier' => $identifier]) // ID упаковки
                    ->send('remove');
            }

            if(!$handle instanceof ManufacturePart)
            {
                throw new DomainException(sprintf('%s: Ошибка при закрытии (статус Complete) производственной партии', $handle));
            }

            $this->logger->info('Производственная партия выполнена');
        }
    }
}
