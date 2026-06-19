<?php

namespace App\Security\Voter;

use App\Entity\Employe;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class EmployeVoter extends Voter
{
    const VIEW = 'view';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW && $subject instanceof Employe;
    }

    // Ajout du paramètre optionnel $vote
    protected function voteOnAttribute(
        string $attribute, 
        mixed $subject, 
        TokenInterface $token,
        ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null  
    ): bool {
        $user = $token->getUser();
        
        if (!$user instanceof UserInterface) {
            return false;
        }

        /** @var Employe $employe */
        $employe = $subject;

        // Admin peut tout voir
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        // Employé peut voir son propre historique
        if ($user instanceof User && $user->getEmploye() === $employe) {
            return true;
        }

        return false;
    }
}