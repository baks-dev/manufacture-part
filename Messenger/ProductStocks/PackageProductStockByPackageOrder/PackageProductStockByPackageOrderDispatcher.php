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

namespace BaksDev\Manufacture\Part\Messenger\ProductStocks\PackageProductStockByPackageOrder;


use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Messenger\MultiplyOrdersPackage\MultiplyOrdersPackageMessage;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusNew;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusPackage;
use BaksDev\Orders\Order\UseCase\Admin\Access\AccessOrderDTO;
use BaksDev\Orders\Order\UseCase\Admin\Package\PackageOrderDTO;
use BaksDev\Orders\Order\UseCase\Admin\Package\Products\PackageOrderProductDTO;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierByEventInterface;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierResult;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\UseCase\Admin\Package\Orders\PackageProductStockOrderDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Package\PackageProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Package\PackageProductStockHandler;
use BaksDev\Products\Stocks\UseCase\Admin\Package\Products\CollectionPackageProductStockDTO;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\Common\Collections\ArrayCollection;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * На добавленные в производственную партию заказы - создает складскую заявку, готовые к упаковке (total === access)
 */
#[Autoconfigure(shared: false)]
#[AsMessageHandler(priority: 0)]
final readonly class PackageProductStockByPackageOrderDispatcher
{
    public function __construct(
        #[Target('manufacturePartLogger')] private LoggerInterface $logger,
        private CurrentOrderEventInterface $CurrentOrderEvent,
        private DeduplicatorInterface $deduplicator,
        private MessageDispatchInterface $messageDispatch,
    ) {}

    public function __invoke(PackageProductStockByPackageOrderMessage $message): void
    {
        $DeduplicatorOrder = $this->deduplicator
            ->deduplication([$message->getOrder(), self::class]);

        if($DeduplicatorOrder->isExecuted())
        {
            return;
        }

        $OrderEvent = $this->CurrentOrderEvent
            ->forOrder($message->getOrder())
            ->find();

        if(false === ($OrderEvent instanceof OrderEvent))
        {
            $this->logger->critical(
                sprintf(
                    '%s: заказ не является статусом «Новый» или «Упаковка заказов»',
                    $OrderEvent->getPostingNumber(),
                ),
                [$message, self::class.':'.__LINE__],
            );

            return;
        }

        /** Только заказы в статусе New «Новый» */
        if(false === $OrderEvent->isStatusEquals(OrderStatusNew::class))
        {
            $this->logger->critical(
                sprintf(
                    'manufacture-part: заказ %s не является статусом «Новый» или «Упаковка заказов»',
                    $OrderEvent->getPostingNumber(),
                ),
                [$message, self::class.':'.__LINE__],
            );

            return;
        }

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

        if(false === $isPackage)
        {
            return;
        }

        $this->logger->info(
            sprintf(
                '%s: Отправляем заказ на упаковку',
                $OrderEvent->getPostingNumber(),
            ),
            [self::class.':'.__LINE__],
        );

        $MultiplyOrdersPackageMessage = new MultiplyOrdersPackageMessage(
            $OrderEvent->getMain(),
            $OrderEvent->getOrderProfile(),
            true === ($OrderEvent->getModifyUser() instanceof UserUid) ? $OrderEvent->getModifyUser() : $OrderEvent->getOrderUser(),
        );

        $this->messageDispatch->dispatch(
            message: $MultiplyOrdersPackageMessage,
            transport: 'orders-order',
        );
    }
}
