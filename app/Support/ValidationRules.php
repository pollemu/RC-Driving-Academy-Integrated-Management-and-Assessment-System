<?php

namespace App\Support;

/* Shared, human-friendly input rules used by every place that creates or
   edits an account (self sign-up, admin student form, profile editing). */
class ValidationRules
{
    /* A person's name: letters (any language), spaces, period, apostrophe and
       hyphen only. Must start with a letter, 2–60 characters. This blocks
       digits, emoji and HTML while still allowing "Ma. Cristina", "O'Brien"
       and "Dela Cruz-Santos". */
    public const NAME = [
        'required',
        'string',
        'min:2',
        'max:60',
        'regex:/^\pL[\pL .\'-]*$/u',
    ];

    /* A Philippine mobile number in canonical 09XXXXXXXXX form. */
    public const PHONE = [
        'nullable',
        'string',
        'regex:/^09\d{9}$/',
    ];
}
