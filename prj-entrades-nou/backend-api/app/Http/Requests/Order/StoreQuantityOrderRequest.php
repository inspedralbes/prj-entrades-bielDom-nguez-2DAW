<?php

namespace App\Http\Requests\Order;

//================================ NAMESPACES / IMPORTS ============

use Illuminate\Foundation\Http\FormRequest;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

class StoreQuantityOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<int|string>>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:6'],
        ];
    }
}
