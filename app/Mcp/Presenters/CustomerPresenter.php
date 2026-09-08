<?php

namespace App\Mcp\Presenters;

use App\Models\Customer;

/**
 * The allow-list for customers. Email, phone and internal notes exist on the
 * model and are never returned here. Add a field only after deciding the
 * model is allowed to see it.
 */
final class CustomerPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function public(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'city' => $customer->city,
        ];
    }
}
