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

namespace BaksDev\Manufacture\Part\Controller\Admin;


use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\UseCase\Admin\Delete\ManufacturePartDeleteDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\Delete\ManufacturePartDeleteForm;
use BaksDev\Manufacture\Part\UseCase\Admin\Delete\ManufacturePartDeleteHandler;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[RoleSecurity('ROLE_MANUFACTURE_PART_DELETE')]
final class DeleteController extends AbstractController
{
    #[Route('/admin/manufacture/part/delete/{id}', name: 'admin.delete', methods: ['GET', 'POST'])]
    public function delete(
        Request $request,
        #[MapEntity] ManufacturePart $ManufacturePart,
        ManufacturePartDeleteHandler $ManufacturePartDeleteHandler,
    ): Response
    {

        $ManufacturePartDeleteDTO = new ManufacturePartDeleteDTO($ManufacturePart->getEvent());

        $form = $this->createForm(ManufacturePartDeleteForm::class, $ManufacturePartDeleteDTO, [
            'action' => $this->generateUrl('manufacture-part:admin.delete',
                ['id' => $ManufacturePart->getId()]),
        ]);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('manufacture_part_delete'))
        {

            $handle = $ManufacturePartDeleteHandler->handle($ManufacturePartDeleteDTO);

            if($handle instanceof ManufacturePart)
            {
                $this->addFlash('admin.page.delete', 'admin.success.delete', 'admin.manufacture.part');

                return $this->redirectToRoute('manufacture-part:admin.manufacture');
            }

            $this->addFlash(
                'admin.page.delete',
                'admin.danger.delete',
                'admin.manufacture.part',
                $handle
            );

            return $this->redirectToRoute('manufacture-part:admin.manufacture', status: 400);
        }

        return $this->render([
            'form' => $form->createView(),
            'number' => $ManufacturePart->getNumber()
        ]);
    }
}
