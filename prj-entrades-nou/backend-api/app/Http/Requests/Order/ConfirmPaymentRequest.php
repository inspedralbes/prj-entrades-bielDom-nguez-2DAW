<?php

namespace App\Http\Requests\Order;

//================================ NAMESPACES / IMPORTS ============

use Illuminate\Foundation\Http\FormRequest;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Cos buit: la comanda ve del path (`{order}`).
 */
class ConfirmPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
