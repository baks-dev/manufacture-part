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

namespace BaksDev\Manufacture\Part\UseCase\Admin\Products\Edit;


use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Entity\Products\ManufacturePartProduct;
use BaksDev\Manufacture\Part\Messenger\ManufacturePartMessage;
use BaksDev\Manufacture\Part\Type\Event\ManufacturePartEventUid;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusOpen;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ManufacturePartProductEditHandler
{
    private EntityManagerInterface $entityManager;

    private ValidatorInterface $validator;

    private LoggerInterface $logger;
    private DBALQueryBuilder $DBALQueryBuilder;
    private MessageDispatchInterface $messageDispatch;


    public function __construct(
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        LoggerInterface $logger,
        DBALQueryBuilder $DBALQueryBuilder,
        MessageDispatchInterface $messageDispatch,
    )
    {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->DBALQueryBuilder = $DBALQueryBuilder;
        $this->messageDispatch = $messageDispatch;
    }

    /** @see ManufacturePart */
    public function handle(
        ManufacturePartProductEditDTO $command,
        ?UserProfileUid $profile = null
    ): string|ManufacturePartProduct
    {
        /**
         *  Валидация ManufacturePartProductDefectDTO
         */
        $errors = $this->validator->validate($command);

        if(count($errors) > 0)
        {
            /** Ошибка валидации */
            $uniqid = uniqid('', false);
            $this->logger->error(sprintf('%s: %s', $uniqid, $errors), [self::class.':'.__LINE__]);

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



        if($profile)
        {
            /** Проверяем владельца */

            $qb = $this->DBALQueryBuilder->createQueryBuilder(self::class);
            $qb->from(ManufacturePartEvent::TABLE, 'event');
            $qb->join('event', ManufacturePart::TABLE, 'part', 'part.id = event.main AND part.event = event.id');
            $qb->where('event.id = :event')
                ->setParameter('event', $Product->getEvent()->getId(), ManufacturePartEventUid::TYPE);
            $qb->andWhere('event.profile = :profile')
                ->setParameter('profile', $profile, UserProfileUid::TYPE);

            if(!$qb->fetchExist())
            {
                $uniqid = uniqid('', false);
                $errorsString = sprintf(
                    'Профилю пользователя %s не принадлежит продукт %s',
                    $profile,
                    $command->getId()
                );
                $this->logger->error($uniqid.': '.$errorsString);

                return $uniqid;
            }

            /** Нельзя обновить продукцию, которая уже в производстве (Только в открытой партии) */
            if(!$Product->getEvent()->getStatus()->equals(ManufacturePartStatusOpen::class))
            {
                $uniqid = uniqid('', false);
                $errorsString = sprintf(
                    'Невозможно обновить продукт из партии, которая уже в производстве %s',
                    $Product->getEvent()->getStatus()
                );
                $this->logger->error($uniqid.': '.$errorsString);

                return $uniqid;
            }
        }



        $Product->setEntity($command);

        /**
         * Валидация Product
         */
        $errors = $this->validator->validate($Product);

        if(count($errors) > 0)
        {
            /** Ошибка валидации */
            $uniqid = uniqid('', false);
            $this->logger->error(sprintf('%s: %s', $uniqid, $errors), [self::class.':'.__LINE__]);

            return $uniqid;
        }


        $this->entityManager->flush();

        /* Отправляем сообщение в шину */
        $ManufacturePartEvent = $Product->getEvent();

        $this->messageDispatch->dispatch(
            message: new ManufacturePartMessage($ManufacturePartEvent->getMain(), $ManufacturePartEvent->getId()),
            transport: 'manufacture-part'
        );


        return $Product;
    }
}