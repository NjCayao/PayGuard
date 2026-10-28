<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

abstract class DemoCommand extends Command
{
    protected function guard(): ?int
    {
        if (app()->environment('production') && ! config('payguard.demo.enabled')) {
            $this->error('The demo commands are disabled in production. Set PAYGUARD_DEMO_ENABLED=true to enable them on purpose.');

            return self::FAILURE;
        }

        $email = (string) config('payguard.demo.email');
        $password = (string) config('payguard.demo.password');

        if ($email === '' || $password === '') {
            $this->error('Set PAYGUARD_DEMO_EMAIL and PAYGUARD_DEMO_PASSWORD in the .env file first.');

            return self::FAILURE;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('PAYGUARD_DEMO_EMAIL is not a valid email address.');

            return self::FAILURE;
        }

        return null;
    }
}
