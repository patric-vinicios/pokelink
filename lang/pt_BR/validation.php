<?php

return [

    'required' => 'O campo :attribute é obrigatório.',
    'string' => 'O campo :attribute deve ser um texto.',
    'email' => 'O campo :attribute deve ser um endereço de e-mail válido.',
    'unique' => 'O :attribute informado já está em uso.',
    'confirmed' => 'A confirmação do campo :attribute não confere.',

    'min' => [
        'array' => 'O campo :attribute deve ter pelo menos :min itens.',
        'file' => 'O campo :attribute deve ter pelo menos :min kilobytes.',
        'numeric' => 'O campo :attribute deve ser pelo menos :min.',
        'string' => 'O campo :attribute deve ter pelo menos :min caracteres.',
    ],

    'max' => [
        'array' => 'O campo :attribute não deve ter mais que :max itens.',
        'file' => 'O campo :attribute não deve ser maior que :max kilobytes.',
        'numeric' => 'O campo :attribute não deve ser maior que :max.',
        'string' => 'O campo :attribute não deve ter mais que :max caracteres.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Exact wording required by the F03 registration contract, keyed by
    | "attribute.rule" so it takes priority over the generic lines above.
    |
    */

    'custom' => [
        'email' => [
            'email' => 'Informe um e-mail válido.',
            'unique' => 'Este e-mail já está cadastrado.',
        ],
        'password' => [
            'min' => 'A senha deve ter pelo menos 8 caracteres.',
            'confirmed' => 'A confirmação de senha não confere.',
        ],
    ],

    'attributes' => [
        'name' => 'nome',
        'email' => 'e-mail',
        'password' => 'senha',
        'password_confirmation' => 'confirmação de senha',
    ],

];
