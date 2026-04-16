<?php

namespace App\Http\Requests\Order;

//================================ NAMESPACES / IMPORTS ============

use Illuminate\Foundation\Http\FormRequest;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

class StoreCinemaSeatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<int|string|array<int, string>>>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer'],
            'seat_keys' => ['required', 'array', 'min:1', 'max:6'],
            'seat_keys.*' => ['required', 'string', 'max:255'],
        ];
    }
}
