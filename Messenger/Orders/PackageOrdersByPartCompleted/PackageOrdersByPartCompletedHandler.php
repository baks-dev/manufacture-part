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

namespace BaksDev\Manufacture\Part\Messenger\Orders\PackageOrdersByPartCompleted;


use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\Invariable\ManufacturePartInvariable;
use BaksDev\Manufacture\Part\Messenger\ManufacturePartMessage;
use BaksDev\Manufacture\Part\Messenger\ProductStocks\PackageProductStockByPartCompletedHandler;
use BaksDev\Manufacture\Part\Repository\ManufacturePartCurrentEvent\ManufacturePartCurrentEventInterface;
use BaksDev\Manufacture\Part\Repository\ManufacturePartInvariable\ManufacturePartInvariableInterface;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusCompleted;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\ManufacturePartDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\Products\ManufacturePartProductsDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\Products\Orders\ManufacturePartProductOrderDTO;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusNew;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusPackage;
use BaksDev\Orders\Order\UseCase\Admin\Access\AccessOrderDTO;
use BaksDev\Orders\Order\UseCase\Admin\Status\OrderStatusDTO;
use BaksDev\Orders\Order\UseCase\Admin\Status\OrderStatusHandler;
use BaksDev\Ozon\Orders\Type\DeliveryType\TypeDeliveryFbsOzon;
use BaksDev\Wildberries\Orders\Type\DeliveryType\TypeDeliveryFboWildberries;
use BaksDev\Wildberries\Orders\Type\DeliveryType\TypeDeliveryFbsWildberries;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * На добавленные в производственную партию заказы - отправляет на упаковку заказы со статусом «NEW»
 *
 * @see ManufacturePartProductOrderByPartCompletedDispatcher
 */
#[Autoconfigure(shared: false)]
#[AsMessageHandler(priority: 30)]
final readonly class PackageOrdersByPartCompletedHandler
{
    public function __construct(
        #[Target('manufacturePartLogger')] private LoggerInterface $logger,
        private ManufacturePartCurrentEventInterface $ManufacturePartCurrentEvent,
        private DeduplicatorInterface $deduplicator,
        private ManufacturePartInvariableInterface $ManufacturePartInvariableRepository,
        private MessageDispatchInterface $messageDispatch,
    ) {}

    /**
     *
     * @note В первую очередь создается складская заявка
     * @see PackageProductStockByPartCompletedHandler
     */
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
                [var_export($message, true), self::class.':'.__LINE__],
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
         *
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

        /** Завершаем, если завершающий этап не связан с обработкой заказов */
        if(false === $orderType)
        {
            return false;
        }


        $ManufacturePartInvariable = $this->ManufacturePartInvariableRepository
            ->forPart($message->getId())
            ->find();

        if(false === ($ManufacturePartInvariable instanceof ManufacturePartInvariable))
        {
            return false;
        }

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
                /**
                 * Обновляем статус заказа и присваиваем профиль склада упаковки.
                 */

                $PackageOrdersByPartCompletedMessage = new PackageOrdersByPartCompletedMessage(
                    $ManufacturePartProductOrderDTO->getOrd(),
                    $ManufacturePartEvent->getInvariable()->getProfile(),
                    $ManufacturePartEvent->getInvariable()->getNumber(),
                );

                $this->messageDispatch->dispatch(
                    message: $PackageOrdersByPartCompletedMessage,
                    transport: 'orders-order',
                );
            }
        }

        $DeduplicatorExecuted->save();

        return true;
    }
}
