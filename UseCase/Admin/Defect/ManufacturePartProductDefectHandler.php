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

namespace BaksDev\Manufacture\Part\UseCase\Admin\Defect;


use BaksDev\Core\Entity\AbstractHandler;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Entity\Products\ManufacturePartProduct;
use BaksDev\Manufacture\Part\Messenger\ManufacturePartMessage;
use BaksDev\Manufacture\Part\UseCase\Admin\Defect\Event\ManufacturePartProductsDTO;

final class ManufacturePartProductDefectHandler extends AbstractHandler
{
    //    public function __construct(
    //        #[Target('manufacturePartLogger')] private LoggerInterface $logger,
    //        private EntityManagerInterface $entityManager,
    //        private ValidatorInterface $validator,
    //        private MessageDispatchInterface $messageDispatch
    //    ) {}

    /** @see ManufacturePart */
    public function handle(ManufacturePartProductDefectDTO $command): string|ManufacturePart
    {


        /**
         * Продукция, которой допущен дефект
         * @var ManufacturePartProduct $ManufacturePartProduct
         */
        $ManufacturePartProduct = $this
            ->getRepository(ManufacturePartProduct::class)
            ->find($command->getId());


        /** @var ManufacturePart $ManufacturePart */
        $ManufacturePart = $this
            ->getRepository(ManufacturePart::class)
            ->find($ManufacturePartProduct->getEvent()->getMain());


        $this->clear();

        /**
         * Получаем активное событие партии
         * @var ManufacturePartEvent $ManufacturePartEvent
         */
        $ManufacturePartEvent = $this
            ->getRepository(ManufacturePartEvent::class)
            ->find($ManufacturePart->getEvent());

        $ManufacturePartDTO = new Event\ManufacturePartDTO();
        $ManufacturePartEvent->getDto($ManufacturePartDTO);


        /**
         * Если передан идентификатор события - значит брак производственный по вине пользователя
         */

        if($command->getEvent())
        {
            /**
             * @var ManufacturePartEvent $ManufacturePartEvent
             */
            $ManufacturePartEvent = $this
                ->getRepository(ManufacturePartEvent::class)
                ->find($command->getEvent());

            $DefectManufacturePartDTO = new Event\ManufacturePartDTO();
            $ManufacturePartEvent->getDto($DefectManufacturePartDTO);

            /** присваиваем Working */
            $ManufacturePartDTO
                ->getWorking()
                ->setProfile($DefectManufacturePartDTO->getWorking()->getProfile())
                ->setWorking($DefectManufacturePartDTO->getWorking()->getWorking());

        }

        /**
         * Если брак производственного сырья - сбрасываем идентификатор профиля и рабочее состояние -
         */

        else
        {
            $ManufacturePartDTO->resetWorking();
        }


        /**
         * Ищем продукт из партии, которому необходимо применить дефект
         */


        $FilterProduct = new ManufacturePartProductsDTO();
        $ManufacturePartProduct->getDto($FilterProduct);
        $ProductDefect = $ManufacturePartDTO->getCurrentProduct($FilterProduct);

        if(false === ($ProductDefect instanceof ManufacturePartProductsDTO))
        {
            return 'Продукт не найден';
        }


        /** Если количество дефектов меньше чем продукции */
        if($ProductDefect->getTotal() < $command->getTotal())
        {
            $uniqid = uniqid('', false);
            return sprintf('manufacture-part: Ошибка %s при дефектовке! Дефект превышает количество в партии', $uniqid);
        }


        /** Если после дефектовки количество 0 = удаляем продукцию из партии */
        $ProductDefect->setDefect($command->getTotal());

        if($ProductDefect->getTotal() === 0)
        {
            $ManufacturePartDTO->removeProduct($ProductDefect);
        }


        $this->clear();

        $this
            ->setCommand($ManufacturePartDTO)
            ->preEventPersistOrUpdate(ManufacturePart::class, ManufacturePartEvent::class);

        /* Валидация всех объектов */
        if($this->validatorCollection->isInvalid())
        {
            return $this->validatorCollection->getErrorUniqid();
        }

        $this->flush();

        /* Отправляем сообщение в шину */
        $this->messageDispatch
            ->addClearCacheOther('wildberries-manufacture')
            ->addClearCacheOther('wildberries-package')
            ->dispatch(
                message: new ManufacturePartMessage($this->main->getId(), $this->main->getEvent(), total: $command->getTotal()),
                transport: 'manufacture-part'
            );

        return $this->main;
    }
}