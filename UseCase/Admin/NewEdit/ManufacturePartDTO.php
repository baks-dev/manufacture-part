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

namespace BaksDev\Manufacture\Part\UseCase\Admin\NewEdit;

use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEventInterface;
use BaksDev\Manufacture\Part\Type\Complete\Collection\ManufacturePartCompleteNothing;
use BaksDev\Manufacture\Part\Type\Complete\ManufacturePartComplete;
use BaksDev\Manufacture\Part\Type\Event\ManufacturePartEventUid;
use BaksDev\Products\Category\Type\Id\ProductCategoryUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\UsersTable\Type\Actions\Event\UsersTableActionsEventUid;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ManufacturePartEvent */
final class ManufacturePartDTO implements ManufacturePartEventInterface
{

    /**
     * Идентификатор события
     */
    #[Assert\Uuid]
    private ?ManufacturePartEventUid $id = null;


    /**
     * Идентификатор процесса производства (!! События)
     */
    //#[Assert\NotBlank]
    #[Assert\Uuid]
    private UsersTableActionsEventUid $action;

    /**
     * Профиль ответственного.
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private UserProfileUid $profile;


    /**
     * Завершающий этап
     */
    #[Assert\NotBlank]
    private ?ManufacturePartComplete $complete = null;


    /* Вспомогательные свойства */

    /**
     * Категория производства
     */
    #[Assert\Uuid]
    private ?ProductCategoryUid $category = null;


    /**
     * Фильтр по профилю.
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private UserProfileUid $filter;


    public function __construct() {
        $this->complete = new ManufacturePartComplete(ManufacturePartCompleteNothing::class);
    }

    //    /**
    //     * Маркетплейс
    //     */
    //    #[Assert\NotBlank]
    //    private ManufacturePartMarketplace $marketplace;

    //    /**
    //     * Рабочее состояние производственной партии
    //     */
    //    #[Assert\Valid]
    //    private Working\ManufacturePartWorkingDTO $working;

    //    /**
    //     * Коллекция продукции
    //     */
    //    #[Assert\Valid]
    //    private ArrayCollection $product;


    /**
     * Комментарий
     */
    #[Assert\Length(max: 255)]
    private ?string $comment = null;

    //    public function __construct()
    //    {
    //        //$this->working = new Working\ManufacturePartWorkingDTO();
    //        $this->product = new ArrayCollection();
    //    }

    /**
     * Идентификатор события
     */
    public function getEvent(): ?ManufacturePartEventUid
    {
        return $this->id;
    }

    /**
     * Профиль ответственного.
     */
    public function getProfile(): UserProfileUid
    {
        return $this->profile;
    }

    public function setProfile(UserProfileUid $profile): self
    {
        $this->profile = $profile;
        return $this;
    }

    /**
     * Filter
     */
    public function getFilter(): UserProfileUid
    {
        return $this->filter;
    }

    public function setFilter(UserProfileUid $filter): self
    {
        $this->filter = $filter;
        return $this;
    }

    /**
     * Категория производства
     */

    public function getCategory(): ?ProductCategoryUid
    {
        return $this->category;
    }

    public function setCategory(ProductCategoryUid|string $category): void
    {
        if(is_string($category))
        {
            $category = new ProductCategoryUid($category);
        }

        $this->category = $category;
    }

    /**
     * Complete
     */
    public function getComplete(): ManufacturePartComplete
    {
        return $this->complete ?: new ManufacturePartComplete(ManufacturePartCompleteNothing::class);
    }

    public function setComplete(?ManufacturePartComplete $complete): self
    {
        if($complete)
        {
            $this->complete = $complete;
        }
        
        return $this;
    }



    /**
     * Comment
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * Action
     */
    public function getAction(): UsersTableActionsEventUid
    {
        return $this->action;
    }

    public function setAction(UsersTableActionsEventUid $action): self
    {
        $this->action = $action;
        return $this;
    }

}