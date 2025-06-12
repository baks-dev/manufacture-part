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
use BaksDev\Core\Type\Gps\GpsLatitude;
use BaksDev\Core\Type\Gps\GpsLongitude;
use BaksDev\Delivery\Repository\CurrentDeliveryEvent\CurrentDeliveryEventInterface;
use BaksDev\Delivery\Type\Event\DeliveryEventUid;
use BaksDev\Delivery\Type\Id\Choice\Collection\TypeDeliveryInterface;
use BaksDev\Delivery\Type\Id\DeliveryUid;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Messenger\ManufacturePartMessage;
use BaksDev\Manufacture\Part\Repository\ManufacturePartCurrentEvent\ManufacturePartCurrentEventInterface;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusCompleted;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\ManufacturePartDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\Products\ManufacturePartProductsDTO;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\UseCase\Admin\New\NewOrderDTO;
use BaksDev\Orders\Order\UseCase\Admin\New\NewOrderHandler;
use BaksDev\Orders\Order\UseCase\Admin\New\Products\NewOrderProductDTO;
use BaksDev\Orders\Order\UseCase\Admin\New\Products\Price\NewOrderPriceDTO;
use BaksDev\Orders\Order\UseCase\Admin\New\User\OrderUserDTO;
use BaksDev\Payment\Type\Id\Choice\Collection\TypePaymentInterface;
use BaksDev\Payment\Type\Id\PaymentUid;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierInterface;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierResult;
use BaksDev\Reference\Currency\Type\Currencies\RUR;
use BaksDev\Reference\Currency\Type\Currency;
use BaksDev\Reference\Money\Type\Money;
use BaksDev\Users\Profile\TypeProfile\Type\Id\Choice\Collection\TypeProfileInterface;
use BaksDev\Users\Profile\TypeProfile\Type\Id\TypeProfileUid;
use BaksDev\Users\Profile\UserProfile\Entity\Event\UserProfileEvent;
use BaksDev\Users\Profile\UserProfile\Repository\CurrentUserProfileEventById\CurrentUserProfileEventByIdInterfaceInterface;
use BaksDev\Wildberries\Orders\Type\DeliveryType\TypeDeliveryFboWildberries;
use BaksDev\Wildberries\Orders\Type\PaymentType\TypePaymentFboWildberries;
use BaksDev\Wildberries\Orders\Type\ProfileType\TypeProfileFboWildberries;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Создает заказ со статусом NEW на производство FBO при завершающем этапе
 */
