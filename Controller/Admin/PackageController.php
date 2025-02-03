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


use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Repository\ManufacturePartCurrentEvent\ManufacturePartCurrentEventInterface;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\ManufacturePartHandler;
use BaksDev\Manufacture\Part\UseCase\Admin\Package\ManufacturePartPackageDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\Package\ManufacturePartPackageForm;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_MANUFACTURE_PART_NEW')]
final class PackageController extends AbstractController
{
    /**
     *  Отправить партию на производство
     */
    #[Route('/admin/manufacture/part/package/{id}', name: 'admin.package', methods: ['GET', 'POST'])]
    public function package(
        Request $request,
        #[MapEntity] ManufacturePart $ManufacturePart,
        ManufacturePartHandler $ManufacturePartHandler,
        ManufacturePartCurrentEventInterface $manufacturePartCurrentEvent
    ): Response
    {

        $ManufacturePartEvent = $manufacturePartCurrentEvent
            ->fromPart($ManufacturePart)
            ->find();

        if(!$ManufacturePartEvent)
        {
            throw new InvalidArgumentException('Page not found');
        }

        $ManufacturePartPackageDTO = new ManufacturePartPackageDTO($ManufacturePart->getEvent());
        $ManufacturePartEvent->getDto($ManufacturePartPackageDTO);


        $form = $this->createForm(ManufacturePartPackageForm::class, $ManufacturePartPackageDTO, [
            'action' => $this->generateUrl('manufacture-part:admin.package',
                ['id' => $ManufacturePart->getId()]
            ),
        ]);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('manufacture_part_package'))
        {
            $this->refreshTokenForm($form);

            $handle = $ManufacturePartHandler->handle($ManufacturePartPackageDTO);

            if($handle instanceof ManufacturePart)
            {
                $this->addFlash('admin.page.package', 'admin.success.package', 'admin.manufacture.part');

                return $this->redirectToRoute('manufacture-part:admin.products.index',['id' => $ManufacturePart->getId()]);
            }

            $this->addFlash(
                'admin.page.package',
                'admin.danger.package',
                'admin.manufacture.part',
                $handle
            );

            return $this->redirectToRoute('manufacture-part:admin.index', status: 400);
        }
        
        return $this->render([
            'form' => $form->createView(),
            'number' => $ManufacturePart->getNumber()
        ]);
    }
}
