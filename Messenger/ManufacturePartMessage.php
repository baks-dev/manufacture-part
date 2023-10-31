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

namespace BaksDev\Manufacture\Part\Messenger;

use BaksDev\Manufacture\Part\Type\Event\ManufacturePartEventUid;
use BaksDev\Manufacture\Part\Type\Id\ManufacturePartUid;

final class ManufacturePartMessage
{
    /**
     * Идентификатор
     */
    private ManufacturePartUid $id;

    /**
     * Идентификатор события
     */
    private ManufacturePartEventUid $event;

    /**
     * Идентификатор предыдущего события
     */
    private ?ManufacturePartEventUid $last;

    /**
     * Количество
     */
    private int $total;


    public function __construct(
        ManufacturePartUid $id,
        ManufacturePartEventUid $event,
        ?ManufacturePartEventUid $last = null,
        int $total = 0
    )
    {
        $this->last = $last;
        $this->id = $id;
        $this->event = $event;
        $this->total = $total;
    }

    /**
     * Идентификатор
     */
    public function getId(): ManufacturePartUid
    {
        return $this->id;
    }

    /**
     * Идентификатор события
     */
    public function getEvent(): ManufacturePartEventUid
    {
        return $this->event;
    }

    /**
     * Идентификатор предыдущего события
     */
    public function getLast(): ?ManufacturePartEventUid
    {
        return $this->last;
    }

    /**
     * Total
     */
    public function getTotal(): int
    {
        return $this->total;
    }

}