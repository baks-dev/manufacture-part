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


use BaksDev\Delivery\Type\Id\DeliveryUid;
use BaksDev\Manufacture\Part\Messenger\ManufacturePartMessage;
use BaksDev\Manufacture\Part\Repository\ManufacturePartCurrentEvent\ManufacturePartCurrentEventInterface;
use BaksDev\Manufacture\Part\Repository\ProductsByManufacturePart\ProductsByManufacturePartInterface;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusCompleted;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\ManufacturePartDTO;
use BaksDev\Manufacture\Part\UseCase\ProductStocks\ManufactureProductStockDTO;
use BaksDev\Manufacture\Part\UseCase\ProductStocks\ManufactureProductStockHandler;
use BaksDev\Manufacture\Part\UseCase\ProductStocks\Products\ProductStockDTO;
use BaksDev\Orders\Order\Repository\RelevantNewOrderByProduct\RelevantNewOrderByProductInterface;
use BaksDev\Orders\Order\Repository\UpdateAccessOrderProduct\UpdateAccessOrderProductInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\OrderStatusPackage;
use BaksDev\Orders\Order\UseCase\Admin\Access\AccessOrderDTO;
use BaksDev\Orders\Order\UseCase\Admin\Access\Products\AccessOrderProductDTO;
use BaksDev\Orders\Order\UseCase\Admin\Status\OrderStatusDTO;
use BaksDev\Orders\Order\UseCase\Admin\Status\OrderStatusHandler;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierInterface;
use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\UseCase\Admin\Package\Orders\ProductStockOrderDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Package\PackageProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Package\PackageProductStockHandler;
use BaksDev\Users\Profile\UserProfile\Repository\UserByUserProfile\UserByUserProfileInterface;
use BaksDev\Wildberries\Manufacture\Type\ManufacturePartComplete\ManufacturePartCompleteWildberriesFbs;
use BaksDev\Wildberries\Orders\Type\DeliveryType\TypeDeliveryFbsWildberries;
use Doctrine\Common\Collections\ArrayCollection;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final readonly class PackageOrdersByPartCompleted
{
    public function __construct(
        #[Target('manufacturePartLogger')] private LoggerInterface $logger,
        private ProductsByManufacturePartInterface $ProductsByManufacturePart,
        private ManufacturePartCurrentEventInterface $ManufacturePartCurrentEvent,
        private RelevantNewOrderByProductInterface $RelevantNewOrderByProduct,
        private UpdateAccessOrderProductInterface $UpdateAccessOrderProduct,
        private OrderStatusHandler $OrderStatusHandler,
        private ManufactureProductStockHandler $ManufactureProductStockHandler,
        private UserByUserProfileInterface $UserByUserProfile,
        private CurrentProductIdentifierInterface $CurrentProductIdentifier,
        private PackageProductStockHandler $PackageProductStockHandler,

    ) {}

    /**
     * Обновляем заказы при завершении производственной партии
     */
    public function __invoke(ManufacturePartMessage $message): void
    {
        $ManufacturePartEvent = $this->ManufacturePartCurrentEvent
            ->fromPart($message->getId())
            ->find();

        if(!$ManufacturePartEvent)
        {
            return;
        }

        if(false === $ManufacturePartEvent->equalsManufacturePartStatus(ManufacturePartStatusCompleted::class))
        {
            return;
        }


        /** Получаем всю продукцию в производственной партии */

        $ProductsManufacture = $this->ProductsByManufacturePart
            ->forPart($message->getId())
            ->findAll();


        $ManufacturePartDTO = new ManufacturePartDTO();
        $ManufacturePartEvent->getDto($ManufacturePartDTO);


        /**
         * Определяем тип производства для заказов
         */


        // TODO: Переделать завершающие этапы на типы доставки
        $orderType = match (true)
        {
            $ManufacturePartEvent->equalsManufacturePartComplete(ManufacturePartCompleteWildberriesFbs::class) => TypeDeliveryFbsWildberries::TYPE,
            default => false,
        };

        if(false === $orderType)
        {
            return;
        }

        $DeliveryUid = new DeliveryUid($orderType);

        foreach($ProductsManufacture as $product)
        {
            $total = $product['product_total'];

            /**
             * Отправляем продукцию на склад
             */

            $ProductUid = new ProductUid($product['product_id']);
            $ProductOfferConst = $product['product_offer_const'] ? new ProductOfferConst($product['product_offer_const']) : null;
            $ProductVariationConst = $product['product_variation_const'] ? new ProductVariationConst($product['product_variation_const']) : null;
            $ProductModificationConst = $product['product_modification_const'] ? new ProductModificationConst($product['product_modification_const']) : null;

            $ProductStockDTO = new ProductStockDTO()
                ->setProduct($ProductUid)
                ->setOffer($ProductOfferConst)
                ->setVariation($ProductVariationConst)
                ->setModification($ProductModificationConst)
                ->setTotal($total);

            $ManufactureProductStockDTO = new ManufactureProductStockDTO()
                ->setProfile($ManufacturePartDTO->getProfile())
                ->addProduct($ProductStockDTO);

            $ProductStock = $this->ManufactureProductStockHandler->handle($ManufactureProductStockDTO);


            if(false === ($ProductStock instanceof ProductStock))
            {
                $this->logger->critical(
                    'manufacture-part: Ошибка при обновлении складских остатков',
                    [$product, self::class.':'.__LINE__]
                );
            }

            $ProductEventUid = new ProductEventUid($product['product_event']);
            $ProductOfferUid = $product['product_offer_id'] ? new ProductOfferUid($product['product_offer_id']) : false;
            $ProductVariationUid = $product['product_variation_id'] ? new ProductVariationUid($product['product_variation_id']) : false;
            $ProductModificationUid = $product['product_modification_id'] ? new ProductModificationUid($product['product_modification_id']) : false;


            /**
             * Перебираем все количество продукции в производственной партии
             */

            for($i = 1; $i <= $total; $i++)
            {
                /** Получаем заказ со статусом НОВЫЙ на данную продукцию */

                $OrderEvent = $this->RelevantNewOrderByProduct
                    ->forDelivery($DeliveryUid)
                    ->forProductEvent($ProductEventUid)
                    ->forOffer($ProductOfferUid)
                    ->forVariation($ProductVariationUid)
                    ->forModification($ProductModificationUid)
                    ->onlyNewStatus()
                    ->find();

                /**
                 * Приступаем к следующему продукту в случае отсутствия заказов
                 */

                if(false === $OrderEvent)
                {
                    continue 2;
                }


                $AccessOrderDTO = new AccessOrderDTO();
                $OrderEvent->getDto($AccessOrderDTO);


                /** @var AccessOrderProductDTO $AccessOrderProductDTO */
                foreach($AccessOrderDTO->getProduct() as $AccessOrderProductDTO)
                {
                    /**
                     * Проверяем, что продукт в заказе соответствует идентификаторам производства
                     */

                    if(false === $AccessOrderProductDTO->getProduct()->equals($product['product_event']))
                    {
                        continue;
                    }

                    if($AccessOrderProductDTO->getOffer() instanceof ProductOfferUid && false === $AccessOrderProductDTO->getOffer()->equals($ProductOfferUid))
                    {
                        continue;
                    }


                    if($ProductOfferUid !== false && true === is_null($AccessOrderProductDTO->getOffer()))
                    {
                        continue;
                    }


                    if($AccessOrderProductDTO->getVariation() instanceof ProductVariationUid && false === $AccessOrderProductDTO->getVariation()->equals($ProductVariationUid))
                    {
                        continue;
                    }

                    if(false !== $ProductVariationUid && true === is_null($AccessOrderProductDTO->getVariation()))
                    {
                        continue;
                    }


                    if($AccessOrderProductDTO->getModification() instanceof ProductModificationUid && false === $AccessOrderProductDTO->getModification()->equals($ProductModificationUid))
                    {
                        continue;
                    }


                    if(false !== $ProductModificationUid && true === is_null($AccessOrderProductDTO->getModification()))
                    {
                        continue;
                    }

                    $AccessOrderPriceDTO = $AccessOrderProductDTO->getPrice();

                    // Пропускаем, если продукция в заказе уже готова к сборке, но еще не отправлена на упаковку
                    if(true === $AccessOrderPriceDTO->isAccess())
                    {
                        continue;
                    }

                    $AccessOrderPriceDTO->addAccess();

                    /**
                     * Если заказ не укомплектован - увеличиваем ACCESS продукции на единицу для дальнейшей сборки
                     */
                    if(false === $AccessOrderPriceDTO->isAccess())
                    {
                        // Увеличиваем ACCESS продукции на единицу
                        $is = $this->UpdateAccessOrderProduct->update($AccessOrderProductDTO->getId());

                        if($is !== 1)
                        {
                            $this->logger->critical(
                                'manufacture-part: Ошибка при обновлении Access продукции в заказе заказа',
                                [$AccessOrderProductDTO->getId(), self::class.':'.__LINE__]
                            );
                        }
                    }
                }


                /**
                 * Проверяем что вся продукция в заказе готова к сборке
                 */

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

                    $User = $this->UserByUserProfile
                        ->forProfile($ManufacturePartEvent->getProfile())
                        ->find();

                    if(false === $User)
                    {
                        $this->logger->critical(
                            'manufacture-part: Пользователя идентификатора профиля не найдено',
                            [$ManufacturePartEvent->getProfile(), self::class.':'.__LINE__]
                        );

                        return;
                    }

                    /**
                     * Создаем заявку на упаковку
                     */

                    $PackageProductStockDTO = new PackageProductStockDTO($User);
                    $OrderEvent->getDto($PackageProductStockDTO);
                    $PackageProductStockDTO->setProduct(new ArrayCollection());

                    $ProductStockOrderDTO = new ProductStockOrderDTO();
                    $ProductStockOrderDTO->setOrd($OrderEvent->getMain());
                    $PackageProductStockDTO->setOrd($ProductStockOrderDTO);
                    $PackageProductStockDTO->setNumber($OrderEvent->getOrderNumber());


                    /** @var AccessOrderProductDTO $AccessOrderProductDTO */
                    foreach($AccessOrderDTO->getProduct() as $AccessOrderProductDTO)
                    {
                        $CurrentProductIdentifier = $this->CurrentProductIdentifier
                            ->forEvent($AccessOrderProductDTO->getProduct())
                            ->forOffer($AccessOrderProductDTO->getOffer())
                            ->forVariation($AccessOrderProductDTO->getVariation())
                            ->forModification($AccessOrderProductDTO->getModification())
                            ->find();

                        $ProductStockPackageDTO = new \BaksDev\Products\Stocks\UseCase\Admin\Package\Products\ProductStockDTO();

                        $ProductStockPackageDTO
                            ->setProduct($CurrentProductIdentifier->getProduct())
                            ->setOffer($CurrentProductIdentifier->getOfferConst())
                            ->setVariation($CurrentProductIdentifier->getVariationConst())
                            ->setModification($CurrentProductIdentifier->getModificationConst())
                            ->setTotal($AccessOrderProductDTO->getPrice()->getTotal());

                        $PackageProductStockDTO->addProduct($ProductStockPackageDTO);

                    }

                    $ProductStock = $this->PackageProductStockHandler->handle($PackageProductStockDTO);

                    if(!$ProductStock instanceof ProductStock)
                    {
                        $this->logger->critical(
                            'manufacture-part: Ошибка при добавлении складской заявки заказа со статусом «Упаковка»',
                            [$ProductStock, self::class.':'.__LINE__]
                        );
                    }


                    /**
                     * Обновляем статус заказа и присваиваем профиль склада упаковки.
                     */

                    $OrderStatusDTO = new OrderStatusDTO(
                        OrderStatusPackage::class,
                        $OrderEvent->getId(),
                        $ManufacturePartEvent->getProfile()
                    );

                    /** @var OrderStatusHandler $statusHandler */
                    $OrderStatusHandler = $this->OrderStatusHandler->handle($OrderStatusDTO);

                    if(!$OrderStatusHandler instanceof Order)
                    {
                        $this->logger->critical(
                            'manufacture-part: Ошибка при обновлении заказа со статусом «Упаковка»',
                            [$OrderStatusHandler, self::class.':'.__LINE__]
                        );
                    }
                }
            }

        }
    }
}