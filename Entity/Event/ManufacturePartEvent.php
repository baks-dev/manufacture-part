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

namespace BaksDev\Manufacture\Part\Entity\Event;

use BaksDev\Core\Entity\EntityEvent;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Entity\Modify\ManufacturePartModify;
use BaksDev\Manufacture\Part\Entity\Products\ManufacturePartProduct;
use BaksDev\Manufacture\Part\Entity\Working\ManufacturePartWorking;
use BaksDev\Manufacture\Part\Type\Complete\ManufacturePartComplete;
use BaksDev\Manufacture\Part\Type\Event\ManufacturePartEventUid;
use BaksDev\Manufacture\Part\Type\Id\ManufacturePartUid;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusOpen;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\UsersTable\Type\Actions\Event\UsersTableActionsEventUid;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'manufacture_part_event')]
#[ORM\Index(columns: ['action'])]
#[ORM\Index(columns: ['status'])]
#[ORM\Index(columns: ['profile'])]
#[ORM\Index(columns: ['complete'])]
class ManufacturePartEvent extends EntityEvent
{
    /**
     * Идентификатор События
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Id]
    #[ORM\Column(type: ManufacturePartEventUid::TYPE)]
    private ManufacturePartEventUid $id;


    /**
     * Идентификатор ManufacturePart
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Column(type: ManufacturePartUid::TYPE, nullable: false)]
    private ?ManufacturePartUid $main = null;

    /**
     * Идентификатор процесса производства
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Column(type: UsersTableActionsEventUid::TYPE)]
    private UsersTableActionsEventUid $action;

    /**
     * Профиль ответственного.
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Column(type: UserProfileUid::TYPE)]
    private UserProfileUid $profile;

    /**
     * Фиксация производственной партии
     */
    #[ORM\Column(type: UserProfileUid::TYPE, nullable: true)]
    private ?UserProfileUid $fixed = null;

    /**
     * Статус производственной партии
     */
    #[Assert\NotBlank]
    #[ORM\Column(type: ManufacturePartStatus::TYPE)]
    private ManufacturePartStatus $status;


    /**
     * Завершающий этап
     */
    #[Assert\NotBlank]
    #[ORM\Column(type: ManufacturePartComplete::TYPE, nullable: true)]
    private ManufacturePartComplete $complete;


    /**
     * Коллекция продукции
     */
    #[Assert\Valid]
    #[ORM\OneToMany(targetEntity: ManufacturePartProduct::class, mappedBy: 'event', cascade: ['all'])]
    private Collection $product;

    /**
     * Рабочее состояние партии
     */
    #[Assert\Valid]
    #[ORM\OneToOne(targetEntity: ManufacturePartWorking::class, mappedBy: 'event', cascade: ['all'])]
    private ?ManufacturePartWorking $working = null;

    /**
     * Модификатор
     */
    #[Assert\Valid]
    #[ORM\OneToOne(targetEntity: ManufacturePartModify::class, mappedBy: 'event', cascade: ['all'])]
    private ManufacturePartModify $modify;


    /**
     * Комментарий
     */
    #[Assert\Length(max: 255)]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;


    public function __construct()
    {
        $this->id = new ManufacturePartEventUid();
        $this->modify = new ManufacturePartModify($this);
        $this->status = new ManufacturePartStatus(ManufacturePartStatusOpen::class);
    }

    /**
     * Идентификатор События
     */

    public function __clone()
    {
        $this->id = clone $this->id;
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }

    public function getId(): ManufacturePartEventUid
    {
        return $this->id;
    }


    public function getWorking(): ?ManufacturePartWorking
    {
        return $this->working;
    }

    public function resetWorking(): void
    {
        $this->working = null;
    }


    /**
     * Status
     */
    public function getStatus(): ManufacturePartStatus
    {
        return $this->status;
    }


    public function equalsManufacturePartStatus(mixed $status): bool
    {
        return $this->status->equals($status);
    }

    public function equalsManufacturePartComplete(mixed $complete): bool
    {
        return $this->complete->equals($complete);
    }


    /**
     * Action
     */
    public function getAction(): UsersTableActionsEventUid
    {
        return $this->action;
    }

    /**
     * Profile
     */
    public function getProfile(): UserProfileUid
    {
        return $this->profile;
    }

    /**
     * Идентификатор ManufacturePart
     */
    public function setMain(ManufacturePartUid|ManufacturePart $main): void
    {
        $this->main = $main instanceof ManufacturePart ? $main->getId() : $main;
    }


    public function getMain(): ?ManufacturePartUid
    {
        return $this->main;
    }

    public function getDto($dto): mixed
    {
        $dto = is_string($dto) && class_exists($dto) ? new $dto() : $dto;

        if($dto instanceof ManufacturePartEventInterface)
        {
            return parent::getDto($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

    public function setEntity($dto): mixed
    {
        if($dto instanceof ManufacturePartEventInterface || $dto instanceof self)
        {
            return parent::setEntity($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }
}