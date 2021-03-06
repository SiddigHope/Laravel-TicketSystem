<?php

namespace App\Http\Controllers;

use App\Attendize\Utils;
use App\Models\Account;
use App\Models\User;
use Auth;
use Hash;
use Illuminate\Contracts\Auth\Guard;
use Input;
use Mail;
use Redirect;
use Session;
use Validator;
use View;

class UserSignupController extends Controller
{
    protected $auth;

    public function __construct(Guard $auth)
    {
        if (Account::count() > 0 && !Utils::isAttendize()) {
            return Redirect::route('login');
        }

        $this->auth = $auth;
        $this->middleware('guest');
    }

    public function showSignup()
    {
        return View::make('Public.LoginAndRegister.Signup');
    }

    /**
     * Creates an account.
     *
     * @return void
     */
    public function postSignup()
    {
        $rules = [
        'email'                      => ['required', 'email', 'unique:users'],
        'password'                   => ['required', 'min:5', 'confirmed'],
                'first_name'         => ['required'],
                'terms_agreed'       => Utils::isAttendize() ? ['required'] : '',
    ];

        $messages = [
        'email.email'                   => 'Please enter a valid E-mail address.',
        'email.required'                => 'E-mail address is required.',
        'password.required'             => 'Password is required.',
        'password.min'                  => 'Your password is too short! Min 5 symbols.',
        'email.unique'                  => 'This E-mail has already been taken.',
        'first_name.required'           => 'Please enter your first name.',
                'terms_agreed.required' => 'Please agree to our Terms of Service.',
    ];

        $validation = Validator::make(Input::all(), $rules, $messages);

        if ($validation->fails()) {
            return Redirect::to('signup')->withInput()->withErrors($validation);
        }

        $account = new Account();
        $account->email = Input::get('email');
        $account->first_name = Input::get('first_name');
        $account->last_name = Input::get('last_name');
        $account->currency_id = config('attendize.default_currency');
        $account->timezone_id = config('attendize.default_timezone');
        $account->save();

        $user = new User();
        $user->email = Input::get('email');
        $user->first_name = Input::get('first_name');
        $user->last_name = Input::get('last_name');
        $user->password = Hash::make(Input::get('password'));
        $user->account_id = $account->id;
        $user->is_parent = 1;
        $user->is_registered = 1;
        $user->save();

        if (Utils::isAttendize()) {
            Mail::send('Emails.ConfirmEmail', ['first_name' => $user->first_name, 'confirmation_code' => $user->confirmation_code], function ($message) {
                $message->to(Input::get('email'), Input::get('first_name'))
                    ->subject('Thank you for registering for Attendize');
            });
        }

        Session::flash('message', 'Success! You can now login.');

        return Redirect::to('login');
    }

    public function confirmEmail($confirmation_code)
    {
        $user = User::whereConfirmationCode($confirmation_code)->first();

        if (!$user) {
            return \View::make('Public.Errors.Generic', [
                'message' => 'The confirmation code is missing or malformed.',
            ]);
        }

        $user->is_confirmed = 1;
        $user->confirmation_code = null;
        $user->save();

        \Session::flash('message', 'Success! Your email is now verified. You can now login.');

        //$this->auth->login($user);

        return Redirect::route('login');
    }

    private function validateEmail($data)
    {
        $rules = [
            'email' => 'required|email|unique:users',
        ];

        return Validator::make($data, $rules);
    }
}
