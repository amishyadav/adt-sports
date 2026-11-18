<?php

namespace App\Http\Requests\Ktms;

use App\Models\Ktms\Tournament;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TournamentRequest extends FormRequest
{
    /** Authorisation is handled by the admin.only middleware on the route. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'season' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'venue' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(array_keys(Tournament::STATUSES))],
            'half_duration_seconds' => ['required', 'integer', 'min:60', 'max:3600'],
            'raid_duration_seconds' => ['required', 'integer', 'min:10', 'max:120'],
            'players_per_side' => ['required', 'integer', 'min:5', 'max:12'],
        ];
    }

    public function attributes(): array
    {
        return [
            'half_duration_seconds' => 'half duration',
            'raid_duration_seconds' => 'raid duration',
            'players_per_side' => 'players per side',
        ];
    }
}
