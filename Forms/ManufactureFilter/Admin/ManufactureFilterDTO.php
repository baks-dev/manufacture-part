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

namespace BaksDev\Manufacture\Part\Forms\ManufactureFilter\Admin;

use BaksDev\Manufacture\Part\Forms\ManufactureFilter\ManufactureFilterInterface;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;

final class ManufactureFilterDTO implements ManufactureFilterInterface
{
    public const date = 'zbJIvDNPeN';

    public const status = 'vBbCItaRNB';

    private Request $request;

    /**
     * Дата
     */
    private ?DateTimeImmutable $date = null;


    /**
     * Статус производственной партии
     */
    private ?ManufacturePartStatus $status = null;


    public function __construct(Request $request)
    {
        $this->request = $request;
    }


    /**
     * Date.
     */
    public function getDate(): ?DateTimeImmutable
    {
        $session = $this->request->getSession();

        $sessionDate = $session->get(self::date) ?: new DateTimeImmutable();

        //dump(time() - $session->getMetadataBag()->getLastUsed());

        if(time() - $session->getMetadataBag()->getLastUsed() > 300)
        {
            $session->remove(self::date);
            $this->date = new DateTimeImmutable();
        }

        return $this->date ?: $sessionDate;
    }

    public function setDate(?DateTimeImmutable $date): void
    {
        if($date === null)
        {
            $this->request->getSession()->remove(self::date);
        }
        else
        {
            $this->request->getSession()->set(self::date, $date);
        }

        $this->date = $date;
    }

    /**
     * Status
     */
    public function getStatus(): ?ManufacturePartStatus
    {
        $sessionStatus = $this->request->getSession()->get(self::status) ?: null;

        return $this->status ?: $sessionStatus;

    }

    public function setStatus(?ManufacturePartStatus $status): self
    {
        if(empty($this->status))
        {
            $this->request->getSession()->remove(self::status);
        }

        $this->status = $status;
        return $this;
    }


}
