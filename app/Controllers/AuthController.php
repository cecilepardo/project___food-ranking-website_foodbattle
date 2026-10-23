<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\RateLimiter;
use App\Models\User;

/**
 * Connexion et inscription sur une seule page, en deux étapes :
 * 1. l'utilisateur saisit son email ;
 * 2. s'il a un compte, on lui demande son mot de passe ; sinon, on lui propose d'en créer un.
 *
 * L'email passe d'une étape à l'autre dans un champ caché, modifiable par le navigateur :
 * chaque étape revalide donc tout ce qu'elle reçoit.
 */
class AuthController extends Controller
{
    private const EMAIL_MAX_LENGTH    = 254; // users.email VARCHAR(254)
    private const NICKNAME_MAX_LENGTH = 60;  // users.nickname VARCHAR(60)
    private const PASSWORD_MIN_LENGTH = 8;
    // bcrypt ignore tout ce qui dépasse 72 octets : on refuse plutôt que de tronquer en silence.
    private const PASSWORD_MAX_BYTES  = 72;

    // Mots de passe faux tolérés par compte avant blocage temporaire.
    private const LOGIN_MAX_FAILURES   = 5;
    private const LOGIN_WINDOW_SECONDS = 900; // 15 minutes

    /**
     * GET /connexion
     * Étape 1 : saisie de l'email.
     */
    public function emailForm(): void
    {
        $this->showEmailForm();
    }

    /**
     * POST /connexion
     * Choisit l'étape 2 : mot de passe si l'email a un compte, inscription sinon.
     */
    public function checkEmail(): void
    {
        $this->checkCsrf();

        $email = $this->normalizeEmail($this->postString('email'));

        if (!$this->isValidEmail($email)) {
            $this->showEmailForm($email, 'Saisis une adresse email valide.');
            return;
        }

        if (User::emailExists($email)) {
            $this->showPasswordForm($email);
            return;
        }

        $this->showRegisterForm($email);
    }

    /**
     * POST /connexion/mot-de-passe
     * Étape 2a : vérification du mot de passe.
     */
    public function login(): void
    {
        $this->checkCsrf();

        $email    = $this->normalizeEmail($this->postString('email'));
        $password = $this->postString('password');

        // L'email vient d'un champ caché : on le valide avant de s'en servir comme clé de comptage.
        if (!$this->isValidEmail($email)) {
            $this->showEmailForm($email, 'Saisis une adresse email valide.');
            return;
        }

        // Compteur par compte : freine une attaque sur un même compte, même répartie sur plusieurs IP.
        $throttleKey = 'login:' . $email;

        if (RateLimiter::tooManyAttempts($throttleKey, self::LOGIN_MAX_FAILURES, self::LOGIN_WINDOW_SECONDS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($throttleKey, self::LOGIN_WINDOW_SECONDS) / 60);
            $this->showPasswordForm($email, "Trop de mots de passe incorrects. Réessaie dans $minutes minute(s).", 429);
            return;
        }

        $user = Auth::attempt($email, $password);

        if ($user === null) {
            RateLimiter::hit($throttleKey);
            $this->showPasswordForm($email, 'Mot de passe incorrect.');
            return;
        }

        // Connexion réussie : les échecs précédents ne comptent plus.
        RateLimiter::clear($throttleKey);
        Auth::login($user);

        $this->redirectAfterLogin();
    }

    /**
     * POST /connexion/inscription
     * Étape 2b : création du compte.
     */
    public function register(): void
    {
        $this->checkCsrf();

        $email    = $this->normalizeEmail($this->postString('email'));
        $nickname = trim($this->postString('nickname'));
        $password = $this->postString('password');

        $error = $this->registrationError($email, $nickname, $password);

        if ($error !== null) {
            $this->showRegisterForm($email, $nickname, $error);
            return;
        }

        $userId = User::create($email, $nickname, Auth::hashPassword($password));

        // Connexion directe après l'inscription : le compte vient d'être créé avec le rôle par défaut.
        Auth::login(['id' => $userId, 'nickname' => $nickname, 'role' => 'user']);

        $this->redirectAfterLogin();
    }

    /**
     * POST /deconnexion
     */
    public function logout(): void
    {
        $this->checkCsrf();

        Auth::logout();

        $this->redirect('/');
    }

    /**
     * Après une connexion ou une inscription : la page demandée avant la connexion,
     * sinon l'accueil correspondant au rôle.
     */
    private function redirectAfterLogin(): never
    {
        $this->redirect(Auth::pullIntendedUrl() ?? Auth::homeUrl());
    }

    /**
     * Premier problème trouvé dans le formulaire d'inscription, ou null si tout est valide.
     * L'email est revérifié : il vient d'un champ caché et a pu être modifié depuis l'étape 1.
     */
    private function registrationError(string $email, string $nickname, string $password): ?string
    {
        if (!$this->isValidEmail($email)) {
            return 'Saisis une adresse email valide.';
        }
        if ($nickname === '' || mb_strlen($nickname) > self::NICKNAME_MAX_LENGTH) {
            return 'Le pseudo doit contenir entre 1 et 60 caractères.';
        }
        if (mb_strlen($password) < self::PASSWORD_MIN_LENGTH || strlen($password) > self::PASSWORD_MAX_BYTES) {
            return 'Le mot de passe doit contenir entre 8 et 72 caractères.';
        }
        if (User::emailExists($email)) {
            return 'Un compte existe déjà avec cet email.';
        }
        if (User::nicknameExists($nickname)) {
            return 'Ce pseudo est déjà pris.';
        }

        return null;
    }

    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && strlen($email) <= self::EMAIL_MAX_LENGTH;
    }

    /**
     * Même règle qu'en base (CHECK email = lower(email)) : "  Toto@Mail.fr " => "toto@mail.fr".
     */
    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function showEmailForm(string $email = '', ?string $error = null): void
    {
        $this->render('login-email', [
            'title' => 'Connexion',
            'email' => $email,
            'error' => $error,
        ]);
    }

    private function showPasswordForm(string $email, ?string $error = null, int $statusCode = 200): void
    {
        $this->render('login-password', [
            'title' => 'Connexion',
            'email' => $email,
            'error' => $error,
        ], $statusCode);
    }

    private function showRegisterForm(string $email, string $nickname = '', ?string $error = null): void
    {
        $this->render('login-register', [
            'title'    => 'Créer un compte',
            'email'    => $email,
            'nickname' => $nickname,
            'error'    => $error,
        ]);
    }
}
