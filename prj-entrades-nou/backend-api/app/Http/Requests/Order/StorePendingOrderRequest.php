<?php

namespace App\Http\Requests\Order;

//================================ NAMESPACES / IMPORTS ============

use Illuminate\Foundation\Http\FormRequest;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

class StorePendingOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|\Illuminate\Validation\Rules\Uuid>|string>
     */
    public function rules(): array
    {
        return [
            'hold_id' => ['required', 'uuid'],
            'anonymous_session_id' => ['required', 'string', 'max:128'],
        ];
    }
}
