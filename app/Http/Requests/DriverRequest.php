<?php

// app/Http/Requests/DriverRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DriverRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $driverId = $this->driver->id ?? null;

        return [
            'name'         => 'required|string|max:120',
            'phone'        => 'nullable|string|max:40',
            'email'        => 'nullable|email|max:190',
            'document_id'  => 'nullable|string|max:80',
            'status'       => 'nullable|in:offline,idle,busy',
            'foto'         => 'nullable|image|max:3072', // 3 MB

            // si decidieras exponer user_id manualmente (no es necesario con email):
            // 'user_id'   => 'nullable|integer|exists:users,id|unique:drivers,user_id,' . ($driverId ?: 'NULL'),
        ];
    }
}
