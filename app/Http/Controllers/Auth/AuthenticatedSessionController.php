<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request)
    {
        try {
            $request->authenticate();
            $request->session()->regenerate();

            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            $credentials = [
                'email' => $request->email,
                'password' => $request->password,
            ];

            if (Auth::validate($credentials)){
                $user = Auth::getLastAttempted();
               if($user->is_admin){
                   return [
                       'token' => $user->createToken($user->name, ['admin'])->plainTextToken
                   ];
               } else {
                   return [
                       'token' => $user->createToken($user->name, ['user'])->plainTextToken
                   ];
               }
            }

            throw ValidationException::withMessages([
                'email' => ["Поле Email заполнено некорректно"],
                'password' => ["Поле 'Пароль' обязательно для заполнения"],
            ]);
        } catch (\Exception $e){
            return Response::json(array(
                'code' => $e->getCode(),
                'message'=>$e->getMessage()
            // ), $e->getCode());
            ), 401);
        }

//        return redirect()->intended(RouteServiceProvider::HOME);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(LoginRequest $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->user()->currentAccessToken()->delete();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
