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
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Messenger\ManufacturePartMessage;
use BaksDev\Manufacture\Part\Repository\ManufacturePartCurrentEvent\ManufacturePartCurrentEventInterface;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusCompleted;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\ManufacturePartDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\ManufacturePartHandler;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\Products\ManufacturePartProductsDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\Products\Orders\ManufacturePartProductOrderDTO;
use BaksDev\Orders\Order\Repository\RelevantNewOrderByProduct\RelevantNewOrderByProductInterface;
use BaksDev\Orders\Order\Repository\UpdateAccessOrderProduct\UpdateAccessOrderProductInterface;
use BaksDev\Orders\Order\UseCase\Admin\Access\AccessOrderDTO;
use BaksDev\Orders\Order\UseCase\Admin\Access\Products\AccessOrderProductDTO;
use BaksDev\Orders\Order\UseCase\Admin\Status\OrderStatusHandler;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierInterface;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use BaksDev\Products\Stocks\UseCase\Admin\Package\PackageProductStockHandler;
use BaksDev\Users\Profile\UserProfile\Repository\UserByUserProfile\UserByUserProfileInterface;
use BaksDev\Wildberries\Manufacture\Type\ManufacturePartComplete\ManufacturePartCompleteWildberriesFbs;
use BaksDev\Wildberries\Orders\Type\DeliveryType\TypeDeliveryFbsWildberries;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 10)]
final readonly class ManufacturePartProductOrderByPartCompleted
{
    public function __construct(
        #[Target('manufacturePartLogger')] private LoggerInterface $logger,
        private ManufacturePartCurrentEventInterface $ManufacturePartCurrentEvent,
        private RelevantNewOrderByProductInterface $RelevantNewOrderByProduct,
        private UpdateAccessOrderProductInterface $UpdateAccessOrderProduct,
        private ManufacturePartHandler $ManufacturePartHandler,
        private DeduplicatorInterface $deduplicator,
    )
    {
        $this->deduplicator->namespace('wildberries-package');
    }

    /**
     * Обновляем продукцию в производственной партии идентификаторами заказов
     */
    public function __invoke(ManufacturePartMessage $message): bool
    {
        $DeduplicatorExecuted = $this
            ->deduplicator
            ->deduplication([(string) $message->getId(), self::class]);

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

        //        /** Получаем всю продукцию в производственной партии */
        //
        //        $ProductsManufacture = $this->ProductsByManufacturePart
        //            ->forPart($message->getId())
        //            ->findAll();

        $ManufacturePartDTO = new ManufacturePartDTO();
        $ManufacturePartEvent->getDto($ManufacturePartDTO);


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

        if(false === $orderType)
        {
            return false;
        }

        $DeliveryUid = new DeliveryUid($orderType);

        $isPackage = false;

        /** @var ManufacturePartProductsDTO $ManufacturePartProductsDTO */

        foreach($ManufacturePartDTO->getProduct() as $ManufacturePartProductsDTO)
        {
            //            $ProductEventUid = new ProductEventUid($product['product_event']);
            //            $ProductOfferUid = $product['product_offer_id'] ? new ProductOfferUid($product['product_offer_id']) : false;
            //            $ProductVariationUid = $product['product_variation_id'] ? new ProductVariationUid($product['product_variation_id']) : false;
            //            $ProductModificationUid = $product['product_modification_id'] ? new ProductModificationUid($product['product_modification_id']) : false;

            /**
             * Перебираем все количество продукции в производственной партии
             */

            // $total = $product['product_total'];
            $total = $ManufacturePartProductsDTO->getTotal();

            for($i = 1; $i <= $total; $i++)
            {
                /** Получаем заказ со статусом НОВЫЙ на данную продукцию */

                $OrderEvent = $this->RelevantNewOrderByProduct
                    ->forDelivery($DeliveryUid)
                    ->forProductEvent($ManufacturePartProductsDTO->getProduct())
                    ->forOffer($ManufacturePartProductsDTO->getOffer())
                    ->forVariation($ManufacturePartProductsDTO->getVariation())
                    ->forModification($ManufacturePartProductsDTO->getModification())
                    ->onlyNewStatus() // только новые
                    ->filterProductAccess() // только требующие производства
                    ->find();


                /**
                 * Приступаем к следующему продукту в случае отсутствия заказов
                 */

                if(false === $OrderEvent)
                {
                    continue 2;
                }


                $DeduplicatorOrder = $this->deduplicator
                    ->deduplication([(string) $OrderEvent->getMain(), self::class]);

                if($DeduplicatorOrder->isExecuted())
                {
                    continue;
                }

                $this->logger->critical(
                    'Добавляем продукцию к заказу',
                    [$OrderEvent->getOrderNumber(), self::class.':'.__LINE__]
                );

                $AccessOrderDTO = new AccessOrderDTO();
                $OrderEvent->getDto($AccessOrderDTO);

                /** @var AccessOrderProductDTO $AccessOrderProductDTO */
                foreach($AccessOrderDTO->getProduct() as $AccessOrderProductDTO)
                {
                    /** Если коллекция заказов равна количество произведенного (total) - приступаем к следующему продукту */
                    if($ManufacturePartProductsDTO->isAccess())
                    {
                        continue 3;
                    }

                    /**
                     * Проверяем, что продукт в заказе соответствует идентификаторам производства
                     */
                    if(false === $AccessOrderProductDTO->getProduct()->equals($ManufacturePartProductsDTO->getProduct()))
                    {
                        continue;
                    }

                    if(
                        $AccessOrderProductDTO->getOffer() instanceof ProductOfferUid &&
                        false === $AccessOrderProductDTO->getOffer()->equals($ManufacturePartProductsDTO->getOffer())
                    )
                    {
                        continue;
                    }


                    if($ManufacturePartProductsDTO->getOffer() instanceof ProductOfferUid && true === is_null($AccessOrderProductDTO->getOffer()))
                    {
                        continue;
                    }


                    if($AccessOrderProductDTO->getVariation() instanceof ProductVariationUid && false === $AccessOrderProductDTO->getVariation()->equals($ManufacturePartProductsDTO->getVariation()))
                    {
                        continue;
                    }

                    if($ManufacturePartProductsDTO->getVariation() instanceof ProductVariationUid && true === is_null($AccessOrderProductDTO->getVariation()))
                    {
                        continue;
                    }


                    if($AccessOrderProductDTO->getModification() instanceof ProductModificationUid && false === $AccessOrderProductDTO->getModification()->equals($ManufacturePartProductsDTO->getModification()))
                    {
                        continue;
                    }


                    if($ManufacturePartProductsDTO->getModification() instanceof ProductModificationUid && true === is_null($AccessOrderProductDTO->getModification()))
                    {
                        continue;
                    }

                    $AccessOrderPriceDTO = $AccessOrderProductDTO->getPrice();


                    // Пропускаем, если продукция в заказе уже готова к сборке, но еще не отправлена на упаковку
                    if(true === $AccessOrderPriceDTO->isAccess())
                    {
                        continue;
                    }

                    /**
                     * Если заказ не укомплектован - увеличиваем ACCESS продукции на единицу для дальнейшей сборки
                     * isAccess вернет true, если количество в заказе равное количество произведенного
                     */
                    if(false === $AccessOrderPriceDTO->isAccess())
                    {
                        $isPackage = true;

                        $this->UpdateAccessOrderProduct
                            ->update($AccessOrderProductDTO->getId());

                        $AccessOrderPriceDTO->addAccess();

                        /** Присваиваем заказ продукции в производственной партии */
                        $ManufacturePartProductOrderDTO = new ManufacturePartProductOrderDTO()->setOrd($OrderEvent->getMain());
                        $ManufacturePartProductsDTO->addOrd($ManufacturePartProductOrderDTO);

                    }
                }

                $DeduplicatorOrder->save();
            }
        }

        /** Сохраняем производственную партию */

        if($isPackage)
        {
            $this->logger->critical(
                'Сохраняем производственную партию с указанными заказами к продукции',
                [$OrderEvent->getOrderNumber(), self::class.':'.__LINE__]
            );

            $ManufacturePart = $this->ManufacturePartHandler->handle($ManufacturePartDTO);

            if(false === ($ManufacturePart instanceof ManufacturePart))
            {
                $this->logger->critical(
                    sprintf('manufacture-part: Ошибка %s при обновлении производственной партии', $ManufacturePart),
                    [$message, self::class.':'.__LINE__]
                );
            }
        }

        $DeduplicatorExecuted->save();

        return true;
    }
}