#[AsMessageHandler(priority: 100)]
final readonly class NewOrderFboByPartCompletedDispatcher
{
    public function __construct(
        #[Target('manufacturePartLogger')] private LoggerInterface $logger,
        private ManufacturePartCurrentEventInterface $ManufacturePartCurrentEvent,
        private CurrentUserProfileEventByIdInterfaceInterface $CurrentUserProfileEvent,
        private CurrentProductIdentifierInterface $CurrentProductIdentifier,
        private NewOrderHandler $NewOrderHandler,
        private DeduplicatorInterface $deduplicator,
        private CurrentDeliveryEventInterface $CurrentDeliveryEvent,
    ) {}

    public function __invoke(ManufacturePartMessage $message): void
    {
        $DeduplicatorExecuted = $this
            ->deduplicator
            ->namespace('manufacture-part')
            ->deduplication([(string) $message->getId(), self::class]);

        if($DeduplicatorExecuted->isExecuted())
        {
            return;
        }

        $ManufacturePartEvent = $this->ManufacturePartCurrentEvent
            ->fromPart($message->getId())
            ->find();

        if(false === ($ManufacturePartEvent instanceof ManufacturePartEvent))
        {
            $this->logger->critical(
                'manufacture-part: ManufacturePartEvent не определено',
                [var_export($message, true), self::class.':'.__LINE__]
            );

            return;
        }

        if(false === $ManufacturePartEvent->equalsManufacturePartStatus(ManufacturePartStatusCompleted::class))
        {
            return;
        }

        /**
         * Определяем тип доставки
         * доступно только для заказов типа FBO
         */

        /** @var TypeDeliveryInterface $orderType */
        $orderType = match (true)
        {
            /* FBO Wb*/
            $ManufacturePartEvent->equalsManufacturePartComplete(TypeDeliveryFboWildberries::class) => TypeDeliveryFboWildberries::TYPE,
            default => false,
        };


        /**
         * Определяем способ оплаты заказа
         * доступно только для заказов типа FBO
         */

        /** @var TypePaymentInterface $paymentType */
        $paymentType = match (true)
        {
            /* FBO Wb*/
            $ManufacturePartEvent->equalsManufacturePartComplete(TypeDeliveryFboWildberries::class) => TypePaymentFboWildberries::TYPE,
            default => false,
        };

        /**
         * Определяем тип профиля заказа
         * доступно только для заказов типа FBO
         */

        /** @var TypeProfileInterface $profileType */
        $profileType = match (true)
        {
            /* FBO Wb*/
            $ManufacturePartEvent->equalsManufacturePartComplete(TypeDeliveryFboWildberries::class) => TypeProfileFboWildberries::TYPE,
            default => false,
        };


        // Если у заказа не определен тип
        if(false === ($profileType || $orderType || $paymentType))
        {
            return;
        }

        /** Способ доставки FBO */
        $Delivery = new DeliveryUid($orderType);
        $DeliveryEventUid = $this->CurrentDeliveryEvent
            ->forDelivery($Delivery)
            ->getId();

        if(false === ($DeliveryEventUid instanceof DeliveryEventUid))
        {
            $this->logger->critical(
                'manufacture-part: DeliveryEventUid не определено',
                [
                    'message' => $message,
                    'delivery' => $orderType,
                    self::class.':'.__LINE__
                ]
            );

            return;
        }

        $ManufacturePartDTO = new ManufacturePartDTO();
        $ManufacturePartEvent->getDto($ManufacturePartDTO);
        $ManufacturePartInvariableDTO = $ManufacturePartDTO->getInvariable();

        /**
         * Создаем заказ с продукцией на указанное количество
         */

        $OrderDTO = new NewOrderDTO();
        $OrderDTO->setProfile($ManufacturePartInvariableDTO->getProfile());

        $NewOrderInvariableDTO = $OrderDTO->getInvariable();
        $NewOrderInvariableDTO->setUsr($ManufacturePartInvariableDTO->getUsr());
        $NewOrderInvariableDTO->setNumber($ManufacturePartInvariableDTO->getNumber());

        /**
         * OrderUserDTO
         */

        $OrderUserDTO = new OrderUserDTO();
        $OrderDTO->setUsr($OrderUserDTO);

        $OrderDeliveryDTO = $OrderDTO->getUsr()->getDelivery();
        $OrderPaymentDTO = $OrderDTO->getUsr()->getPayment();
        $OrderProfileDTO = $OrderDTO->getUsr()->getUserProfile();


        $UserProfileEvent = $this->CurrentUserProfileEvent
            ->forProfile($ManufacturePartDTO->getFixed())
            ->find();

        if(true === ($UserProfileEvent instanceof UserProfileEvent))
        {
            $OrderUserDTO->setUsr($UserProfileEvent->getUser());
            $OrderUserDTO->setProfile($UserProfileEvent->getId());
        }

        /** Тип профиля FBO */
        $Profile = new TypeProfileUid($profileType);
        $OrderProfileDTO?->setType($Profile);

        /** Тип доставки FBO */
        $OrderDeliveryDTO->setDelivery($Delivery);
        $OrderDeliveryDTO->setEvent($DeliveryEventUid);
        $OrderDeliveryDTO->setLatitude(new GpsLatitude(55.755892));
        $OrderDeliveryDTO->setLongitude(new GpsLongitude(37.617188));

        /** Способ оплаты FBO */
        $Payment = new PaymentUid($paymentType);
        $OrderPaymentDTO->setPayment($Payment);


        /** @var ManufacturePartProductsDTO $ManufacturePartProductsDTO */
        foreach($ManufacturePartDTO->getProduct() as $ManufacturePartProductsDTO)
        {
            /** @var CurrentProductIdentifierResult $CurrentProductIdentifierResult */
            $CurrentProductIdentifierResult = $this->CurrentProductIdentifier
                ->forEvent($ManufacturePartProductsDTO->getProduct())
                ->forOffer($ManufacturePartProductsDTO->getOffer())
                ->forVariation($ManufacturePartProductsDTO->getVariation())
                ->forModification($ManufacturePartProductsDTO->getModification())
                ->find();

            if(false === ($CurrentProductIdentifierResult instanceof CurrentProductIdentifierResult))
            {
                continue;
            }

            $OrderProductDTO = new NewOrderProductDTO();
            $OrderDTO->addProduct($OrderProductDTO);

            $OrderProductDTO
                ->setProduct($CurrentProductIdentifierResult->getEvent())
                ->setOffer($CurrentProductIdentifierResult->getOffer())
                ->setVariation($CurrentProductIdentifierResult->getVariation())
                ->setModification($CurrentProductIdentifierResult->getModification());

            /** OrderPriceDTO */

            $OrderPriceDTO = new NewOrderPriceDTO();
            $OrderProductDTO->setPrice($OrderPriceDTO);

            $OrderPriceDTO->setTotal($ManufacturePartProductsDTO->getTotal());

            $price = new Money($ManufacturePartProductsDTO->getTotal());
            $OrderPriceDTO->setPrice($price);

            $currency = new Currency(RUR::class);
            $OrderPriceDTO->setCurrency($currency);

        }

        $Order = $this->NewOrderHandler->handle($OrderDTO);

        if(false === ($Order instanceof Order))
        {
            $this->logger->critical(
                sprintf('wildberries-package: Ошибка %s при добавлении заказа DBS при выполненном производстве', $Order),
                [$message, self::class.':'.__LINE__]
            );

            return;
        }

        $DeduplicatorExecuted->save();
    }
}