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


use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusNew;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusPackage;
use BaksDev\Orders\Order\UseCase\Admin\Access\AccessOrderDTO;
use BaksDev\Orders\Order\UseCase\Admin\Status\OrderStatusDTO;
use BaksDev\Orders\Order\UseCase\Admin\Status\OrderStatusHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * На добавленные в производственную партию заказы - отправляет на упаковку заказы со статусом «NEW»
 */
#[Autoconfigure(shared: false)]
#[AsMessageHandler(priority: 0)]
final class PackageOrdersByPartCompletedDispatcher
{
    public function __construct(
        #[Target('manufacturePartLogger')] private LoggerInterface $logger,
        private readonly CurrentOrderEventInterface $CurrentOrderEventRepository,
        private OrderStatusHandler $OrderStatusHandler,
    ) {}

    public function __invoke(PackageOrdersByPartCompletedMessage $message): void
    {
        $CurrentOrderEvent = $this->CurrentOrderEventRepository
            ->forOrder($message->getOrderId())
            ->find();

        if(false === $CurrentOrderEvent instanceof OrderEvent)
        {
            $this->logger->critical(
                'manufacture-part: Заказ для отправки на упаковку после производственного процесса не найден',
                [self::class.':'.__LINE__, (string) $message->getOrderId()],
            );

            return;
        }

        /** Только заказы в статусе NEW «Новый» */
        if(false === $CurrentOrderEvent->isStatusEquals(OrderStatusNew::class))
        {
            $this->logger->warning(
                sprintf(
                    'manufacture-part: Заказ %s не в статусе «Новый»',
                    $CurrentOrderEvent->getPostingNumber(),
                ),
                [self::class.':'.__LINE__, (string) $message->getOrderId()],
            );

            return;
        }


        /**
         * Проверяем что вся продукция в заказе готова к сборке
         */

        $AccessOrderDTO = new AccessOrderDTO();
        $CurrentOrderEvent->getDto($AccessOrderDTO);

        foreach($AccessOrderDTO->getProduct() as $AccessOrderProductDTO)
        {
            $AccessOrderPriceDTO = $AccessOrderProductDTO->getPrice();

            if(false === $AccessOrderPriceDTO->isAccess())
            {
                $this->logger->critical(
                    sprintf(
                        'manufacture-part: Продукция в заказе %s еще не готова к упаковке',
                        $CurrentOrderEvent->getPostingNumber(),
                    ),
                    [self::class.':'.__LINE__, (string) $message->getOrderId()],
                );

                return;
            }
        }

        $OrderStatusDTO = new OrderStatusDTO(
            OrderStatusPackage::class,
            $CurrentOrderEvent->getId(),
        );

        // Добавляем в комментарий идентификатор производственной партии и присваиваем склад
        $OrderStatusDTO
            ->setProfile($message->getProfile())
            ->addComment($message->getPartNumber());


        /** @var OrderStatusHandler $statusHandler */
        $Order = $this->OrderStatusHandler->handle($OrderStatusDTO);

        if(false === ($Order instanceof Order))
        {
            $this->logger->critical(
                sprintf('manufacture-part: Ошибка %s при обновлении заказа со статусом «Упаковка»', $Order),
                [self::class.':'.__LINE__],
            );

            return;
        }

        $this->logger->info(
            sprintf(
                '%s: Отправили заказ на упаковку после производственного процесса',
                $CurrentOrderEvent->getPostingNumber(),
            ), [self::class.':'.__LINE__],
        );
    }
}
