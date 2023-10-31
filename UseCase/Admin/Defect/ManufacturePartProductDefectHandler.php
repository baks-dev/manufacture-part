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

namespace BaksDev\Manufacture\Part\UseCase\Admin\Defect;


use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Entity\Products\ManufacturePartProduct;
use BaksDev\Manufacture\Part\Messenger\ManufacturePartMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ManufacturePartProductDefectHandler
{
    private EntityManagerInterface $entityManager;

    private ValidatorInterface $validator;

    private LoggerInterface $logger;

    private MessageDispatchInterface $messageDispatch;

    public function __construct(
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        LoggerInterface $logger,
        MessageDispatchInterface $messageDispatch
    )
    {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->messageDispatch = $messageDispatch;

    }

    /** @see ManufacturePart */
    public function handle(
        ManufacturePartProductDefectDTO $command,
    ): string|ManufacturePart
    {
        /**
         *  Валидация ManufacturePartProductDefectDTO
         */
        $errors = $this->validator->validate($command);

        if(count($errors) > 0)
        {
            /** Ошибка валидации */
            $uniqid = uniqid('', false);
            $this->logger->error(sprintf('%s: %s', $uniqid, $errors), [__LINE__ => __FILE__]);

            return $uniqid;
        }


        /** Продукция, которой допущен дефект */
        $Product = $this->entityManager->getRepository(ManufacturePartProduct::class)
            ->find($command->getId());

        if($Product === null)
        {
            $uniqid = uniqid('', false);
            $errorsString = sprintf(
                'Not found %s by id: %s',
                ManufacturePartProduct::class,
                $command->getId()
            );
            $this->logger->error($uniqid.': '.$errorsString);

            return $uniqid;
        }

        $Main = $this->entityManager->getRepository(ManufacturePart::class)
            ->find($Product->getEvent()->getMain());

        /** Получаем активное событие партии */
        $this->entityManager->clear();
        $EventRepo = $this->entityManager->getRepository(ManufacturePartEvent::class)
            ->find($Main->getEvent());


        if($EventRepo === null)
        {
            $uniqid = uniqid('', false);
            $errorsString = sprintf(
                'Not found %s by id: %s',
                ManufacturePartEvent::class,
                $Product->getEvent()->getId()
            );
            $this->logger->error($uniqid.': '.$errorsString);

            return $uniqid;
        }

        $ManufacturePartDTO = new Event\ManufacturePartDTO();
        $EventRepo->getDto($ManufacturePartDTO);

        /**
         * Если передан идентификатор события - значит брак производственный по вине пользователя
         */
        if($command->getEvent())
        {

            $this->entityManager->clear();
            $DefectProfile = $this->entityManager->getRepository(ManufacturePartEvent::class)
                ->find($command->getEvent());

            $DefectManufacturePartDTO = new Event\ManufacturePartDTO();
            $DefectProfile->getDto($DefectManufacturePartDTO);

            /** присваиваем Working */
            $ManufacturePartDTO->getWorking()
                ->setProfile($DefectManufacturePartDTO->getWorking()->getProfile())
                ->setWorking($DefectManufacturePartDTO->getWorking()->getWorking());

        }

        /** Если брак производственного сырья - сбрасываем идентификатор профиля и рабочее состояние -  */
        else
        {
            $ManufacturePartDTO->resetWorking();
        }


        /** Ищем продукт из партии, которому необходимо применить дефект */
        $FilterProduct = new Event\ManufacturePartProductsDTO();
        $Product->getDto($FilterProduct);
        $ProductDefect = $ManufacturePartDTO->getCurrentProduct($FilterProduct);

        /** Если количество дефектов меньше чем продукции */
        if($ProductDefect->getTotal() < $command->getTotal())
        {
            $uniqid = uniqid('', false);
            $errorsString = sprintf(
                'Product %s < Defect %s',
                $ProductDefect->getTotal(),
                $command->getTotal()
            );

            $this->logger->error($uniqid.': '.$errorsString);
            return $uniqid;
        }


        /** Если после дефектовки количество 0 = удаляем продукцию из партии */
        $ProductDefect->setDefect($command->getTotal());

        if($ProductDefect->getTotal() === 0)
        {
            $ManufacturePartDTO->removeProduct($ProductDefect);
        }

        $Event = $EventRepo->cloneEntity();
        $Event->setEntity($ManufacturePartDTO);


        $this->entityManager->clear();

        $Main = $this->entityManager->getRepository(ManufacturePart::class)->find($Event->getMain());

        if(empty($Main))
        {
            $uniqid = uniqid('', false);
            $errorsString = sprintf(
                'Not found %s by event: %s',
                ManufacturePart::class,
                $command->getEvent()
            );
            $this->logger->error($uniqid.': '.$errorsString);

            return $uniqid;
        }


        /**
         * Валидация Event
         */
        $errors = $this->validator->validate($Event);

        if(count($errors) > 0)
        {
            /** Ошибка валидации */
            $uniqid = uniqid('', false);
            $this->logger->error(sprintf('%s: %s', $uniqid, $errors), [__LINE__ => __FILE__]);

            return $uniqid;
        }


        /**
         * Валидация Main
         */
        $errors = $this->validator->validate($Main);

        if(count($errors) > 0)
        {
            /** Ошибка валидации */
            $uniqid = uniqid('', false);
            $this->logger->error(sprintf('%s: %s', $uniqid, $errors), [__LINE__ => __FILE__]);

            return $uniqid;
        }

        $this->entityManager->persist($Event);

        /* присваиваем событие корню */
        $Main->setEvent($Event);

        $this->entityManager->flush();

        /* Отправляем сообщение в шину */
        $this->messageDispatch->dispatch(
            message: new ManufacturePartMessage($Main->getId(), $Main->getEvent(), total: $command->getTotal()),
            transport: 'manufacture-part'
        );

        // 'manufacture-part_high'
        return $Main;
    }
}