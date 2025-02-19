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
use BaksDev\Orders\Order\Repository\RelevantNewOrderByProduct\RelevantNewOrderByProductInterface;
use BaksDev\Orders\Order\Repository\UpdateAccessOrderProduct\UpdateAccessOrderProductInterface;
use BaksDev\Orders\Order\UseCase\Admin\Access\AccessOrderDTO;
use BaksDev\Orders\Order\UseCase\Admin\Access\Products\AccessOrderProductDTO;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use BaksDev\Wildberries\Manufacture\Type\ManufacturePartComplete\ManufacturePartCompleteWildberriesFbs;
use BaksDev\Wildberries\Orders\Type\DeliveryType\TypeDeliveryFbsWildberries;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Обновляет произведенную продукцию в заказах со статусом «NEW» как готовую к упаковке (+ access)
 */
#[AsMessageHandler(priority: 60)]
final readonly class AccessOrderProductByPartCompletedDispatcher
{
    public function __construct(
        #[Target('manufacturePartLogger')] private LoggerInterface $logger,
        private ManufacturePartCurrentEventInterface $ManufacturePartCurrentEvent,
        private RelevantNewOrderByProductInterface $RelevantNewOrderByProduct,
        private UpdateAccessOrderProductInterface $UpdateAccessOrderProduct,
        private DeduplicatorInterface $deduplicator,
    )
    {
        $this->deduplicator->namespace('wildberries-package');
    }


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

        $DeduplicatorExecuted->save();


        $ManufacturePartDTO = new ManufacturePartDTO();
        $ManufacturePartEvent->getDto($ManufacturePartDTO);

        $DeliveryUid = new DeliveryUid($orderType);

        /** @var ManufacturePartProductsDTO $ManufacturePartProductsDTO */

        foreach($ManufacturePartDTO->getProduct() as $ManufacturePartProductsDTO)
        {
            /**
             * Перебираем все количество продукции в производственной партии
             */

            $total = $ManufacturePartProductsDTO->getTotal();

            for($i = 1; $i <= $total; $i++)
            {
                /** Получаем заказ со статусом НОВЫЙ на данную продукцию требующие производства */

                $OrderEvent = $this->RelevantNewOrderByProduct
                    ->forDelivery($DeliveryUid)
                    ->forProductEvent($ManufacturePartProductsDTO->getProduct())
                    ->forOffer($ManufacturePartProductsDTO->getOffer())
                    ->forVariation($ManufacturePartProductsDTO->getVariation())
                    ->forModification($ManufacturePartProductsDTO->getModification())
                    ->onlyNewStatus() // только в статусе НОВЫЕ
                    ->filterProductAccess() // только требующие производства (access != total)
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
                    'Добавляем произведенную продукцию к заказу',
                    [$OrderEvent->getOrderNumber(), self::class.':'.__LINE__]
                );

                $AccessOrderDTO = new AccessOrderDTO();
                $OrderEvent->getDto($AccessOrderDTO);

                /** @var AccessOrderProductDTO $AccessOrderProductDTO */
                foreach($AccessOrderDTO->getProduct() as $AccessOrderProductDTO)
                {
                    /**
                     * Проверяем, что продукт в заказе соответствует идентификаторам производства
                     */
                    if(false === $AccessOrderProductDTO->getProduct()->equals($ManufacturePartProductsDTO->getProduct()))
                    {
                        continue;
                    }

                    /**
                     * Проверяем соответствие идентификаторов торгового предложения
                     */

                    if(
                        (
                            // если имеется OFFER идентификатор произведенной продукции, но в заказе OFFER = NULL
                            $ManufacturePartProductsDTO->getOffer() instanceof ProductOfferUid &&
                            true === is_null($AccessOrderProductDTO->getOffer())
                        )
                        ||
                        (
                            // если идентификатор OFFER продукции заказа не равен идентификатору OFFER произведенного
                            $AccessOrderProductDTO->getOffer() instanceof ProductOfferUid &&
                            false === $AccessOrderProductDTO->getOffer()->equals($ManufacturePartProductsDTO->getOffer())
                        )
                    )
                    {
                        continue;
                    }

                    /**
                     * Проверяем соответствие идентификаторов множественного варианта торгового предложения
                     */

                    if(
                        (
                            // если имеется VARIATION идентификатор произведенной продукции, но в заказе VARIATION = NULL
                            $ManufacturePartProductsDTO->getVariation() instanceof ProductVariationUid &&
                            true === is_null($AccessOrderProductDTO->getVariation())
                        )
                        ||
                        (
                            // если идентификатор VARIATION продукции заказа не равен идентификатору VARIATION произведенного
                            $AccessOrderProductDTO->getVariation() instanceof ProductVariationUid &&
                            false === $AccessOrderProductDTO->getVariation()->equals($ManufacturePartProductsDTO->getVariation())
                        )
                    )
                    {
                        continue;
                    }


                    /**
                     * Поверяем соответствие идентификаторов модификации множественного варианта
                     */

                    if(
                        (
                            // если имеется MODIFICATION идентификатор произведенной продукции, но в заказе MODIFICATION = NULL
                            $ManufacturePartProductsDTO->getModification() instanceof ProductModificationUid &&
                            true === is_null($AccessOrderProductDTO->getModification())
                        )
                        ||
                        (
                            // если идентификатор MODIFICATION продукции заказа не равен идентификатору MODIFICATION произведенного
                            $AccessOrderProductDTO->getModification() instanceof ProductModificationUid &&
                            false === $AccessOrderProductDTO->getModification()->equals($ManufacturePartProductsDTO->getModification())
                        )
                    )
                    {
                        continue;
                    }

                    $AccessOrderPriceDTO = $AccessOrderProductDTO->getPrice();

                    // Пропускаем, если продукция в заказе уже ГОТОВА к сборке, но еще не отправлена на упаковку
                    if(true === $AccessOrderPriceDTO->isAccess())
                    {
                        continue;
                    }

                    /**
                     * Если заказ не укомплектован - увеличиваем ACCESS продукции на единицу для дальнейшей сборки
                     * isAccess вернет true, если количество в заказе равное количество произведенного
                     */

                    $counter = $this->UpdateAccessOrderProduct
                        ->update($AccessOrderProductDTO->getId());

                    if($counter === 0)
                    {
                        $this->logger->critical(
                            'Ошибка при обновлении готовой продукции в заказе',
                            [$OrderEvent->getOrderNumber(), self::class.':'.__LINE__]
                        );

                        continue;
                    }

                    $AccessOrderPriceDTO->addAccess();
                }

                $DeduplicatorOrder->save();
            }
        }

        /**
         * Приступаем к обновлению продукцию в производственной партии идентификаторами заказов, готовых к сборке
         * @see ManufacturePartProductOrderByPartCompletedDispatch
         */


        return true;
    }
}