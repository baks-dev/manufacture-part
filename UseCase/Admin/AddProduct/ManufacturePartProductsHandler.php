<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Manufacture\Part\UseCase\Admin\AddProduct;


use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Entity\Products\ManufacturePartProduct;
use BaksDev\Manufacture\Part\Messenger\ManufacturePartMessage;
use BaksDev\Manufacture\Part\Repository\OpenManufacturePartByAction\OpenManufacturePartByActionInterface;
use BaksDev\Products\Product\Entity\Event\ProductEvent;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\UsersTable\Entity\Actions\Event\UsersTableActionsEvent;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ManufacturePartProductsHandler
{
    private EntityManagerInterface $entityManager;

    private ValidatorInterface $validator;

    private LoggerInterface $logger;

    private MessageDispatchInterface $messageDispatch;

    private OpenManufacturePartByActionInterface $openManufacturePartByAction;

//    private ActionByProductInterface $actionByProduct;
//    private ActionByCategoryInterface $actionByCategory;

    //private ManufacturePartHandler $ManufacturePartHandler;

    public function __construct(
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        LoggerInterface $logger,
        MessageDispatchInterface $messageDispatch,
        OpenManufacturePartByActionInterface $openManufacturePartByAction,

//        ActionByProductInterface $actionByProduct,
//        ActionByCategoryInterface $actionByCategory,
//        ManufacturePartHandler $ManufacturePartHandler,
    )
    {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->messageDispatch = $messageDispatch;
        $this->openManufacturePartByAction = $openManufacturePartByAction;

//        $this->actionByProduct = $actionByProduct;
//        $this->actionByCategory = $actionByCategory;
//        $this->ManufacturePartHandler = $ManufacturePartHandler;
    }

    /** @see ManufacturePart */
    public function handle(
        ManufacturePartProductsDTO $command,
        UserProfileUid $current,
    ): string|ManufacturePart|ManufacturePartProduct
    {

        /**
         *  Валидация ManufacturePartProductsDTO
         */
        $errors = $this->validator->validate($command);

        if(count($errors) > 0)
        {
            /** Ошибка валидации */
            $uniqid = uniqid('', false);
            $this->logger->error(sprintf('%s: %s', $uniqid, $errors), [__FILE__.':'.__LINE__]);

            return $uniqid;
        }

        /** Получаем активную открытую производственную партию ответственного лица */
        $ManufacturePartEvent = $this->openManufacturePartByAction
            ->findManufacturePartEventOrNull($current);
        
        if(!$ManufacturePartEvent)
        {
            $uniqid = uniqid('', false);
            $errorsString = sprintf(
                'У профиля %s нет открытой производственной партии',
                $current,
            );
            $this->logger->error($uniqid.': '.$errorsString);

            return $uniqid;
        }



        /** Получаем категорию продукции */
        $ProductEvent = $this->entityManager->getRepository(ProductEvent::class)->find(
            $command->getProduct()
        );

        if(!$ProductEvent->getRootCategory())
        {
            $uniqid = uniqid('', false);
            $errorsString = sprintf(
                'Not found root category %s by product id: %s',
                ProductEvent::class,
                $command->getProduct()
            );
            $this->logger->error($uniqid.': '.$errorsString);

            return $uniqid;
        }


        /** Проверяем, что категория продукции соответствует категории партии */
        $UsersTableActionsEvent = $this->entityManager->getRepository(UsersTableActionsEvent::class)->findOneBy(
            [
                'id' => $ManufacturePartEvent->getAction(),
                'category' => $ProductEvent->getRootCategory()
            ]
        );

        if(!$UsersTableActionsEvent)
        {
            $uniqid = uniqid('', false);
            $errorsString = sprintf(
                'Продукция не соответствует категории производственной партии'
            );
            $this->logger->error($uniqid.': '.$errorsString);

            return $uniqid;
        }



        //        /** Получаем Индивидуальный способ производства продукции */
//
//        $UsersTableActionsEvent = $this->actionByProduct
//            ->findUsersTableActionsByProduct($ProductEvent->getProduct());
//
//        /* Если нет индивидуального способ производства - ищем по категории */
//        if(!$UsersTableActionsEvent)
//        {
//            $UsersTableActionsEvent = $this->actionByCategory
//                ->findUsersTableActionsByCategory($ProductEvent->getRootCategory());
//        }
//
//        if(!$UsersTableActionsEvent)
//        {
//            $uniqid = uniqid('', false);
//            $errorsString = sprintf(
//                'Not found action user table %s (product: %s, root category: %s)',
//                ProductEvent::class,
//                $ProductEvent->getProduct(),
//                $ProductEvent->getRootCategory()
//            );
//            $this->logger->error($uniqid.': '.$errorsString);
//
//            return $uniqid;
//        }
//
//
//        /** Получаем активную открытую производственную партию */
//        $ManufacturePartEvent = $this->openManufacturePartByAction
//            ->findManufacturePartEventOrNull(
//                $command->getProfile(),
//                $UsersTableActionsEvent,
//                //$marketplace
//            );

//        /** Если открытой партии нет - открываем */
//        if(!$ManufacturePartEvent)
//        {
//
//            $ManufacturePartDTO = new ManufacturePartDTO();
//            $ManufacturePartDTO
//                ->setProfile($command->getProfile())
//                //->setMarketplace($marketplace)
//                ->setAction($UsersTableActionsEvent)
//            ;
//
//            /** Открываем партию на указанную категорию и маркетплейс */
//            $ManufacturePart = $this->ManufacturePartHandler->handle($ManufacturePartDTO);
//
//            if(!$ManufacturePart instanceof ManufacturePart)
//            {
//                return $ManufacturePart;
//            }
//
//            /** Получаем активную открытую производственную партию */
//            $ManufacturePartEvent = $this->openManufacturePartByAction
//                ->findManufacturePartEventOrNull(
//                    $command->getProfile(),
//                    $UsersTableActionsEvent,
//                    //$marketplace
//                );
//        }

        /**
         * Добавляем к открытой партии продукт
         */

        $ManufacturePartProduct = new ManufacturePartProduct($ManufacturePartEvent);
        $ManufacturePartProduct->setEntity($command);

        /**
         * Валидация Event
         */
        $errors = $this->validator->validate($ManufacturePartProduct);

        if(count($errors) > 0)
        {
            /** Ошибка валидации */
            $uniqid = uniqid('', false);
            $this->logger->error(sprintf('%s: %s', $uniqid, $errors), [__FILE__.':'.__LINE__]);

            return $uniqid;
        }


        $this->entityManager->persist($ManufacturePartProduct);
        $this->entityManager->flush();

        /* Отправляем сообщение в шину */
        $this->messageDispatch->dispatch(
            message: new ManufacturePartMessage($ManufacturePartEvent->getMain(), $ManufacturePartEvent->getId()),
            transport: 'manufacture-part'
        );


        // 'manufacture_part_high'
        return $ManufacturePartProduct;
    }
}