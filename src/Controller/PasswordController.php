<?php
// src/Controller/PasswordController.php
namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PasswordController extends AbstractController
{
    #[Route('/change-password', name: 'app_change_password', methods: ['GET','POST'])]
    public function changePassword(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {
            $new = (string) $request->request->get('password');
            $confirm = (string) $request->request->get('confirm_password');

            if ($new === '' || $new !== $confirm) {
                $this->addFlash('error', 'Passwords do not match or empty.');
                return $this->redirectToRoute('app_change_password');
            }

            // optionally validate password strength here

            $hashed = $passwordHasher->hashPassword($user, $new);
            $user->setPassword($hashed);
            $user->setPasswordChangedAt(new \DateTimeImmutable());
            $user->resetFailedLoginCount();
            $user->setLockedUntil(null);

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Password updated. You are now logged in.');
            return $this->redirectToRoute('app_home');
        }

        return $this->render('password_change.html.twig', []);
    }
}
