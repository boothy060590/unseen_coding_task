<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\UserService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Constructor
     *
     * @param UserService $userService
     */
    public function __construct(
        private UserService $userService
    ) {}

    /**
     * Show the registration form
     *turn View
     */
    public function showRegister(): View
    {
        return view('auth.register');
    }

    /**
     * Handle user registration
     *
     * @param RegisterRequest $request
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function register(RegisterRequest $request): RedirectResponse
    {
        $user = $this->userService->createUser([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => $request->password,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect()
            ->intended(route('dashboard'))
            ->with('success', 'Registration successful! Please verify your email.');
    }

    /**
     * Show the login form
     *
     * @return View
     */
    public function showLogin(): View
    {
        return view('auth.login');
    }

    /**
     * Handle user login
     *
     * @param LoginRequest $request
     * @return RedirectResponse
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Handle user logout
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'You have been logged out.');
    }

    /**
     * Show the dashboard for authenticated users
     *
     * @return View
     */
    public function dashboard(): View
    {
        return view('dashboard');
    }
}
