<?php

namespace App\Http\Requests;

use App\Actions\UpdateProfile;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return UpdateProfile::rules($user);
    }

    protected function prepareForValidation(): void
    {
        UpdateProfile::prepare($this);
    }
}
