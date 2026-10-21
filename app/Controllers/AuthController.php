<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\User;

/**
 * Connexion, inscription et déconnexion.
 */
class AuthController extends Controller
{
    private const EMAIL_MAX_LENGTH    = 254; // users.email VARCHAR(254)
    private const NICKNAME_MAX_LENGTH = 60;  // users.nickname VARCHAR(60)
    private const PASSWORD_MIN_LENGTH = 8;
    // bcrypt ignore tout ce qui dépasse 72 octets : on refuse plutôt que de tronquer en silence.
    private const PASSWORD_MAX_BYTES  = 72;

    /**
     * GET /connexion
     */
    public function loginForm(): void
    {
        $this->showLoginForm();
    }

    /**
     * POST /connexion
     */
    public function login(): void
    {
        $this->checkCsrf();

        $email    = $this->normalizeEmail($this->postString('email'));
        $password = $this->postString('password');

        $user = Auth::attempt($email, $password);

        if ($user === null) {
            // Message volontairement vague : on ne dit pas si c'est l'email ou le mot de passe qui est faux.
            $this->showLoginForm($email, 'Email ou mot de passe incorrect.');
            return;
        }

        Auth::login($user);

        $this->redirect($user['role'] === 'admin' ? '/dashboard' : '/');
    }

    /**
     * GET /inscription
     */
    public function registerForm(): void
    {
        $this->showRegisterForm();
    }

    /**
     * POST /inscription
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

        $this->redirect('/');
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
     * Premier problème trouvé dans le formulaire d'inscription, ou null si tout est valide.
     */
    private function registrationError(string $email, string $nickname, string $password): ?string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > self::EMAIL_MAX_LENGTH) {
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

    /**
     * Valeur texte d'un champ POST ('' si absent ou si ce n'est pas du texte, ex. "email[]=x").
     */
    private function postString(string $key): string
    {
        $value = $_POST[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * Même règle qu'en base (CHECK email = lower(email)) : "  Toto@Mail.fr " => "toto@mail.fr".
     */
    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function showLoginForm(string $email = '', ?string $error = null): void
    {
        $this->render('login', [
            'title' => 'Connexion',
            'email' => $email,
            'error' => $error,
        ]);
    }

    private function showRegisterForm(string $email = '', string $nickname = '', ?string $error = null): void
    {
        $this->render('register', [
            'title'    => 'Inscription',
            'email'    => $email,
            'nickname' => $nickname,
            'error'    => $error,
        ]);
    }
}
