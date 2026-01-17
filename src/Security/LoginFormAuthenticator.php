<?php
// src/Security/LoginFormAuthenticator.php
namespace App\Security;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class LoginFormAuthenticator extends AbstractAuthenticator
{
    use TargetPathTrait;

    private UserRepository $users;
    private RouterInterface $router;
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;

    // lockout policy
    private int $maxFailed = 5;
    private int $lockMinutes = 15;

    // password expiry period (6 months)
    private \DateInterval $passwordExpiryInterval;

    public function __construct(
        UserRepository $users,
        RouterInterface $router,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ) {
        $this->users = $users;
        $this->router = $router;
        $this->em = $em;
        $this->passwordHasher = $passwordHasher;
        $this->passwordExpiryInterval = new \DateInterval('P6M');
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'app_login' && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $email = (string) $request->request->get('email', '');
        $password = (string) $request->request->get('password', '');

        $user = $this->users->findOneByEmail($email);

        // Generic message so we don't reveal user existence
        if (!$user) {
            throw new CustomUserMessageAuthenticationException('Invalid credentials.');
        }

        // If account locked in DB
        if (method_exists($user, 'isLocked') && $user->isLocked()) {
            throw new CustomUserMessageAuthenticationException('Account locked. Try again later or contact admin.');
        }

        // Build passport with PasswordCredentials (Symfony will verify)
        return new Passport(
            new UserBadge($email, function ($userIdentifier) {
                return $this->users->findOneByEmail($userIdentifier);
            }),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

        // Reset failed attempts on success
        if (is_object($user) && method_exists($user, 'resetFailedLoginCount')) {
            $user->resetFailedLoginCount();
            if (method_exists($user, 'setLockedUntil')) {
                $user->setLockedUntil(null);
            }
            $this->em->persist($user);
            $this->em->flush();
        }

        // Check password expiry (if user has getPasswordChangedAt)
        if (is_object($user) && method_exists($user, 'getPasswordChangedAt')) {
            $changedAt = $user->getPasswordChangedAt();
            $now = new \DateTimeImmutable();
            $expired = false;
            if ($changedAt === null) {
                $expired = true;
            } else {
                $expiresAt = $changedAt->add($this->passwordExpiryInterval);
                if ($expiresAt <= $now) {
                    $expired = true;
                }
            }

            if ($expired) {
                // redirect to change-password page (force)
                return new RedirectResponse($this->router->generate('app_change_password'));
            }
        }

        // Redirect to previously requested page or homepage
        $target = $this->getTargetPath($request->getSession(), $firewallName);
        if ($target) {
            return new RedirectResponse($target);
        }

        return new RedirectResponse($this->router->generate('web_homepage'));

    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // increment failed count in DB for the user (if exists)
        $email = (string) $request->request->get('email', '');
        $user = $this->users->findOneByEmail($email);
        if ($user) {
            if (method_exists($user, 'incrementFailedLoginCount')) {
                $user->incrementFailedLoginCount();
            }
            if (method_exists($user, 'getFailedLoginCount') && $user->getFailedLoginCount() >= $this->maxFailed) {
                if (method_exists($user, 'setLockedUntil')) {
                    $user->setLockedUntil((new \DateTimeImmutable())->add(new \DateInterval('PT' . ($this->lockMinutes * 60) . 'S')));
                }
            }
            $this->em->persist($user);
            $this->em->flush();
        }

        // Return to login with generic message (store message in session)
        if ($request->hasSession()) {
            $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
        }


        return new RedirectResponse($this->router->generate('app_login'));
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        // default redirect to login page when auth is required
        return new RedirectResponse($this->router->generate('app_login'));
    }
}
