<?php
/*
The MIT License (MIT)

Copyright (c) 2014 eve-seat

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

use App\Services\Validators;

class UserController extends BaseController
{

    /*
    |--------------------------------------------------------------------------
    | __construct()
    |--------------------------------------------------------------------------
    |
    | Sets up the class to ensure that CSRF tokens are validated on the POST
    | verb
    |
    */

    public function __construct()
    {
        $this->beforeFilter('csrf', array('on' => 'post'));
    }

    /*
    |--------------------------------------------------------------------------
    | getAll()
    |--------------------------------------------------------------------------
    |
    | Get all of the users in the database
    |
    */

    public function getAll()
    {

        $users = \User::all();

        return View::make('user.all')
            ->with(array('users' => $users));
    }

    /*
    |--------------------------------------------------------------------------
    | postNewUser()
    |--------------------------------------------------------------------------
    |
    | Registers a new user in the database
    |
    */

    public function postNewUser()
    {

        // Grab the inputs and validate them
        $new_user = Input::only(
            'email', 'username', 'password', 'first_name', 'last_name', 'is_admin'
        );

        $validation = new Validators\SeatUserValidator($new_user);

        // Should the form validation pass, continue to attempt to add this user
        if ($validation->passes()) {

            $user = new \User;
            $user->email = Input::get('email');
            $user->username = Input::get('username');
            $user->password = Hash::make(Input::get('password'));

            if (Input::get('is_admin') == 'yes') {

                $adminGroup = \Auth::findGroupByName('Administrators');
                $user->addGroup($adminGroup);
            }

            $user->save();

            return Redirect::action('UserController@getAll')
                ->with('success', 'User ' . Input::get('email') . ' has been added');
            
        } else {

            return Redirect::back()
                    ->withInput()
                ->withErrors($validation->errors);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | getDetail()
    |--------------------------------------------------------------------------
    |
    | Show all of the user details
    |
    */

    public function getDetail($userID)
    {

        try {

            $user = Sentry::findUserById($userID);
            $allGroups = Sentry::findAllGroups();
            $tmp = $user->getGroups();
            $hasGroups = array();

            foreach($tmp as $group)
                $hasGroups = array_add($hasGroups, $group->name, '1');

        } catch (Cartalyst\Sentry\Users\UserNotFoundException $e) {

            App::abort(404);
        }

        return View::make('user.detail')
            ->with('user', $user)
            ->with('availableGroups', $allGroups)
            ->with('hasGroups', $hasGroups);
    }

    /*
    |--------------------------------------------------------------------------
    | getImpersonate()
    |--------------------------------------------------------------------------
    |
    | Impersonate a user
    |
    */

    public function getImpersonate($userID)
    {

        try {

            $user = Sentry::findUserById($userID);

        } catch (Cartalyst\Sentry\Users\UserNotFoundException $e) {

            App::abort(404);
        }

        Sentry::login($user);

        return Redirect::action('HomeController@showIndex')
            ->with('warning', 'You are now impersonating ' . $user->email);
    }

    /*
    |--------------------------------------------------------------------------
    | postUpdateUser()
    |--------------------------------------------------------------------------
    |
    | Changes a user's details
    |
    */

    public function postUpdateUser()
    {

        try {

            $user = Sentry::findUserById(Input::get('userID'));

        } catch (Cartalyst\Sentry\Users\UserNotFoundException $e) {

            App::abort(404);
        }

        try {

            $adminGroup = Sentry::findGroupByName('Administrators');

        } catch (Cartalyst\Sentry\Groups\GroupNotFoundException $e) {

            return Redirect::back()
                ->withInput()
                ->withErrors('Administrators group could not be found');
        }

        $user->email = Input::get('email');

        if (Input::get('username') != '')
            $user->username = Input::get('username');

        if (Input::get('password') != '')
            $user->password = Input::get('password');

        $user->first_name = Input::get('first_name');
        $user->last_name = Input::get('last_name');

        $groups = Input::except('_token', 'username', 'password', 'first_name', 'last_name', 'userID', 'email');

        // This section is probably super-inneficcient, but its late and i'm fucking tired/drunk
        // Future me: fix this somehow

        $tmp = $user->getGroups();
        foreach($tmp as $currentGroup)
            $user->removeGroup($currentGroup);

        foreach($groups as $group => $value) {

            $thisGroup = Sentry::findGroupByName(str_replace("_", " ", $group));
            $user->addGroup($thisGroup);
        }

        // MESSAGE ENDS

        if ($user->save())
            return Redirect::action('UserController@getDetail', array($user->getKey()))
                ->with('success', 'User has been updated');
        else
            return Redirect::back()
                ->withInput()
                ->withErrors('Error updating user');
    }

    /*
    |--------------------------------------------------------------------------
    | getDeleteUser()
    |--------------------------------------------------------------------------
    |
    | Deletes a user from the database
    |
    */

    public function getDeleteUser($userID)
    {

        try {

            $user = Sentry::findUserById($userID);
            $user->delete();

        } catch (Cartalyst\Sentry\Users\UserNotFoundException $e) {

            App::abort(404);
        }

        return Redirect::action('UserController@getAll')
            ->with('success', 'User has been deleted');
    }
}
