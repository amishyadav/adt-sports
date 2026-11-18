<?php

namespace App\Http\Requests\Ktms;

use App\Models\Ktms\Player;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Unchecked checkboxes are absent from the payload entirely.
        $this->merge([
            'is_captain' => $this->boolean('is_captain'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $player = $this->route('player');
        $teamId = $player?->team_id ?? $this->route('team')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'jersey_number' => [
                'required', 'integer', 'min:0', 'max:999',
                // Two players in one squad cannot share a jersey number.
                Rule::unique('ktms_players', 'jersey_number')
                    ->where(fn ($query) => $query->where('team_id', $teamId))
                    ->ignore($player?->id),
            ],
            'role' => ['required', Rule::in(array_keys(Player::ROLES))],
            'position' => ['nullable', Rule::in(array_keys(Player::POSITIONS))],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'height_cm' => ['nullable', 'integer', 'min:100', 'max:250'],
            'weight_kg' => ['nullable', 'integer', 'min:30', 'max:200'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'is_captain' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'jersey_number.unique' => 'That jersey number is already taken in this squad.',
        ];
    }
}
