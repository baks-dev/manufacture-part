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

namespace BaksDev\Manufacture\Part\Messenger\BarcodeScanner;

use BaksDev\Auth\Telegram\Repository\ActiveProfileByAccountTelegram\ActiveProfileByAccountTelegramInterface;
use BaksDev\Barcode\Messenger\ScannerMessage;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Repository\ActiveWorkingManufacturePart\ActiveWorkingManufacturePartInterface;
use BaksDev\Manufacture\Part\Repository\AllWorkingByManufacturePart\AllWorkingByManufacturePartInterface;
use BaksDev\Manufacture\Part\Repository\CurrentManufacturePartEvent\CurrentManufacturePartEventInterface;
use BaksDev\Manufacture\Part\Type\Id\ManufacturePartUid;
use BaksDev\Manufacture\Part\UseCase\Admin\Action\ManufacturePartActionDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\Action\ManufacturePartActionForm;
use BaksDev\Telegram\Api\TelegramSendMessages;
use BaksDev\Telegram\Bot\Messenger\TelegramEndpointMessage\TelegramEndpointMessage;
use BaksDev\Telegram\Request\Type\TelegramRequestIdentifier;
use BaksDev\Users\UsersTable\Type\Actions\Working\UsersTableActionsWorkingUid;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[Autoconfigure(shared: false)]
#[AsMessageHandler(priority: 0)]
final class ScannerManufacturePartWorking
{
    public const string KEY = 'UfjQCCzp';

    private TelegramRequestIdentifier $request;

    public function __construct(
        #[Target('manufacturePartTelegramLogger')] private readonly LoggerInterface $logger,
        private readonly ActiveWorkingManufacturePartInterface $activeWorkingManufacturePart,
        private readonly AllWorkingByManufacturePartInterface $allWorkingByManufacturePart,
        private readonly CurrentManufacturePartEventInterface $CurrentManufacturePartEventRepository,
        private readonly FormFactoryInterface $formFactory,
        private readonly RouterInterface $router,
        private readonly Environment $twig,
    ) {}


    /**
     * Получаем состояние партии отправляем соответствующие действия
     */
    public function __invoke(ScannerMessage $message): void
    {
        if(false === empty($message->getContent()))
        {
            return;
        }


        /**
         * Получаем заявку на производство
         */

        $ManufacturePartUid = new ManufacturePartUid($message->getIdentifier());

        $CurrentManufacturePartEvent = $this->CurrentManufacturePartEventRepository
            ->forPart($ManufacturePartUid)
            ->find();

        if(false === ($CurrentManufacturePartEvent instanceof ManufacturePartEvent))
        {
            $this->logger->critical(
                'manufacture-part: Событие производственной партии на найдено',
                [self::class.':'.__LINE__, 'ManufacturePartUid' => (string) $ManufacturePartUid],
            );
        }

        /**
         * Получаем этап производства партии, который необходимо выполнить
         */
        $working = $this->activeWorkingManufacturePart
            ->findNextWorkingByManufacturePart($ManufacturePartUid);


        /**
         * Если все этапы выполнены - получаем все выполненные этапы
         */
        if(false === ($working instanceof UsersTableActionsWorkingUid))
        {
            return;
        }

        $ManufacturePartActionDTO = new ManufacturePartActionDTO();
        $CurrentManufacturePartEvent->getDto($ManufacturePartActionDTO);

        $ManufacturePartActionDTO
            ->getWorking()
            ->setWorking($working)
            ->setProfile(null);

        // Форма
        $form = $this->formFactory
            ->create(
                type: ManufacturePartActionForm::class,
                data: $ManufacturePartActionDTO,
                options: [
                    'action' => $this->router->generate(
                        name: 'manufacture-part:admin.scan',
                        parameters: ['id' => $ManufacturePartUid]),
                ],
            );


        /**
         * Получаем все этапы данной категории производства
         */
        $all = $this->allWorkingByManufacturePart
            ->forPart($ManufacturePartUid)
            ->findAll();


        $content = $this->twig->render(
            name: '@manufacture-part/admin/scan/content.html.twig',
            context: [
                'form' => $form->createView(),
                'part' => $CurrentManufacturePartEvent,
                'current' => $working,
                'all' => $all,
            ],
        );

        $message->setContent($content);
    }
}