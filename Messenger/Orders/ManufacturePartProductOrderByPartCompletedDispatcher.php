<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Manufacture\Part\Messenger\Orders;


use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Delivery\Type\Id\DeliveryUid;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Messenger\ManufacturePartMessage;
use BaksDev\Manufacture\Part\Repository\ManufacturePartCurrentEvent\ManufacturePartCurrentEventInterface;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusCompleted;
use BaksDev\Manufacture\Part\UseCase\Admin\Orders\OrdersManufacturePartDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\Orders\OrdersManufacturePartHandler;
use BaksDev\Manufacture\Part\UseCase\Admin\Orders\Products\ManufacturePartProductsDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\Orders\Products\Orders\ManufacturePartProductOrderDTO;
use BaksDev\Orders\Order\Repository\RelevantNewOrderByProduct\RelevantNewOrderByProductInterface;
use BaksDev\Orders\Order\UseCase\Admin\Access\AccessOrderDTO;
use BaksDev\Ozon\Orders\Type\DeliveryType\TypeDeliveryFbsOzon;
use BaksDev\Wildberries\Orders\Type\DeliveryType\TypeDeliveryFboWildberries;
use BaksDev\Wildberries\Orders\Type\DeliveryType\TypeDeliveryFbsWildberries;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Обновляем производственную партию идентификаторами заказов со статусом «NEW», и готовых к упаковке (total === access)
 * @see AccessOrderProductByPartCompletedDispatcher
 */
#[Autoconfigure(public: true)]
#[AsMessageHandler(priority: 50)]
final readonly class ManufacturePartProductOrderByPartCompletedDispatcher
{
    public function __construct(
        #[Target('manufacturePartLogger')] private LoggerInterface $logger,
        private ManufacturePartCurrentEventInterface $ManufacturePartCurrentEvent,
        private RelevantNewOrderByProductInterface $RelevantNewOrderByProduct,
        private OrdersManufacturePartHandler $OrdersManufacturePartHandler,
        private DeduplicatorInterface $deduplicator,
    ) {}

    public function __invoke(ManufacturePartMessage $message): bool
    {
        $DeduplicatorExecuted = $this
            ->deduplicator
            ->namespace('manufacture-part')
            ->deduplication([(string) $message->getId(), self::class]);

        if($DeduplicatorExecuted->isExecuted())
        {
            return true;
        }

        $ManufacturePartEvent = $this->ManufacturePartCurrentEvent
            ->fromPart($message->getId())
            ->find();

        if(false === ($ManufacturePartEvent instanceof ManufacturePartEvent))
        {
            $this->logger->error(
                'manufacture-part: ManufacturePartEvent не определено',
                [var_export($message, true), self::class.':'.__LINE__]
            );

            return false;
        }

        if(false === $ManufacturePartEvent->equalsManufacturePartStatus(ManufacturePartStatusCompleted::class))
        {
            return true;
        }

        /**
         * Определяем тип производства для заказов
         * доступно только для заказов типа FBS и FBO (DBS перемещаются в ручную)
         */

        $orderType = match (true)
        {
            /* FBS Wb */
            $ManufacturePartEvent->equalsManufacturePartComplete(TypeDeliveryFbsWildberries::class) => TypeDeliveryFbsWildberries::TYPE,
            /* FBO Wb*/
            $ManufacturePartEvent->equalsManufacturePartComplete(TypeDeliveryFboWildberries::class) => TypeDeliveryFboWildberries::TYPE,

            /* FBS Ozon */
            $ManufacturePartEvent->equalsManufacturePartComplete(TypeDeliveryFbsOzon::class) => TypeDeliveryFbsOzon::TYPE,


            default => false,
        };

        if(false === $orderType)
        {
            return false;
        }

        //        $ManufacturePartEvent = $this->ManufacturePartCurrentEvent
        //            ->fromPart($message->getId())
        //            ->find();

        $ManufacturePartDTO = new OrdersManufacturePartDTO();
        $ManufacturePartEvent->getDto($ManufacturePartDTO);

        $DeliveryUid = new DeliveryUid($orderType);

        /** @var ManufacturePartProductsDTO $ManufacturePartProductsDTO */

        foreach($ManufacturePartDTO->getProduct() as $ManufacturePartProductsDTO)
        {
            /**
             * Получаем все релевантные заказы, готовые к упаковке из расчета, что в заказе только 1 ед. продукции
             */

            $orders = $this->RelevantNewOrderByProduct
                ->forDelivery($DeliveryUid)
                ->forProductEvent($ManufacturePartProductsDTO->getProduct())
                ->forOffer($ManufacturePartProductsDTO->getOffer())
                ->forVariation($ManufacturePartProductsDTO->getVariation())
                ->forModification($ManufacturePartProductsDTO->getModification())
                ->onlyNewStatus() // только новые
                ->filterProductNotAccess() // только готовые к упаковке
                ->findAll();

            if(false === $orders)
            {
                continue;
            }

            $total = $ManufacturePartProductsDTO->getTotal();

            foreach($orders as $OrderEvent)
            {
                /** Если количество заказов в упаковке равное произведенной продукции - завершаем цикл*/
                if($ManufacturePartProductsDTO->getOrd()->count() === $total)
                {
                    break;
                }

                $AccessOrderDTO = new AccessOrderDTO();
                $OrderEvent->getDto($AccessOrderDTO);

                $isPackage = true;

                foreach($AccessOrderDTO->getProduct() as $AccessOrderProductDTO)
                {
                    $AccessOrderPriceDTO = $AccessOrderProductDTO->getPrice();

                    // Пропускаем, если есть продукция в заказе НЕ ГОТОВАЯ к сборке
                    if(false === $AccessOrderPriceDTO->isAccess())
                    {
                        $isPackage = false;
                        break;
                    }
                }

                if(false === $isPackage)
                {
                    continue;
                }

                $this->logger->info(
                    'Добавляем заказ к производственной партии',
                    [$OrderEvent->getOrderNumber(), self::class.':'.__LINE__]
                );

                /** Присваиваем заказ продукции в производственной партии */
                $ManufacturePartProductOrderDTO = new ManufacturePartProductOrderDTO()->setOrd($OrderEvent->getMain());
                $ManufacturePartProductsDTO->addOrd($ManufacturePartProductOrderDTO);
            }

            /** Сохраняем производственную партию */

            $this->logger->info(
                'Сохраняем производственную партию с указанными заказами к продукции',
                [self::class.':'.__LINE__]
            );

        }

        $ManufacturePart = $this->OrdersManufacturePartHandler->handle($ManufacturePartDTO);

        if(false === ($ManufacturePart instanceof ManufacturePart))
        {
            $this->logger->critical(
                sprintf('manufacture-part: Ошибка %s при обновлении производственной партии', $ManufacturePart),
                [$message, self::class.':'.__LINE__]
            );
        }

        /**
         * Приступаем к упаковке заказов
         * @see PackageOrdersByPartCompletedDispatcher
         */

        $DeduplicatorExecuted->save();

        return true;
    }
}