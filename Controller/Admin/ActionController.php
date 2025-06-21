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

namespace BaksDev\Manufacture\Part\Controller\Admin;


use BaksDev\Barcode\Writer\BarcodeFormat;
use BaksDev\Barcode\Writer\BarcodeType;
use BaksDev\Barcode\Writer\BarcodeWrite;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Type\UidType\ParamConverter;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Repository\ActiveWorkingManufacturePart\ActiveWorkingManufacturePartInterface;
use BaksDev\Manufacture\Part\Repository\AllWorkingByManufacturePart\AllWorkingByManufacturePartInterface;
use BaksDev\Manufacture\Part\Repository\ManufacturePartCurrentEvent\ManufacturePartCurrentEventInterface;
use BaksDev\Manufacture\Part\Type\Id\ManufacturePartUid;
use BaksDev\Manufacture\Part\UseCase\Admin\Action\ManufacturePartActionDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\Action\ManufacturePartActionForm;
use BaksDev\Manufacture\Part\UseCase\Admin\Action\ManufacturePartActionHandler;
use BaksDev\Users\UsersTable\Type\Actions\Working\UsersTableActionsWorkingUid;
use chillerlan\QRCode\QRCode;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_MANUFACTURE_PART_ACTION')]
final class ActionController extends AbstractController
{
    private BarcodeWrite $BarcodeWrite;

    /**
     * Меняет производственное состояние и присваивает исполнителя
     */
    #[Route('/admin/manufacture/part/action/{id}', name: 'admin.action', methods: ['GET', 'POST'])]
    public function action(
        Request $request,
        #[ParamConverter(ManufacturePartUid::class)] $id,
        ManufacturePartActionHandler $ManufacturePartActionHandler,
        ActiveWorkingManufacturePartInterface $activeWorkingManufacturePart,
        AllWorkingByManufacturePartInterface $allWorkingByManufacturePart,
        ManufacturePartCurrentEventInterface $ManufacturePartCurrentEvent,
        BarcodeWrite $BarcodeWrite,
    ): Response
    {

        $ManufacturePartEvent = $ManufacturePartCurrentEvent
            ->fromPart($id)
            ->find();

        if(false === ($ManufacturePartEvent instanceof ManufacturePartEvent))
        {
            throw new InvalidArgumentException('Invalid Argument ManufacturePartEvent');
        }


        $this->BarcodeWrite = $BarcodeWrite;

        /**
         * Получаем все этапы данной категории производства
         */
        $all = $allWorkingByManufacturePart->fetchAllWorkingByManufacturePartAssociative($ManufacturePartEvent->getMain());

        /**
         * Получаем этап производства партии, который необходимо выполнить
         */
        $working = $activeWorkingManufacturePart->findNextWorkingByManufacturePart($ManufacturePartEvent->getMain());


        $data = sprintf('%s', $ManufacturePartEvent->getMain());

        /**
         * Если все этапы выполнены - получаем все выполненные этапы
         */
        if(false === ($working instanceof UsersTableActionsWorkingUid))
        {
            $all = $activeWorkingManufacturePart->fetchCompleteWorkingByManufacturePartAssociative($ManufacturePartEvent->getMain());

            return $this->render([
                'part' => $ManufacturePartEvent,
                'current' => $working,
                'all' => $all,
                'qrcode' => $this->renderQRCode($data),
            ], file: 'completed.html.twig');
        }

        $ManufacturePartActionDTO = new ManufacturePartActionDTO();
        $ManufacturePartEvent->getDto($ManufacturePartActionDTO);

        $ManufacturePartActionDTO
            ->getWorking()
            ->setWorking($working)
            ->setProfile(null);

        // Форма
        $form = $this
            ->createForm(
                type: ManufacturePartActionForm::class,
                data: $ManufacturePartActionDTO,
                options: ['action' => $this->generateUrl('manufacture-part:admin.action', ['id' => $ManufacturePartEvent->getMain()]),]
            )
            ->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('manufacture_part_action'))
        {
            $this->refreshTokenForm($form);

            $handle = $ManufacturePartActionHandler
                ->handle($ManufacturePartActionDTO);

            if($handle instanceof ManufacturePart)
            {
                $this->addFlash(
                    'admin.page.action',
                    'admin.success.action',
                    'manufacture-part.admin',
                );

                return $this->redirectToRoute('manufacture-part:admin.manufacture');
            }

            $this->addFlash(
                'admin.page.action',
                'admin.danger.action',
                'manufacture-part.admin',
                $handle);

            return $this->redirectToReferer();
        }


        return $this->render([
            'form' => $form->createView(),
            'part' => $ManufacturePartEvent,
            'current' => $working,
            'all' => $all,
            'qrcode' => $this->renderQRCode($data),
        ]);
    }


    private function renderQRCode(string $data): string
    {
        $barcode = $this->BarcodeWrite
            ->text($data)
            ->type(BarcodeType::QRCode)
            ->format(BarcodeFormat::SVG)
            ->generate(implode(DIRECTORY_SEPARATOR, ['barcode', 'test']));

        if($barcode === false)
        {
            /**
             * Проверить права на исполнение
             * chmod +x /home/bundles.baks.dev/vendor/baks-dev/barcode/Writer/Generate
             * chmod +x /home/bundles.baks.dev/vendor/baks-dev/barcode/Reader/Decode
             * */
            throw new RuntimeException('Barcode write error');
        }

        $render = $this->BarcodeWrite->render();
        $render = strip_tags($render, ['path']);
        $render = trim($render);
        $this->BarcodeWrite->remove();

        return $render;
    }
}
