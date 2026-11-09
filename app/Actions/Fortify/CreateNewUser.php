<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ], [
            'phone.regex' => 'Format nomor HP tidak valid, contoh: 08123456789.',
            'phone.unique' => 'Nomor HP ini sudah terdaftar.',
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'phone' => $input['phone'],
            'password' => $input['password'],
        ]);
    }
}
