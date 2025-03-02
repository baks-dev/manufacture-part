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

namespace BaksDev\Manufacture\Part\Messenger\Orders;


use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Delivery\Type\Id\DeliveryUid;
use BaksDev\Manufacture\Part\Messenger\ManufacturePartMessage;
use BaksDev\Manufacture\Part\Repository\ManufacturePartCurrentEvent\ManufacturePartCurrentEventInterface;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusCompleted;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\ManufacturePartDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\Products\ManufacturePartProductsDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\Products\Orders\ManufacturePartProductOrderDTO;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\OrderStatusNew;
use BaksDev\Orders\Order\Type\Status\OrderStatus\OrderStatusPackage;
use BaksDev\Orders\Order\UseCase\Admin\Access\AccessOrderDTO;
use BaksDev\Orders\Order\UseCase\Admin\Status\OrderStatusDTO;
use BaksDev\Orders\Order\UseCase\Admin\Status\OrderStatusHandler;
use BaksDev\Wildberries\Manufacture\Type\ManufacturePartComplete\ManufacturePartCompleteWildberriesFbs;
use BaksDev\Wildberries\Orders\Type\DeliveryType\TypeDeliveryFbsWildberries;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * На добавленные в производственную партию заказы - отправляет на упаковку заказы со статусом «NEW»
 * @see ManufacturePartProductOrderByPartCompletedDispatcher
 */
#[AsMessageHandler(priority: 30)]
final readonly class PackageOrdersByPartCompletedDispatcher
{
    public function __construct(
        #[Target('manufacturePartLogger')] private LoggerInterface $logger,
        private ManufacturePartCurrentEventInterface $ManufacturePartCurrentEvent,
        private OrderStatusHandler $OrderStatusHandler,
        private CurrentOrderEventInterface $CurrentOrderEvent,
        private DeduplicatorInterface $deduplicator,
    ) {}

    /**
     *
     * @note В первую очередь создается складская заявка
     * @see PackageProductStockByPartComplete
     */
    public function __invoke(ManufacturePartMessage $message): bool
    {
        $DeduplicatorExecuted = $this
            ->deduplicator
            ->namespace('wildberries-package')
            ->deduplication([(string) $message->getEvent(), self::class]);

        if($DeduplicatorExecuted->isExecuted())
        {
            return true;
        }

        $ManufacturePartEvent = $this->ManufacturePartCurrentEvent
            ->fromPart($message->getId())
            ->find();

        if(false === $ManufacturePartEvent)
        {
            $this->logger->critical(
                'manufacture-part: ManufacturePartEvent не определено',
                [$message, self::class.':'.__LINE__]
            );

            return false;
        }

        if(false === $ManufacturePartEvent->equalsManufacturePartStatus(ManufacturePartStatusCompleted::class))
        {
            return true;
        }

        /**
         * Определяем тип производства для заказов
         * доступно только для заказов типа FBS (DBS перемещаются в ручную)
         *
         * TODO: Переделать завершающие этапы на типы доставки
         */

        $orderType = match (true)
        {
            $ManufacturePartEvent->equalsManufacturePartComplete(ManufacturePartCompleteWildberriesFbs::class) => TypeDeliveryFbsWildberries::TYPE,
            default => false,
        };

        /** Завершаем, если завершающий этап не связан с обработкой заказов */
        if(false === $orderType)
        {
            return false;
        }


        $DeliveryUid = new DeliveryUid($orderType);

        $ManufacturePartDTO = new ManufacturePartDTO();
        $ManufacturePartEvent->getDto($ManufacturePartDTO);

        /** @var ManufacturePartProductsDTO $ManufacturePartProductsDTO */
        foreach($ManufacturePartDTO->getProduct() as $ManufacturePartProductsDTO)
        {
            if($ManufacturePartProductsDTO->getOrd()->isEmpty())
            {
                continue;
            }

            /** @var ManufacturePartProductOrderDTO $ManufacturePartProductOrderDTO */
            foreach($ManufacturePartProductsDTO->getOrd() as $ManufacturePartProductOrderDTO)
            {
                $OrderUid = $ManufacturePartProductOrderDTO->getOrd();
                $OrderEvent = $this->CurrentOrderEvent
                    ->forOrder($OrderUid)
                    ->find();

                if(false === $OrderEvent)
                {
                    continue;
                }

                /** Только заказы в статусе NEW «Новый» */
                if(false === $OrderEvent->isStatusEquals(OrderStatusNew::class))
                {
                    continue;
                }

                //                $DeduplicatorOrder = $this->deduplicator
                //                    ->deduplication([$OrderUid, self::class]);
                //
                //                if($DeduplicatorOrder->isExecuted())
                //                {
                //                    continue;
                //                }

                /**
                 * Проверяем что вся продукция в заказе готова к сборке
                 */

                $AccessOrderDTO = new AccessOrderDTO();
                $OrderEvent->getDto($AccessOrderDTO);

                $isPackage = true;

                foreach($AccessOrderDTO->getProduct() as $AccessOrderProductDTO)
                {
                    $AccessOrderPriceDTO = $AccessOrderProductDTO->getPrice();

                    if(false === $AccessOrderPriceDTO->isAccess())
                    {
                        $isPackage = false;
                        break;
                    }
                }

                /**
                 * Обновляем статус заказа и присваиваем профиль склада упаковки.
                 */

                if(true === $isPackage)
                {

                    $this->logger->info(
                        sprintf('%s: Отправляем заказ  на упаковку', $OrderEvent->getOrderNumber()),
                        [self::class.':'.__LINE__]
                    );

                    $OrderStatusDTO = new OrderStatusDTO(
                        OrderStatusPackage::class,
                        $OrderEvent->getId(),
                        $ManufacturePartEvent->getProfile()
                    );

                    /** @var OrderStatusHandler $statusHandler */
                    $OrderStatusHandler = $this->OrderStatusHandler->handle($OrderStatusDTO);

                    if(false === ($OrderStatusHandler instanceof Order))
                    {
                        $this->logger->critical(
                            'manufacture-part: Ошибка при обновлении заказа со статусом «Упаковка»',
                            [self::class.':'.__LINE__]
                        );
                    }

                    //$DeduplicatorOrder->save();
                }
            }
        }

        $DeduplicatorExecuted->save();

        return true;
    }
}