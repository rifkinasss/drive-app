<?php

namespace App\Services;

use Illuminate\Validation\Rules\Password;

class PasswordPolicyService
{
    public function __construct(private readonly SystemSettingService $settings) {}

    public function rule(): Password
    {
        $rule = Password::min($this->settings->getInt('security.password_min_length'));
        if ($this->settings->getBool('security.password_require_uppercase')) {
            $rule->mixedCase();
        }
        if ($this->settings->getBool('security.password_require_number')) {
            $rule->numbers();
        }
        if ($this->settings->getBool('security.password_require_special')) {
            $rule->symbols();
        }

        return $rule;
    }
}
