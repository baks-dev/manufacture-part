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
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
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
use Doctrine\Common\Collections\ArrayCollection;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[Autoconfigure(shared: false)]
#[AsMessageHandler(priority: 0)]
final readonly class PackageProductStockByPackageOrderDispatcher
{
    public function __construct(
        #[Target('manufacturePartLogger')] private LoggerInterface $logger,
        private CurrentProductIdentifierByEventInterface $CurrentProductIdentifier,
        private PackageProductStockHandler $PackageProductStockHandler,
        private CurrentOrderEventInterface $CurrentOrderEvent,
        private DeduplicatorInterface $deduplicator,
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

        /** Только заказы в статусе New «Новый» либо Package «Упаковка заказов» */
        if(
            false === $OrderEvent->isStatusEquals(OrderStatusNew::class)
            && false === $OrderEvent->isStatusEquals(OrderStatusPackage::class)
        )
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

        /**
         * Обновляем статус заказа и присваиваем профиль склада упаковки.
         */

        $this->logger->info(
            sprintf(
                '%s: Создаем складскую заявку на упаковку',
                $OrderEvent->getPostingNumber(),
            ),
            [self::class.':'.__LINE__],
        );

        $DeduplicatorOrder->save();

        /**
         * Создаем складскую заявку на упаковку для резерва продукции
         */

        $PackageProductStockDTO = new PackageProductStockDTO();
        $OrderEvent->getDto($PackageProductStockDTO);

        // Присваиваем заявке идентификатор заказа
        $ProductStockOrderDTO = new PackageProductStockOrderDTO();
        $ProductStockOrderDTO->setOrd($OrderEvent->getMain());

        $PackageProductStockDTO->setProduct(new ArrayCollection());
        $PackageProductStockDTO->setOrd($ProductStockOrderDTO);


        $PackageOrderInvariableDTO = $PackageProductStockDTO->getInvariable();
        $PackageOrderInvariableDTO
            ->setUsr($OrderEvent->getOrderUser())
            ->setProfile($OrderEvent->getOrderProfile())
            ->setNumber($OrderEvent->getPostingNumber());


        /** Получаем PackageOrderDTO для коллекции продукции  */
        $PackageOrderDTO = new PackageOrderDTO();
        $OrderEvent->getDto($PackageOrderDTO);

        /** @var PackageOrderProductDTO $PackageOrderProductDTO */
        foreach($PackageOrderDTO->getProduct() as $PackageOrderProductDTO)
        {
            /** Получаем идентификаторы продукции по событию заказа */

            $CurrentProductIdentifier = $this->CurrentProductIdentifier
                ->forEvent($PackageOrderProductDTO->getProduct())
                ->forOffer($PackageOrderProductDTO->getOffer())
                ->forVariation($PackageOrderProductDTO->getVariation())
                ->forModification($PackageOrderProductDTO->getModification())
                ->find();

            if(false === ($CurrentProductIdentifier instanceof CurrentProductIdentifierResult))
            {
                continue;
            }

            $CollectionPackageProductStockDTO = new CollectionPackageProductStockDTO()
                ->setProduct($CurrentProductIdentifier->getProduct())
                ->setOffer($CurrentProductIdentifier->getOfferConst())
                ->setVariation($CurrentProductIdentifier->getVariationConst())
                ->setModification($CurrentProductIdentifier->getModificationConst())
                ->setTotal($PackageOrderProductDTO->getPrice()->getTotal());

            $PackageProductStockDTO->addProduct($CollectionPackageProductStockDTO);

        }

        $ProductStock = $this->PackageProductStockHandler->handle($PackageProductStockDTO);

        if(false === ($ProductStock instanceof ProductStock))
        {
            $this->logger->critical(
                sprintf('manufacture-part: Ошибка %s при создании заявки на упаковку заказа %s при производстве',
                    $ProductStock,
                    $OrderEvent->getPostingNumber(),
                ),
                [$message, self::class.':'.__LINE__],
            );
        }
    }
}
