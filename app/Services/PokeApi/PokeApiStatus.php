<?php

namespace App\Services\PokeApi;

enum PokeApiStatus: string
{
    case Success = 'success';
    case NotFound = 'not_found';
    case Unavailable = 'unavailable';
}
