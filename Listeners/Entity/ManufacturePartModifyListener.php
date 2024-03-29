<?php
/*
 *  Copyright 2022.  Baks.dev <admin@baks.dev>
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *   limitations under the License.
 *
 */

namespace BaksDev\Manufacture\Part\Listeners\Entity;

use BaksDev\Core\Type\Ip\IpAddress;
use BaksDev\Manufacture\Part\Entity\Modify\ManufacturePartModify;
use BaksDev\Users\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: ManufacturePartModify::class)]
final class ManufacturePartModifyListener
{
    private RequestStack $request;
    private TokenStorageInterface $token;

    public function __construct(
        RequestStack $request,
        TokenStorageInterface $token,
    ) {
        $this->request = $request;
        $this->token = $token;
    }

    public function prePersist(ManufacturePartModify $data, LifecycleEventArgs $event)
    {
        $token = $this->token->getToken();

        if ($token) {

            $data->setUsr($token->getUser());

            if($token instanceof SwitchUserToken)
            {
                /** @var User $originalUser */
                $originalUser = $token->getOriginalToken()->getUser();
                $data->setUsr($originalUser);
            }
        }

        // Если пользователь не из консоли
        if ($this->request->getCurrentRequest()) {
            $data->upModifyAgent(
                new IpAddress($this->request->getCurrentRequest()->getClientIp()), // Ip
                $this->request->getCurrentRequest()->headers->get('User-Agent') // User-Agent
            );
        }
    }

}