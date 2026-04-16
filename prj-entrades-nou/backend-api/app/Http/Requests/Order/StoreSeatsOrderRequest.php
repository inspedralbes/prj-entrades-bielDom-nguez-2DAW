<?php

namespace App\Http\Requests\Order;

//================================ NAMESPACES / IMPORTS ============

use Illuminate\Foundation\Http\FormRequest;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

class StoreSeatsOrderRequest extends FormRequest
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
            'seat_ids' => ['required', 'array', 'min:1', 'max:6'],
            'seat_ids.*' => ['required', 'integer'],
            'anonymous_session_id' => ['required', 'string', 'max:128'],
        ];
    }
}
